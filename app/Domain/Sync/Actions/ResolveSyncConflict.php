<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Billing\Actions\CalculateBill;
use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTO\CalculateBillData;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Ordering\Actions\AttachTable;
use App\Domain\Ordering\Actions\CancelOrderItem;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\Actions\DetachTable;
use App\Domain\Ordering\Actions\OpenTableSession;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\SendToKitchen;
use App\Domain\Ordering\DTO\AttachTableData;
use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\DTO\DetachTableData;
use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\PlaceOrderItemData;
use App\Domain\Ordering\DTO\PlaceOrderItemOptionData;
use App\Domain\Ordering\DTO\SendToKitchenData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Ordering\Policies\TableSessionPolicy;
use App\Domain\Staffing\Actions\RecordCashMovement;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\DTO\RecordCashMovementData;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\DTO\ResolveSyncConflictData;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use App\Exceptions\ApprovalPinRequiredException;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Người (chủ quán/thu ngân) quyết một xung đột đồng bộ đang chờ ở màn hình
 * Bước 5 — theo đúng ma trận và câu chữ ở docs/thiet-ke-dong-bo.md mục 5.
 *
 * KHÔNG viết logic nghiệp vụ tiền/bàn/món ở đây — mọi hiệu ứng dữ liệu đi qua
 * đúng Action Phase 1 đã có, giống nguyên tắc của SyncBatch (Bước 4). Action
 * này chỉ:
 *  1. Khoá đúng một bản ghi `sync_conflicts` (chống bấm xử lý hai lần).
 *  2. Với mỗi (loại xung đột, phương án chọn), gọi ĐÚNG Action Phase 1 tương
 *     ứng — có phương án không làm gì cả (VD "Két không thừa", "Huỷ món") vì
 *     bản thân "không tạo dữ liệu gì" chính là hiệu ứng đúng.
 *  3. Chốt lại bản ghi xung đột: ai quyết, lúc nào, lý do gì.
 *
 * Khác với SyncBatch (mỗi thao tác một giao dịch riêng vì gói có thể tới 200
 * việc — hỏng một cái không được mất luôn phần còn lại), ở đây NGƯỜI đang xử
 * lý ĐÚNG MỘT xung đột nên bọc toàn bộ trong một `DB::transaction` cha: các
 * Action Phase 1 gọi bên trong dùng `DB::transaction` riêng của chúng, Laravel
 * tự biến thành SAVEPOINT lồng nhau — hỏng ở bất kỳ bước nào thì rollback hết,
 * xung đột vẫn giữ nguyên "pending" để thử lại, không bao giờ chốt nửa vời.
 *
 * DUYỆT PIN LUÔN XẢY RA TRƯỚC KHI MỞ TRANSACTION (sửa 05/08 sau kiểm toán):
 * nếu xung đột cần người duyệt — hoặc chắc chắn cần (thu trùng, thu vượt sau
 * giảm giá), hoặc CÓ THỂ cần tuỳ số tiền (giá món đổi, khi khoản giảm bù vượt
 * ngưỡng % của người xử lý) — việc xác thực PIN diễn ra ở duyetPinTruocGiaoDich()
 * TRƯỚC bất kỳ `DB::transaction`/`lockForUpdate` nào. Thiếu hoặc sai PIN thì
 * ném ApprovalPinRequiredException ngay tại đó: chưa khoá một dòng dữ liệu
 * nào, chưa có gì để rollback. Lý do: một giao dịch mở sẵn rồi mới phát hiện
 * thiếu PIN sẽ giữ khoá TableSession/Shift trong lúc chờ người khác đi lấy mã
 * — không ai thu tiền được các bàn khác thuộc cùng ca trong lúc đó.
 */
final class ResolveSyncConflict
{
    /**
     * Xung đột dính tiền, LUÔN cần người duyệt bằng mã PIN bất kể số tiền —
     * kể cả khi chọn "Bỏ qua" (đây cũng là một quyết định về tiền, không phải
     * việc không ai chịu trách nhiệm).
     *
     * @var list<ConflictKind>
     */
    private const CAN_PIN_DUYET = [
        ConflictKind::ThuTienTrung,
        ConflictKind::ThuMotPhanVuot,
        ConflictKind::ThuVuotGiamGia,
        ConflictKind::ThuVuotTongDoiKhac,
    ];

    public function __construct(
        private readonly OpenTableSession $openTableSession,
        private readonly AttachTable $attachTable,
        private readonly DetachTable $detachTable,
        private readonly PlaceOrder $placeOrder,
        private readonly SendToKitchen $sendToKitchen,
        private readonly CancelOrderItem $cancelOrderItem,
        private readonly RecordPayment $recordPayment,
        private readonly CloseTableSession $closeTableSession,
        private readonly CalculateBill $calculateBill,
        private readonly RecordCashMovement $recordCashMovement,
        private readonly VerifyApproverPin $verifyApproverPin,
    ) {}

