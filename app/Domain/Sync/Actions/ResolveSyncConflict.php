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
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Actions\RecordCashMovement;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\DTO\RecordCashMovementData;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Sync\DTO\ResolveSyncConflictData;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
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
 */
final class ResolveSyncConflict
{
    /**
     * Xung đột dính tiền — bắt buộc người duyệt bằng mã PIN trước khi ghi
     * quyết định, kể cả khi chọn "Bỏ qua" (đây cũng là một quyết định về
     * tiền, không phải việc không ai chịu trách nhiệm).
     *
     * @var list<ConflictKind>
     */
    private const CAN_PIN_DUYET = [ConflictKind::ThuTienTrung, ConflictKind::ThuVuotGiamGia];

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
        return DB::transaction(function () use ($data): SyncConflict {
            $conflict = SyncConflict::query()->lockForUpdate()->findOrFail($data->conflictId);

            if ($conflict->status !== ConflictStatus::Pending) {
                throw new DomainException('Xung đột này đã được xử lý rồi, không xử lý lại được nữa.');
            }

            $lyDo = trim($data->note);
            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do quyết định.');
            }

            if (in_array(ConflictKind::from($conflict->conflict_kind), self::CAN_PIN_DUYET, true)) {
                if ($data->approverUserId === null || $data->approverPin === null) {
                    throw new DomainException('Xung đột liên quan tới tiền phải có người duyệt bằng mã PIN.');
                }

                $this->verifyApproverPin->handle(new PinVerifyData(
                    userId: $data->approverUserId,
                    pin: $data->approverPin,
                    requestedByUserId: $data->resolvedByUserId,
                ));
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
            ConflictKind::ThuVuotGiamGia => $this->xuLyThuVuotGiamGia($conflict, $goc, $phuongAn, $data),
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

            $this->apDungCum($cum, $ganLuot->table_session_id, $data->resolvedByUserId);

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

            $this->apDungCumTheoUuid($cum, $data->resolvedByUserId);

            $conflict->table_session_id = $luotMoi->id;

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 4: hai máy cùng thu tiền ────────────────────────────────────

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

    // ── dòng 5: thu offline, giảm giá online ─────────────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyThuVuotGiamGia(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        $payload = $goc['payload'];
        $tableSessionId = (int) $conflict->table_session_id;
        $tendered = isset($payload['tendered_amount']) ? Money::fromInt((int) $payload['tendered_amount']) : null;

        if ($phuongAn === 'thu_du_hoan_phan_thua') {
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

            $this->apDungCumTheoUuid($cum, $data->resolvedByUserId);

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
            $caDangMo = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();
            if ($caDangMo === null) {
                throw new DomainException('Chưa có ca nào đang mở để gán tiền này vào — chọn "Chờ mở ca mới" thay.');
            }

            $tableSessionId = (int) $conflict->table_session_id;
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSessionId);
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
            $payload = $goc['payload'];
            $order = Order::query()->with('items')->where('uuid', (string) $payload['uuid'])->first();
            if ($order === null) {
                throw new DomainException('Không tìm thấy phiếu gọi món gốc để tính phần chênh lệch.');
            }

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

            $this->calculateBill->handle(new CalculateBillData(
                tableSessionId: (int) $conflict->table_session_id,
                discountAmount: Money::fromInt($chenhLech),
                discountReason: $chenhLech > 0
                    ? "Giảm bù chênh lệch giá — xung đột #{$conflict->id}, phiếu tạm tính offline mang giá cũ."
                    : null,
                requestedByUserId: $data->resolvedByUserId,
                approverUserId: null,
                approverPin: null,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
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

            $this->apDungCumTheoUuid($cum, $data->resolvedByUserId);

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
    private function apDungCum(array $cum, int $tableSessionId, int $nguoiThucHien): void
    {
        foreach ($cum as $op) {
            $this->apDungMotOpDonGian($op, $tableSessionId, $nguoiThucHien);
        }
    }

    /**
     * Áp dụng lại một cụm thao tác con — tra `table_session_uuid`/`order_uuid`/
     * `order_item_uuid` trong payload để tìm đúng bản ghi (dùng khi lượt khách
     * hoặc phiếu gọi món GỐC đã được tạo lại với ĐÚNG uuid cũ ngay trước đó).
     *
     * @param  list<array<string, mixed>>  $cum
     */
    private function apDungCumTheoUuid(array $cum, int $nguoiThucHien): void
    {
        foreach ($cum as $op) {
            $this->apDungMotOpDonGian($op, null, $nguoiThucHien);
        }
    }

    /** @param array<string, mixed> $op */
    private function apDungMotOpDonGian(array $op, ?int $ganLuotId, int $nguoiThucHien): void
    {
        $payload = $op['payload'];

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
            'cancel_order_item' => $this->apDungCancelOrderItem($payload, $nguoiThucHien),
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

    /** @param array<string, mixed> $payload */
    private function apDungCancelOrderItem(array $payload, int $nguoiThucHien): void
    {
        $dongMon = OrderItem::query()->where('uuid', (string) $payload['order_item_uuid'])->firstOrFail();

        $this->cancelOrderItem->handle(new CancelOrderItemData(
            orderId: $dongMon->order_id,
            orderItemId: $dongMon->id,
            quantity: (int) $payload['quantity'],
            reason: (string) $payload['reason'],
            cancelledByUserId: $nguoiThucHien,
            approverUserId: null,
            approverPin: null,
            newItemUuid: $payload['new_item_uuid'] ?? null,
            optionUuids: $payload['option_uuids'] ?? [],
        ));
    }
}