    public function handle(ResolveSyncConflictData $data): SyncConflict
    {
        // Validate những gì kiểm được mà KHÔNG cần đọc gì từ database TRƯỚC
        // — giữ đúng thứ tự ưu tiên lỗi cũ (thiếu lý do báo trước, không lẫn
        // với lỗi PIN) và không mở transaction cho một request chắc chắn hỏng.
        $lyDo = trim($data->note);
        if ($lyDo === '') {
            throw new DomainException('Phải ghi rõ lý do quyết định.');
        }

        // Đọc KHÔNG khoá — chỉ để biết loại xung đột/phương án và quyết định
        // có cần PIN không. Bản ghi thật sự dùng để áp dụng hiệu ứng được đọc
        // LẠI có khoá bên trong transaction ở dưới; đọc hai lần ở đây chấp
        // nhận được vì lần đọc này không ghi gì, không giữ khoá gì.
        $conflictSoBo = SyncConflict::query()->findOrFail($data->conflictId);

        $this->duyetPinTruocGiaoDich($conflictSoBo, $data);

        return DB::transaction(function () use ($data, $lyDo): SyncConflict {
            $conflict = SyncConflict::query()->lockForUpdate()->findOrFail($data->conflictId);

            if ($conflict->status !== ConflictStatus::Pending) {
                throw new DomainException('Xung đột này đã được xử lý rồi, không xử lý lại được nữa.');
            }

            if ($data->dismiss) {
                $conflict->update([
                    'status' => ConflictStatus::Dismissed,
                    'resolution' => 'bo_qua',
                    'resolution_note' => $lyDo,
                    'resolved_by_user_id' => $data->resolvedByUserId,
                    'resolved_at' => now(),
                ]);

                return $conflict->refresh();
            }

            $phuongAn = $data->resolution !== null ? trim($data->resolution) : '';
            if ($phuongAn === '') {
                throw new DomainException('Phải chọn một phương án xử lý.');
            }

            $this->apDungHieuUng($conflict, $phuongAn, $data);

            $conflict->update([
                'status' => ConflictStatus::Resolved,
                'resolution' => $phuongAn,
                'resolution_note' => $lyDo,
                'resolved_by_user_id' => $data->resolvedByUserId,
                'resolved_at' => now(),
            ]);

            return $conflict->refresh();
        });
    }

    /**
     * Xác định xung đột này có cần người duyệt bằng PIN không, và xác thực
     * PIN ngay tại đây — TRƯỚC bất kỳ DB::transaction/lockForUpdate nào (xem
     * ghi chú đầu file). Không cần PIN thì không làm gì cả.
     *
     * Ba nguồn cần PIN:
     *  1. `CAN_PIN_DUYET` — bốn loại xung đột LUÔN cần PIN bất kể số tiền
     *     (thu trùng, thu một phần vượt, thu vượt sau giảm giá, thu vượt vì
     *     tổng đổi lý do khác — mọi loại đều đụng tới tiền mặt đã nhận thật),
     *     kể cả khi chọn "Bỏ qua".
     *  2. `GiaLech` + phương án "giam_gia_bu" — CHỈ cần PIN khi khoản giảm bù
     *     tính ra vượt ngưỡng % của người đang xử lý (chủ quán không bao giờ
     *     cần, thu ngân cần khi vượt 20% — đúng luật đang áp cho CalculateBill
     *     ở nghiệp vụ giảm giá tay). Ước tính % ở đây đọc dữ liệu KHÔNG khoá,
     *     chỉ để quyết định có đòi PIN hay không — số tiền giảm bù CHÍNH THỨC
     *     vẫn do CalculateBill tính lại một lần nữa, có khoá, bên trong
     *     transaction. Ước tính lệch (hiếm, do dữ liệu đổi giữa hai lần đọc)
     *     không gây sai tiền: nếu ước tính "không cần PIN" nhưng lúc tính
     *     thật lại vượt ngưỡng, CalculateBill vẫn tự chặn và toàn bộ rollback
     *     — chỉ mất công thử lại, không bao giờ lọt qua được ngưỡng.
     *  3. `GiaLech` + phương án "giu_gia_moi", hoặc dismiss cho `GiaLech`:
     *     không đổi discount gì, không bao giờ cần PIN.
     *  4. Ba loại `HaiMayMoBan`/`LuotDaDong`/`LuotDaHuy`/`ThieuThaoTacGoc`, ở
     *     phương án làm mới lượt khách rồi ÁP LẠI CỤM THAO TÁC (`apDungCum`/
     *     `apDungCumTheoUuid`) — cụm này có thể chứa một thao tác
     *     `cancel_order_item` nhắm vào dòng món ĐÃ "served" (bếp đã bưng ra
     *     bàn trước khi xung đột được phát hiện). `CancelOrderItem::handle()`
     *     tự đòi PIN cho trường hợp này (H5), nhưng đòi BÊN TRONG
     *     `DB::transaction` của chính nó — nếu không kiểm trước ở đây thì
     *     `apDungCancelOrderItem()` vẫn sẽ truyền `null`/`null` xuống, và lỗi
     *     PIN nổ ra giữa transaction đã mở (đúng lỗi hình dạng đã sửa 05/08,
     *     lẽ ra không được lặp lại) — xem `cumCanPinDuyet()`.
     */
    private function duyetPinTruocGiaoDich(SyncConflict $conflict, ResolveSyncConflictData $data): void
    {
        $kind = ConflictKind::from($conflict->conflict_kind);
        $phuongAn = trim((string) $data->resolution);
        $cum = $conflict->payload['cum'] ?? [];

        $canPin = match (true) {
            in_array($kind, self::CAN_PIN_DUYET, true) => true,
            $kind === ConflictKind::GiaLech
                && ! $data->dismiss
                && $phuongAn === 'giam_gia_bu' => $this->giamGiaBuVuotNguong($conflict, $data),
            $kind === ConflictKind::HaiMayMoBan
                && ! $data->dismiss
                && in_array($phuongAn, ['gop', 'tach'], true) => $this->cumCanPinDuyet($cum),
            in_array($kind, [ConflictKind::LuotDaDong, ConflictKind::LuotDaHuy], true)
                && ! $data->dismiss
                && $phuongAn === 'mo_luot_moi' => $this->cumCanPinDuyet($cum),
            $kind === ConflictKind::ThieuThaoTacGoc
                && ! $data->dismiss
                && $phuongAn === 'tao_luot_moi' => $this->cumCanPinDuyet($cum),
            default => false,
        };

        if (! $canPin) {
            return;
        }

        if ($data->approverUserId === null || $data->approverPin === null) {
            throw new ApprovalPinRequiredException('Xung đột này cần người duyệt bằng mã PIN trước khi xử lý.');
        }

        try {
            $this->verifyApproverPin->handle(new PinVerifyData(
                userId: $data->approverUserId,
                pin: $data->approverPin,
                requestedByUserId: $data->resolvedByUserId,
            ));
        } catch (DomainException $e) {
            // Sai PIN, khoá tạm, hay người duyệt không hợp lệ — với màn hình
            // xử lý xung đột đây LUÔN là "chưa duyệt được", không phải một lỗi
            // nghiệp vụ khác, nên đổi thành đúng mã lỗi riêng cho POS.
            throw new ApprovalPinRequiredException($e->getMessage());
        }
    }

    /**
     * Ước tính (không khoá) khoản giảm bù chênh lệch giá có vượt ngưỡng %
     * giảm giá của người đang xử lý xung đột hay không — dùng CalculateBill's
     * cùng công thức làm tròn LÊN như CalculateBill::phanTramLamTron().
     */
    private function giamGiaBuVuotNguong(SyncConflict $conflict, ResolveSyncConflictData $data): bool
    {
        $goc = $conflict->payload['goc'] ?? null;
        if ($goc === null) {
            return false; // Không tính được — apDungHieuUng() sẽ tự báo lỗi thiếu payload lúc thực thi.
        }

        $chenhLech = $this->tinhChenhLechGiaLech($goc);
        if ($chenhLech <= 0) {
            return false;
        }

        $tableSession = TableSession::query()->find($conflict->table_session_id);
        if ($tableSession === null) {
            return true; // Không tra được lượt khách — an toàn hơn là bắt duyệt.
        }

        // Đọc thẳng từ order_items (KHÔNG dùng cột table_sessions.subtotal_amount
        // — cột đó chỉ đúng SAU KHI RecalculateSessionSubtotal chạy, mà bước đó
        // chỉ xảy ra bên trong CalculateBill, TRONG transaction, xảy ra SAU pre-check
        // này). Cùng công thức T2 với RecalculateSessionSubtotal, chỉ khác là
        // không ghi lại cột — đây chỉ là ước tính.
        $tamTinh = $this->tamTinhHienTaiKhongKhoa($tableSession);
        if ($tamTinh <= 0) {
            return true;
        }

        $phanTram = $this->phanTramLamTron($chenhLech, $tamTinh);

        $nguoiXuLy = User::query()->find($data->resolvedByUserId);
        if ($nguoiXuLy === null) {
            return true;
        }

        return ! (new TableSessionPolicy)->discount($nguoiXuLy, $tableSession, $phanTram);
    }

    /**
     * Ước tính (không khoá) một cụm thao tác con có chứa lệnh huỷ món nhắm
     * vào dòng món ĐÃ "served" hay không — nếu có, `apDungCancelOrderItem()`
     * bên dưới chắc chắn cần PIN (H5 của `CancelOrderItem`). Đọc KHÔNG khoá,
     * chỉ để quyết định có đòi PIN trước hay không; bản thân `CancelOrderItem`
     * vẫn tự kiểm tra lại trạng thái CÓ khoá lúc áp dụng thật — ước tính lệch
     * ở đây (hiếm) không làm lọt qua được lỗi thiếu PIN, cùng nguyên tắc với
     * `giamGiaBuVuotNguong()`.
     *
     * @param  list<array<string, mixed>>  $cum
     */
    private function cumCanPinDuyet(array $cum): bool
    {
        foreach ($cum as $op) {
            if (($op['type'] ?? null) !== 'cancel_order_item') {
                continue;
            }

            $uuidDongMon = (string) ($op['payload']['order_item_uuid'] ?? '');
            if ($uuidDongMon === '') {
                continue;
            }

            $dongMon = OrderItem::query()->where('uuid', $uuidDongMon)->first();
            if ($dongMon !== null && $dongMon->status === OrderItemStatus::Served) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ước tính tạm tính hiện tại của một lượt khách — KHÔNG khoá, KHÔNG ghi
     * lại cột `subtotal_amount` (đó là việc của RecalculateSessionSubtotal,
     * chạy có khoá bên trong CalculateBill). Cùng công thức T2 (tổng
     * `line_amount` của mọi dòng món chưa huỷ thuộc mọi phiếu chưa huỷ).
     */
    private function tamTinhHienTaiKhongKhoa(TableSession $tableSession): int
    {
        return (int) OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('table_session_id', $tableSession->id)
                ->where('status', '!=', OrderStatus::Cancelled))
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->sum('line_amount');
    }

    /**
     * Cùng công thức làm tròn LÊN như CalculateBill::phanTramLamTron() — không
     * dùng chung được vì đó là private method của class khác, và ở đây chỉ
     * cần ƯỚC TÍNH (xem giamGiaBuVuotNguong()), không cần đi qua Money.
     */
    private function phanTramLamTron(int $giamGia, int $tamTinh): int
    {
        if ($tamTinh <= 0) {
            return 0;
        }

        return intdiv($giamGia * 100 + $tamTinh - 1, $tamTinh);
    }

    private function apDungHieuUng(SyncConflict $conflict, string $phuongAn, ResolveSyncConflictData $data): void
    {
        $kind = ConflictKind::from($conflict->conflict_kind);
        $goc = $conflict->payload['goc'] ?? null;
        $cum = $conflict->payload['cum'] ?? [];

        if ($goc === null) {
            throw new DomainException('Bản ghi xung đột thiếu dữ liệu thao tác gốc, không áp dụng lại được.');
        }

        match ($kind) {
            ConflictKind::HaiMayMoBan => $this->xuLyHaiMayMoBan($conflict, $goc, $cum, $phuongAn, $data),
            ConflictKind::ThuTienTrung => $this->xuLyThuTienTrung($conflict, $goc, $phuongAn, $data),
            ConflictKind::ThuMotPhanVuot => $this->xuLyThuMotPhanVuot($conflict, $goc, $phuongAn, $data),
            ConflictKind::ThuVuotGiamGia => $this->xuLyThuVuotGiamGia($conflict, $goc, $phuongAn, $data),
            ConflictKind::ThuVuotTongDoiKhac => $this->xuLyThuVuotTongDoiKhac($conflict, $goc, $phuongAn, $data),
            ConflictKind::LuotDaDong, ConflictKind::LuotDaHuy => $this->xuLyLuotDaDongHoacHuy($conflict, $goc, $cum, $phuongAn, $data),
            ConflictKind::CaDaDong => $this->xuLyCaDaDong($conflict, $goc, $phuongAn, $data),
            ConflictKind::GiaLech => $this->xuLyGiaLech($conflict, $goc, $phuongAn, $data),
            ConflictKind::ThieuThaoTacGoc => $this->xuLyThieuThaoTacGoc($conflict, $goc, $cum, $phuongAn, $data),
            // Dòng 1 (bếp báo hết món) chưa có cách sinh ra xung đột này trong hệ
            // thống hiện tại (chưa có tính năng "bếp báo hết món") — xem
            // docs/viec-ton.md. Không có phương án nào để áp dụng, chỉ có thể ghi
            // lại quyết định (đã làm ở handle() qua trường resolution/note).
            ConflictKind::MonDaHet => null,
        };
    }

    // ── dòng 2: hai máy cùng mở bàn ──────────────────────────────────────

    /**
     * @param  array<string, mixed>  $goc
     * @param  list<array<string, mixed>>  $cum
     */
    private function xuLyHaiMayMoBan(SyncConflict $conflict, array $goc, array $cum, string $phuongAn, ResolveSyncConflictData $data): void
    {
        $payload = $goc['payload'];

        if ($phuongAn === 'gop') {
            $idBan = array_map('intval', $payload['dining_table_ids']);
            $ganLuot = TableSessionTable::query()
                ->whereIn('dining_table_id', $idBan)
                ->whereNull('detached_at')
                ->first();

            if ($ganLuot === null) {
                throw new DomainException('Không tìm thấy lượt khách nào đang giữ bàn này để gộp vào — có thể bàn đã trống, xem lại tình huống trước khi chọn "Gộp".');
            }

            $this->apDungCum($cum, $ganLuot->table_session_id, $data);

            return;
        }

        if ($phuongAn === 'tach') {
            if ($data->diningTableIds === []) {
                throw new DomainException('Phải chọn ít nhất một bàn khác cho lượt khách này khi chọn "Tách".');
            }

            $luotMoi = $this->openTableSession->handle(new OpenTableSessionData(
                uuid: (string) $payload['uuid'],
                diningTableIds: $data->diningTableIds,
                primaryDiningTableId: $data->diningTableIds[0],
                guestCount: (int) $payload['guest_count'],
                openedByUserId: $data->resolvedByUserId,
            ));

            $this->apDungCumTheoUuid($cum, $data);

            $conflict->table_session_id = $luotMoi->id;

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 4a: hai máy cùng thu tiền, đúng khoản đã thu ─────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyThuTienTrung(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'ket_khong_thua') {
            return; // Thu trùng, bỏ phiếu này — không tạo dữ liệu gì, đây chính là hiệu ứng đúng.
        }

        if ($phuongAn === 'ket_co_thua') {
            $payload = $goc['payload'];
            $soTien = Money::fromInt((int) $payload['amount']);

            $ca = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();
            if ($ca === null) {
                throw new DomainException('Chưa có ca nào đang mở, không ghi được khoản thu chi vặt cho phần tiền thừa. Mở ca rồi xử lý lại.');
            }

            $tenBan = $this->tenBanCuaLuot($conflict->table_session_id);

            $this->recordCashMovement->handle(new RecordCashMovementData(
                shiftId: $ca->id,
                direction: CashDirection::In,
                amount: $soTien,
                reason: "Thu trùng bàn {$tenBan} - xung đột #{$conflict->id} - khách trả hai lần thật",
                createdByUserId: $data->resolvedByUserId,
            ));

            $this->recordCashMovement->handle(new RecordCashMovementData(
                shiftId: $ca->id,
                direction: CashDirection::Out,
                amount: $soTien,
                reason: "Hoàn lại bàn {$tenBan} - xung đột #{$conflict->id}",
                createdByUserId: $data->resolvedByUserId,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 4b: thu một phần ở hai nơi, cộng lại vượt ────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyThuMotPhanVuot(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'bo_phieu') {
            return; // Không chắc khách đã đưa tiền — không tạo dữ liệu gì, đúng như đã chọn.
        }

        if ($phuongAn === 'ghi_nhan_hoan_phan_thua') {
            $this->ghiNhanPhanConLai($conflict, $goc, $data);

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 5a: thu offline, giảm giá online ─────────────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyThuVuotGiamGia(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        $payload = $goc['payload'];
        $tableSessionId = (int) $conflict->table_session_id;
        $tendered = isset($payload['tendered_amount']) ? Money::fromInt((int) $payload['tendered_amount']) : null;

        if ($phuongAn === 'thu_du_hoan_phan_thua') {
            $this->ghiNhanPhanConLai($conflict, $goc, $data);

            return;
        }

        if ($phuongAn === 'bo_giam_gia') {
            $this->calculateBill->handle(new CalculateBillData(
                tableSessionId: $tableSessionId,
                discountAmount: Money::zero(),
                discountReason: null,
                requestedByUserId: $data->resolvedByUserId,
                approverUserId: null,
                approverPin: null,
            ));

            $this->recordPayment->handle(new RecordPaymentData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $tableSessionId,
                method: PaymentMethod::Cash,
                amount: Money::fromInt((int) $payload['amount']),
                tenderedAmount: $tendered,
                reference: null,
                receivedByUserId: $data->resolvedByUserId,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 5b: thu offline, tổng đổi vì lý do khác (thường là huỷ món) ──

    /** @param array<string, mixed> $goc */
    private function xuLyThuVuotTongDoiKhac(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'bo_phieu') {
            return; // Không chắc khách đã đưa tiền — không tạo dữ liệu gì, đúng như đã chọn.
        }

        if ($phuongAn === 'thu_du_hoan_phan_thua') {
            $this->ghiNhanPhanConLai($conflict, $goc, $data);

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    /**
     * Ghi nhận đúng phần CÒN THIẾU hiện tại (đọc lại, có khoá, tại thời điểm
     * xử lý — không dùng số đã ước tính lúc SyncBatch phát hiện xung đột, vì
     * dữ liệu có thể đã đổi thêm từ đó tới giờ) và hoàn phần thừa cho khách
     * — dùng chung cho ba phương án cùng bản chất "thu đúng phần còn lại":
     * ThuVuotGiamGia/thu_du_hoan_phan_thua, ThuMotPhanVuot/ghi_nhan_hoan_phan_thua,
     * ThuVuotTongDoiKhac/thu_du_hoan_phan_thua. Phần "hoàn thừa" không cần
     * CashMovement riêng — RecordPayment tự tính `change_amount` từ
     * `tenderedAmount − amount` (T7), đúng số tiền mặt khách đã đưa mà máy
     * POS ghi lại lúc offline.
     *
     * @param  array<string, mixed>  $goc
     */
    private function ghiNhanPhanConLai(SyncConflict $conflict, array $goc, ResolveSyncConflictData $data): void
    {
        $payload = $goc['payload'];
        $tableSessionId = (int) $conflict->table_session_id;
        $tendered = isset($payload['tendered_amount']) ? Money::fromInt((int) $payload['tendered_amount']) : null;

        $session = TableSession::query()->lockForUpdate()->findOrFail($tableSessionId);
        $conThieu = Money::fromInt($session->total_amount)->minus(Money::fromInt($session->paid_amount));

        $this->recordPayment->handle(new RecordPaymentData(
            uuid: (string) $payload['uuid'],
            tableSessionId: $tableSessionId,
            method: PaymentMethod::Cash,
            amount: $conThieu,
            tenderedAmount: $tendered ?? $conThieu,
            reference: null,
            receivedByUserId: $data->resolvedByUserId,
        ));
    }

    // ── dòng 6/9: gọi món vào lượt đã đóng/đã huỷ ────────────────────────

    /**
     * @param  array<string, mixed>  $goc
     * @param  list<array<string, mixed>>  $cum
     */
    private function xuLyLuotDaDongHoacHuy(SyncConflict $conflict, array $goc, array $cum, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'huy_mon') {
            return; // Gọi nhầm — không tạo phiếu gì, đúng như đã chọn.
        }

        if ($phuongAn === 'mo_luot_moi') {
            $idBan = $data->diningTableIds !== []
                ? $data->diningTableIds
                : $this->banCuaLuotCu((int) $conflict->table_session_id);

            if ($idBan === []) {
                throw new DomainException('Không xác định được bàn cho lượt khách mới — phải tự chọn bàn.');
            }

            $luotMoi = $this->openTableSession->handle(new OpenTableSessionData(
                uuid: (string) Str::uuid(),
                diningTableIds: $idBan,
                primaryDiningTableId: $idBan[0],
                guestCount: 1,
                openedByUserId: $data->resolvedByUserId,
            ));

            $payload = $goc['payload'];
            $this->placeOrder->handle(new PlaceOrderData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $luotMoi->id,
                items: $this->xayItems($payload['items']),
                note: null,
                createdByUserId: $data->resolvedByUserId,
            ));

            $this->apDungCumTheoUuid($cum, $data);

            $conflict->table_session_id = $luotMoi->id;

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    /**
     * Tên bàn để ghi vào lý do thu chi vặt — tra ngược được ba tháng sau
     * (yêu cầu 04/08). Dòng 4 (thu trùng) luôn xảy ra trên một lượt khách ĐÃ
     * thu đủ tiền, tức tại lúc xử lý bàn gần như chắc chắn đã được nhả
     * (detached_at khác NULL) — khác với CloseShift (chỉ cần bàn ĐANG chiếm để
     * chỉ đường cho nhân viên), ở đây cần TÊN BÀN ĐÃ TỪNG PHỤC VỤ lượt khách
     * này, không lọc theo detached_at. Không có bàn nào (lượt khách không
     * chiếm bàn) thì dùng mã lượt khách thay.
     */
    private function tenBanCuaLuot(?int $tableSessionId): string
    {
        if ($tableSessionId === null) {
            return 'không rõ';
        }

        $session = TableSession::query()->with('tables.diningTable')->find($tableSessionId);
        if ($session === null) {
            return 'không rõ';
        }

        $tenBan = $session->tables
            ->map(fn (TableSessionTable $bi) => $bi->diningTable->code)
            ->unique()
            ->implode(', ');

        return $tenBan !== '' ? $tenBan : "lượt khách {$session->code}";
    }

    /** @return list<int> */
    private function banCuaLuotCu(?int $tableSessionId): array
    {
        if ($tableSessionId === null) {
            return [];
        }

        return TableSessionTable::query()
            ->where('table_session_id', $tableSessionId)
            ->pluck('dining_table_id')
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ── dòng 7: phiếu thu thuộc ca đã đóng ───────────────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyCaDaDong(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'cho_mo_ca_moi') {
            return; // Quyết định chờ — chưa mở ca mới, không ghi phiếu thu nào cả.
        }

        if ($phuongAn === 'gan_ca_dang_mo') {
            // Luật CLAUDE.md mục 11: Payment → TableSession → Shift → DiningTable.
            $tableSessionId = (int) $conflict->table_session_id;
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSessionId);

            $caDangMo = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();
            if ($caDangMo === null) {
                throw new DomainException('Chưa có ca nào đang mở để gán tiền này vào — chọn "Chờ mở ca mới" thay.');
            }

            $session->update(['shift_id' => $caDangMo->id]);

            $payload = $goc['payload'];
            $this->recordPayment->handle(new RecordPaymentData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $tableSessionId,
                method: PaymentMethod::Cash,
                amount: Money::fromInt((int) $payload['amount']),
                tenderedAmount: isset($payload['tendered_amount']) ? Money::fromInt((int) $payload['tendered_amount']) : null,
                reference: null,
                receivedByUserId: $data->resolvedByUserId,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 8: giá món đổi ──────────────────────────────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyGiaLech(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'giu_gia_moi') {
            return; // Đã ghi theo giá server ngay lúc gọi món (SyncBatch) — không làm gì thêm.
        }

        if ($phuongAn === 'giam_gia_bu') {
            $order = $this->timDonTheoUuidGoc($goc);
            if ($order === null) {
                throw new DomainException('Không tìm thấy phiếu gọi món gốc để tính phần chênh lệch.');
            }

            $chenhLech = $this->tinhChenhLechTuDon($order, $goc);

            $this->calculateBill->handle(new CalculateBillData(
                tableSessionId: (int) $conflict->table_session_id,
                discountAmount: Money::fromInt($chenhLech),
                discountReason: $chenhLech > 0
                    ? "Giảm bù chênh lệch giá — xung đột #{$conflict->id}, phiếu tạm tính offline mang giá cũ."
                    : null,
                requestedByUserId: $data->resolvedByUserId,
                approverUserId: $data->approverUserId,
                approverPin: $data->approverPin,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    /** @param array<string, mixed> $goc */
    private function timDonTheoUuidGoc(array $goc): ?Order
    {
        $payload = $goc['payload'];

        return Order::query()->with('items')->where('uuid', (string) $payload['uuid'])->first();
    }

    /**
     * Số tiền khách cần được bù do server ghi giá CAO HƠN giá đã thấy lúc gọi
     * món offline (`client_unit_price`) — dùng chung bởi xuLyGiaLech() (áp
     * dụng thật, đã tự kiểm `$order !== null` trước khi gọi) và
     * giamGiaBuVuotNguong() (ước tính trước để quyết định có cần PIN hay
     * không, xem duyetPinTruocGiaoDich() và tinhChenhLechGiaLech()).
     *
     * @param  array<string, mixed>  $goc
     */
    private function tinhChenhLechTuDon(Order $order, array $goc): int
    {
        $payload = $goc['payload'];

        $chenhLech = 0;
        foreach ($payload['items'] as $itemPayload) {
            if (! isset($itemPayload['client_unit_price'])) {
                continue;
            }

            $dongMon = $order->items->firstWhere('uuid', $itemPayload['uuid']);
            if ($dongMon === null) {
                continue;
            }

            // Server luôn ghi giá CỦA MÌNH (dongMon->unit_price). Khách chỉ cần bù
            // phần chênh khi giá server CAO HƠN giá khách đã thấy lúc offline
            // (client_unit_price) — phiếu tạm tính in offline mang giá thấp hơn.
            // Giá server THẤP hơn thì khách không thiệt, không có gì để bù.
            $chenh = ($dongMon->unit_price - (int) $itemPayload['client_unit_price']) * $dongMon->quantity;
            if ($chenh > 0) {
                $chenhLech += $chenh;
            }
        }

        return $chenhLech;
    }

    /**
     * Ước tính khoản giảm bù (KHÔNG khoá, có thể trả 0 nếu không tìm thấy
     * phiếu gốc — xem giamGiaBuVuotNguong()).
     *
     * @param  array<string, mixed>  $goc
     */
    private function tinhChenhLechGiaLech(array $goc): int
    {
        $order = $this->timDonTheoUuidGoc($goc);
        if ($order === null) {
            return 0; // xuLyGiaLech() tự ném lỗi rõ ràng khi thật sự áp dụng; ở đây chỉ là ước tính.
        }

        return $this->tinhChenhLechTuDon($order, $goc);
    }

    // ── dòng 10: thiếu thao tác gốc ──────────────────────────────────────

    /**
     * @param  array<string, mixed>  $goc
     * @param  list<array<string, mixed>>  $cum
     */
    private function xuLyThieuThaoTacGoc(SyncConflict $conflict, array $goc, array $cum, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'bo_qua') {
            return;
        }

        if ($phuongAn === 'tao_luot_moi') {
            if ($goc['type'] !== 'place_order') {
                throw new DomainException('Phương án "Tạo lượt khách mới" chỉ áp dụng được khi thao tác gốc bị thiếu là gọi món.');
            }

            if ($data->diningTableIds === []) {
                throw new DomainException('Phải chọn bàn cho lượt khách mới.');
            }

            $luotMoi = $this->openTableSession->handle(new OpenTableSessionData(
                uuid: (string) Str::uuid(),
                diningTableIds: $data->diningTableIds,
                primaryDiningTableId: $data->diningTableIds[0],
                guestCount: 1,
                openedByUserId: $data->resolvedByUserId,
            ));

            $payload = $goc['payload'];
            $this->placeOrder->handle(new PlaceOrderData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $luotMoi->id,
                items: $this->xayItems($payload['items']),
                note: null,
                createdByUserId: $data->resolvedByUserId,
            ));

            $this->apDungCumTheoUuid($cum, $data);

            $conflict->table_session_id = $luotMoi->id;

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── tiện ích chung: áp dụng lại cụm thao tác đã lưu ──────────────────

    /**
     * Áp dụng lại một cụm thao tác con — ép thẳng vào MỘT lượt khách đã biết
     * trước ($tableSessionId), bỏ qua table_session_uuid trong payload (dùng
     * cho phương án "Gộp": lượt khách thắng đã tồn tại từ trước, không mang
     * uuid mà cụm này khai).
     *
     * @param  list<array<string, mixed>>  $cum
     */
    private function apDungCum(array $cum, int $tableSessionId, ResolveSyncConflictData $data): void
    {
        foreach ($cum as $op) {
            $this->apDungMotOpDonGian($op, $tableSessionId, $data);
        }
    }

    /**
     * Áp dụng lại một cụm thao tác con — tra `table_session_uuid`/`order_uuid`/
     * `order_item_uuid` trong payload để tìm đúng bản ghi (dùng khi lượt khách
     * hoặc phiếu gọi món GỐC đã được tạo lại với ĐÚNG uuid cũ ngay trước đó).
     *
     * @param  list<array<string, mixed>>  $cum
     */
    private function apDungCumTheoUuid(array $cum, ResolveSyncConflictData $data): void
    {
        foreach ($cum as $op) {
            $this->apDungMotOpDonGian($op, null, $data);
        }
    }

    /** @param array<string, mixed> $op */
    private function apDungMotOpDonGian(array $op, ?int $ganLuotId, ResolveSyncConflictData $data): void
    {
        $payload = $op['payload'];
        $nguoiThucHien = $data->resolvedByUserId;

        match ($op['type']) {
            'place_order' => $this->placeOrder->handle(new PlaceOrderData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                items: $this->xayItems($payload['items']),
                note: null,
                createdByUserId: $nguoiThucHien,
            )),
            'send_to_kitchen' => $this->sendToKitchen->handle(new SendToKitchenData(
                orderId: $this->timIdDonTheoUuid((string) $payload['order_uuid']),
            )),
            'cancel_order_item' => $this->apDungCancelOrderItem($payload, $data),
            'attach_table' => $this->attachTable->handle(new AttachTableData(
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                diningTableId: (int) $payload['dining_table_id'],
                attachedByUserId: $nguoiThucHien,
            )),
            'detach_table' => $this->detachTable->handle(new DetachTableData(
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                diningTableId: (int) $payload['dining_table_id'],
            )),
            'record_payment' => $this->recordPayment->handle(new RecordPaymentData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                method: PaymentMethod::Cash,
                amount: Money::fromInt((int) $payload['amount']),
                tenderedAmount: isset($payload['tendered_amount']) ? Money::fromInt((int) $payload['tendered_amount']) : null,
                reference: null,
                receivedByUserId: $nguoiThucHien,
            )),
            'close_session' => $this->closeTableSession->handle(new CloseTableSessionData(
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                closedByUserId: $nguoiThucHien,
            )),
            default => throw new DomainException("Không biết áp dụng lại thao tác loại \"{$op['type']}\"."),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $itemsPayload
     * @return list<PlaceOrderItemData>
     */
    private function xayItems(array $itemsPayload): array
    {
        return array_map(function (array $itemPayload): PlaceOrderItemData {
            $options = array_map(
                fn (array $optionPayload): PlaceOrderItemOptionData => new PlaceOrderItemOptionData(
                    uuid: (string) $optionPayload['uuid'],
                    optionId: (int) $optionPayload['option_id'],
                ),
                $itemPayload['options'] ?? []
            );

            return new PlaceOrderItemData(
                uuid: (string) $itemPayload['uuid'],
                productId: (int) $itemPayload['product_id'],
                productVariantId: (int) $itemPayload['product_variant_id'],
                quantity: (int) $itemPayload['quantity'],
                note: $itemPayload['note'] ?? null,
                options: $options,
            );
        }, $itemsPayload);
    }

    private function timIdLuotTheoUuid(string $uuid): int
    {
        return TableSession::query()->where('uuid', $uuid)->firstOrFail()->id;
    }

    private function timIdDonTheoUuid(string $uuid): int
    {
        return Order::query()->where('uuid', $uuid)->firstOrFail()->id;
    }

    /**
     * PIN cho lệnh huỷ món "served" đã được duyệt TRƯỚC ở
     * `duyetPinTruocGiaoDich()`/`cumCanPinDuyet()` (nếu cần) — truyền tiếp
     * đúng cặp approver đã duyệt, không phải `null`/`null`, để
     * `CancelOrderItem::handle()` không phải tự đòi PIN lần nữa GIỮA
     * transaction đang mở.
     *
     * @param  array<string, mixed>  $payload
     */
    private function apDungCancelOrderItem(array $payload, ResolveSyncConflictData $data): void
    {
        $dongMon = OrderItem::query()->where('uuid', (string) $payload['order_item_uuid'])->firstOrFail();

        $this->cancelOrderItem->handle(new CancelOrderItemData(
            orderId: $dongMon->order_id,
            orderItemId: $dongMon->id,
            quantity: (int) $payload['quantity'],
            reason: (string) $payload['reason'],
            cancelledByUserId: $data->resolvedByUserId,
            approverUserId: $data->approverUserId,
            approverPin: $data->approverPin,
            newItemUuid: $payload['new_item_uuid'] ?? null,
            optionUuids: $payload['option_uuids'] ?? [],
        ));
    }
}
