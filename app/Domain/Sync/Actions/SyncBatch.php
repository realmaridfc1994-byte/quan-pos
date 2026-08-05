<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\ProductVariant;
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
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Sync\DTO\SyncBatchData;
use App\Domain\Sync\DTO\SyncOperationData;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Enums\OperationStatus;
use App\Domain\Sync\Enums\OperationType;
use App\Domain\Sync\Models\SyncAppliedOp;
use App\Domain\Sync\Models\SyncConflict;
use App\Exceptions\DomainException;
use App\Exceptions\SyncBatchLockedException;
use App\Exceptions\ThaoTacGocKhongTimThayException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Áp dụng một gói thao tác gửi lên từ máy POS offline — theo đúng
 * docs/thiet-ke-dong-bo.md.
 *
 * KHÔNG viết logic nghiệp vụ ở đây (mục 0 của thiết kế). Mọi thay đổi dữ liệu
 * đi qua đúng Action Phase 1 đã có — Action này chỉ xếp thứ tự, phát hiện va
 * chạm theo ma trận mục 5, và gọi Action tương ứng trong DB::transaction
 * riêng của chính Action đó (mục 7.3, không bọc thêm transaction ở đây).
 *
 * Suy luận thêm ngoài thiết kế (đã trình bày và được duyệt trước khi viết):
 *  - CHỐNG TRÙNG Ở TẦNG ĐỒNG BỘ theo op_uuid (bảng `sync_applied_ops`, xem
 *    ketQuaApplied()) — độc lập với uuid nghiệp vụ trên từng bảng, áp dụng
 *    cho MỌI loại thao tác. Sửa đúng lỗi thật: gói đã áp dụng xong nhưng máy
 *    POS không nhận được response (mạng rớt lúc trả kết quả) rồi gửi lại —
 *    trước đây 5 loại thao tác không có uuid riêng (attach/detach/
 *    send_to_kitchen/close_session/huỷ món toàn bộ) sẽ bị Action gốc từ chối
 *    lần hai (VD SendToKitchen báo "đã gửi rồi"), khiến máy POS hiện lỗi đỏ
 *    và nhân viên bấm lại — bếp nhận tem hai lần. Giờ tra sổ TRƯỚC khi gọi
 *    bất kỳ Action nào, có rồi thì trả "duplicate" ngay, không chạy lại.
 *  - Xung đột (`sync_conflicts`) tự chống trùng theo `uq_sync_conflicts_op`
 *    sẵn có — gửi lại một thao tác đã ghi xung đột thì trả đúng conflict_id
 *    cũ, không tạo bản ghi chờ thứ hai (xem taoConflict()).
 *  - Bộ đếm "deferred quá 5 lần" (mục 3.3) dùng Cache::increment() theo
 *    op_uuid, không thêm cột/bảng mới.
 *  - `record_payment` dòng 7: RecordPayment tra CA CỦA CHÍNH LƯỢT KHÁCH (không
 *    tự lấy ca đang mở bất kỳ) — ca gốc đã đóng thì luôn từ chối. Không có
 *    Action nào đổi shift_id để "tự gán sang ca mới", nên dòng 7 LUÔN thành
 *    conflict (đúng "Cần người: ✅" của thiết kế), auto_action='khong_lam_gi',
 *    "gán sang ca đang mở" chỉ là một lựa chọn cho người quyết, không tự làm.
 *  - Mọi thao tác trong một gói được gán chung một người thực hiện: người
 *    đang đăng nhập trên máy POS lúc gửi gói (SyncBatchData::receivedByUserId).
 */
final class SyncBatch
{
    private const SO_LAN_DEFER_TOI_DA = 5;

    /**
     * Không lên lịch chạy nền riêng (quyết định 05/08 — xem CLAUDE.md/README):
     * một cửa sổ dòng lệnh phải mở suốt đời là thứ chắc chắn hỏng ở quán, và
     * việc dọn sổ tay chống trùng op_uuid không đủ quan trọng để đánh đổi lấy
     * phụ thuộc đó. Dọn NGAY TRONG luồng đồng bộ — nơi duy nhất chắc chắn
     * được gọi thường xuyên khi quán còn hoạt động.
     */
    private const SO_NGAY_GIU_SYNC_APPLIED_OP = 7;

    private const SO_DONG_XOA_TOI_DA_MOI_LAN = 1000;

    public function __construct(
        private readonly OpenTableSession $openTableSession,
        private readonly AttachTable $attachTable,
        private readonly DetachTable $detachTable,
        private readonly PlaceOrder $placeOrder,
        private readonly SendToKitchen $sendToKitchen,
        private readonly CancelOrderItem $cancelOrderItem,
        private readonly RecordPayment $recordPayment,
        private readonly CloseTableSession $closeTableSession,
    ) {}

    /** @return list<array<string, mixed>> */
    public function handle(SyncBatchData $data): array
    {
        if (count($data->operations) > 200) {
            throw new DomainException('Một gói tối đa 200 thao tác.');
        }

        $lock = Cache::lock('sync:batch', 120);

        try {
            $ketQua = $lock->block(5, fn () => $this->apDungCaGoi($data));
        } catch (LockTimeoutException) {
            throw new SyncBatchLockedException('Một gói đồng bộ khác đang được xử lý. Thử lại sau ít giây.');
        }

        // Ngoài mọi DB::transaction của thao tác (khoá toàn cục sync:batch đã
        // nhả ở dòng trên) — lỗi dọn dẹp KHÔNG BAO GIỜ được làm hỏng kết quả
        // đồng bộ đã có, chỉ ghi log. Xem donDepSyncAppliedOpsCu().
        $this->donDepSyncAppliedOpsCu($ketQua);

        return $ketQua;
    }

    /** @return list<array<string, mixed>> */
    private function apDungCaGoi(SyncBatchData $data): array
    {
        ['xep' => $daXep, 'vong_lap' => $vongLap] = $this->sapXepThaoTac($data->operations);

        $ketQua = [];
        /** @var array<string, OperationStatus> $trangThaiTheoUuid */
        $trangThaiTheoUuid = [];
        /** @var array<string, array<string, mixed>> $ketQuaTheoUuid */
        $ketQuaTheoUuid = [];

        foreach ($vongLap as $op) {
            $chiTiet = [
                'op_uuid' => $op->opUuid,
                'status' => OperationStatus::Rejected->value,
                'reason' => 'Thao tác nằm trong một vòng lặp phụ thuộc (depends_on quay vòng) — lỗi máy POS, không phải va chạm.',
            ];
            $trangThaiTheoUuid[$op->opUuid] = OperationStatus::Rejected;
            $ketQuaTheoUuid[$op->opUuid] = $chiTiet;
            $ketQua[] = $chiTiet;
        }

        foreach ($daXep as $op) {
            $chiTiet = $this->apDungMotThaoTac($op, $data, $trangThaiTheoUuid, $ketQuaTheoUuid);
            $trangThaiTheoUuid[$op->opUuid] = OperationStatus::from($chiTiet['status']);
            $ketQuaTheoUuid[$op->opUuid] = $chiTiet;
            $ketQua[] = $chiTiet;
        }

        return $ketQua;
    }

    /**
     * Kahn's algorithm — sắp theo đồ thị phụ thuộc trong CHÍNH GÓI NÀY, cùng
     * bậc thì theo occurred_at rồi vị trí gốc (mục 4.1). Thao tác còn sót lại
     * sau vòng lặp chính là những cái nằm trong một vòng phụ thuộc (#4).
     *
     * @param  list<SyncOperationData>  $operations
     * @return array{xep: list<SyncOperationData>, vong_lap: list<SyncOperationData>}
     */
    private function sapXepThaoTac(array $operations): array
    {
        $theoUuid = [];
        foreach ($operations as $op) {
            $theoUuid[$op->opUuid] = $op;
        }

        $bacVao = [];
        $conCua = [];
        foreach ($operations as $op) {
            $bacVao[$op->opUuid] ??= 0;
            foreach ($op->dependsOn as $chaUuid) {
                if (isset($theoUuid[$chaUuid])) {
                    $bacVao[$op->opUuid]++;
                    $conCua[$chaUuid][] = $op->opUuid;
                }
            }
        }

        $sapXepHangCho = static function (array $ds): array {
            usort($ds, fn (SyncOperationData $a, SyncOperationData $b) => $a->occurredAt->timestamp <=> $b->occurredAt->timestamp
                ?: $a->viTriGoc <=> $b->viTriGoc);

            return $ds;
        };

        $sanSang = $sapXepHangCho(array_values(array_filter($operations, fn ($op) => $bacVao[$op->opUuid] === 0)));

        $daXep = [];
        while ($sanSang !== []) {
            $op = array_shift($sanSang);
            $daXep[] = $op;

            $conMoi = [];
            foreach ($conCua[$op->opUuid] ?? [] as $conUuid) {
                $bacVao[$conUuid]--;
                if ($bacVao[$conUuid] === 0) {
                    $conMoi[] = $theoUuid[$conUuid];
                }
            }

            $sanSang = $sapXepHangCho(array_merge($sanSang, $conMoi));
        }

        $uuidDaXep = array_flip(array_map(fn ($op) => $op->opUuid, $daXep));
        $vongLap = array_values(array_filter($operations, fn ($op) => ! isset($uuidDaXep[$op->opUuid])));

        return ['xep' => $daXep, 'vong_lap' => $vongLap];
    }

    /**
     * @param  array<string, OperationStatus>  $trangThaiTheoUuid
     * @param  array<string, array<string, mixed>>  $ketQuaTheoUuid
     * @return array<string, mixed>
     */
    private function apDungMotThaoTac(
        SyncOperationData $op,
        SyncBatchData $data,
        array $trangThaiTheoUuid,
        array $ketQuaTheoUuid,
    ): array {
        // Lớp chống trùng Ở TẦNG ĐỒNG BỘ, theo op_uuid — độc lập với uuid
        // nghiệp vụ trên từng bảng (mục 3.3 docs/thiet-ke-dong-bo.md). Tra
        // TRƯỚC MỌI THỨ, kể cả trước khi xét thao tác cha: một thao tác đã
        // áp dụng xong rồi thì không cần biết cha nó là gì nữa.
        $daApDung = SyncAppliedOp::query()->find($op->opUuid);
        if ($daApDung !== null) {
            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Duplicate->value, ...$daApDung->result_payload];
        }

        foreach ($op->dependsOn as $chaUuid) {
            $trangThaiCha = $trangThaiTheoUuid[$chaUuid] ?? null;

            if ($trangThaiCha === OperationStatus::Conflict) {
                $conflictIdCuaCha = (int) $ketQuaTheoUuid[$chaUuid]['conflict_id'];
                $this->gomVaoCum($conflictIdCuaCha, $op);

                return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflictIdCuaCha];
            }

            if ($trangThaiCha === OperationStatus::Rejected) {
                return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Rejected->value, 'reason' => "Thao tác cha {$chaUuid} bị từ chối, thao tác này không thể áp dụng theo."];
            }
        }

        try {
            return match ($op->type) {
                OperationType::OpenSession => $this->xuLyOpenSession($op, $data),
                OperationType::AttachTable => $this->xuLyGoiActionDon($op, $data, fn () => $this->attachTable->handle(new AttachTableData(
                    tableSessionId: $this->timIdLuotKhach((string) $op->payload['table_session_uuid']),
                    diningTableId: (int) $op->payload['dining_table_id'],
                    attachedByUserId: $data->receivedByUserId,
                ))),
                OperationType::DetachTable => $this->xuLyGoiActionDon($op, $data, fn () => $this->detachTable->handle(new DetachTableData(
                    tableSessionId: $this->timIdLuotKhach((string) $op->payload['table_session_uuid']),
                    diningTableId: (int) $op->payload['dining_table_id'],
                ))),
                OperationType::PlaceOrder => $this->xuLyPlaceOrder($op, $data),
                OperationType::SendToKitchen => $this->xuLySendToKitchen($op, $data),
                OperationType::CancelOrderItem => $this->xuLyCancelOrderItem($op, $data),
                OperationType::RecordPayment => $this->xuLyRecordPayment($op, $data),
                OperationType::CloseSession => $this->xuLyGoiActionDon($op, $data, fn () => $this->closeTableSession->handle(new CloseTableSessionData(
                    tableSessionId: $this->timIdLuotKhach((string) $op->payload['table_session_uuid']),
                    closedByUserId: $data->receivedByUserId,
                ))),
            };
        } catch (ThaoTacGocKhongTimThayException) {
            return $this->xuLyThieuThaoTacGoc($op, $data);
        }
    }

    // ── open_session (dòng 2) ───────────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyOpenSession(SyncOperationData $op, SyncBatchData $data): array
    {
        $uuid = (string) $op->payload['uuid'];

        $daCo = TableSession::query()->where('uuid', $uuid)->first();
        if ($daCo !== null) {
            return $this->ketQuaDuplicate($op, ['table_session_id' => $daCo->id, 'code' => $daCo->code]);
        }

        $idBan = array_map('intval', $op->payload['dining_table_ids']);
        $banDangBiChiem = TableSessionTable::query()
            ->whereIn('dining_table_id', $idBan)
            ->whereNull('detached_at')
            ->exists();

        if ($banDangBiChiem) {
            $conflict = $this->taoConflict(
                op: $op,
                data: $data,
                kind: ConflictKind::HaiMayMoBan,
                autoAction: 'khong_lam_gi',
                messageVi: "Máy POS số {$data->deviceId} mở bàn lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng bàn này đã thuộc một lượt khách khác. Tem bếp có thể đã in — bếp đang nấu.",
                options: [
                    ['key' => 'gop', 'label' => 'Gộp — chuyển các món của máy này vào lượt khách đang giữ bàn'],
                    ['key' => 'tach', 'label' => 'Tách — chọn bàn khác cho lượt khách này, dựng lại từ đầu'],
                ],
                tableSessionId: null,
                isUrgent: true,
            );

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
        }

        $luotKhach = $this->openTableSession->handle(new OpenTableSessionData(
            uuid: $uuid,
            diningTableIds: $idBan,
            primaryDiningTableId: (int) $op->payload['primary_dining_table_id'],
            guestCount: (int) $op->payload['guest_count'],
            openedByUserId: $data->receivedByUserId,
        ));

        return $this->ketQuaApplied($op, $data, ['table_session_id' => $luotKhach->id, 'code' => $luotKhach->code]);
    }

    // ── place_order (dòng 6, 8, 9) ──────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyPlaceOrder(SyncOperationData $op, SyncBatchData $data): array
    {
        $uuid = (string) $op->payload['uuid'];

        $daCo = Order::query()->where('uuid', $uuid)->first();
        if ($daCo !== null) {
            return $this->ketQuaDuplicate($op, ['order_id' => $daCo->id]);
        }

        $session = TableSession::query()->where('uuid', (string) $op->payload['table_session_uuid'])->first();
        if ($session === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        if ($session->status === TableSessionStatus::Closed) {
            $conflict = $this->taoConflict(
                op: $op,
                data: $data,
                kind: ConflictKind::LuotDaDong,
                autoAction: 'khong_lam_gi',
                messageVi: "Máy POS số {$data->deviceId} gọi món lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng lượt khách {$session->code} đã thanh toán xong.",
                options: [
                    ['key' => 'mo_luot_moi', 'label' => 'Mở lượt khách mới cho các món này'],
                    ['key' => 'huy_mon', 'label' => 'Huỷ các món này — gọi nhầm'],
                ],
                tableSessionId: $session->id,
                isUrgent: true,
            );

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
        }

        if ($session->status === TableSessionStatus::Void) {
            $conflict = $this->taoConflict(
                op: $op,
                data: $data,
                kind: ConflictKind::LuotDaHuy,
                autoAction: 'khong_lam_gi',
                messageVi: "Máy POS số {$data->deviceId} gọi món lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng lượt khách {$session->code} đã bị huỷ.",
                options: [
                    ['key' => 'mo_luot_moi', 'label' => 'Mở lượt khách mới cho các món này'],
                    ['key' => 'huy_mon', 'label' => 'Huỷ các món này'],
                ],
                tableSessionId: $session->id,
                isUrgent: true,
            );

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
        }

        $giaLech = false;
        $items = [];
        foreach ($op->payload['items'] as $itemPayload) {
            $variant = ProductVariant::query()->find((int) $itemPayload['product_variant_id']);
            if ($variant !== null && isset($itemPayload['client_unit_price']) && (int) $itemPayload['client_unit_price'] !== $variant->price) {
                $giaLech = true;
            }

            $options = [];
            foreach ($itemPayload['options'] ?? [] as $optionPayload) {
                $options[] = new PlaceOrderItemOptionData(
                    uuid: (string) $optionPayload['uuid'],
                    optionId: (int) $optionPayload['option_id'],
                );
            }

            $items[] = new PlaceOrderItemData(
                uuid: (string) $itemPayload['uuid'],
                productId: (int) $itemPayload['product_id'],
                productVariantId: (int) $itemPayload['product_variant_id'],
                quantity: (int) $itemPayload['quantity'],
                note: $itemPayload['note'] ?? null,
                options: $options,
            );
        }

        $order = $this->placeOrder->handle(new PlaceOrderData(
            uuid: $uuid,
            tableSessionId: $session->id,
            items: $items,
            note: null,
            createdByUserId: $data->receivedByUserId,
        ));

        if ($giaLech) {
            $this->taoConflict(
                op: $op,
                data: $data,
                kind: ConflictKind::GiaLech,
                autoAction: 'dung_gia_server',
                messageVi: "Máy POS số {$data->deviceId} gọi món lúc {$op->occurredAt->format('H:i')} với giá đã thấy lúc offline khác giá hiện tại. Đã ghi theo giá hiện tại — phiếu tạm tính in offline có thể mang giá cũ.",
                options: [
                    ['key' => 'giu_gia_moi', 'label' => 'Giữ giá mới — đúng bảng giá hiện tại'],
                    ['key' => 'giam_gia_bu', 'label' => 'Giảm giá bù đúng phần chênh — giữ đúng phiếu đã đưa khách'],
                ],
                tableSessionId: $session->id,
                isUrgent: false,
            );
        }

        return $this->ketQuaApplied($op, $data, ['order_id' => $order->id]);
    }

    // ── send_to_kitchen ──────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLySendToKitchen(SyncOperationData $op, SyncBatchData $data): array
    {
        $order = Order::query()->where('uuid', (string) $op->payload['order_uuid'])->first();
        if ($order === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        return $this->xuLyGoiActionDon($op, $data, fn () => $this->sendToKitchen->handle(new SendToKitchenData(orderId: $order->id)));
    }

    // ── cancel_order_item ────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyCancelOrderItem(SyncOperationData $op, SyncBatchData $data): array
    {
        $item = OrderItem::query()->where('uuid', (string) $op->payload['order_item_uuid'])->first();
        if ($item === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        return $this->xuLyGoiActionDon($op, $data, fn () => $this->cancelOrderItem->handle(new CancelOrderItemData(
            orderId: $item->order_id,
            orderItemId: $item->id,
            quantity: (int) $op->payload['quantity'],
            reason: (string) $op->payload['reason'],
            cancelledByUserId: $data->receivedByUserId,
            approverUserId: null,
            approverPin: null,
            newItemUuid: $op->payload['new_item_uuid'] ?? null,
            optionUuids: $op->payload['option_uuids'] ?? [],
        )));
    }

    // ── record_payment (dòng 4a/4b/5a/5b/7) ──────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyRecordPayment(SyncOperationData $op, SyncBatchData $data): array
    {
        $uuid = (string) $op->payload['uuid'];

        $daCo = Payment::query()->where('uuid', $uuid)->first();
        if ($daCo !== null) {
            return $this->ketQuaDuplicate($op, ['payment_id' => $daCo->id]);
        }

        $session = TableSession::query()->with('shift')->where('uuid', (string) $op->payload['table_session_uuid'])->first();
        if ($session === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        $amount = (int) $op->payload['amount'];
        $daThu = $session->paid_amount;
        $conThieu = $session->total_amount - $daThu;

        if ($amount > $conThieu) {
            $conflict = $this->taoConflictThuVuot($op, $data, $session, $amount, $daThu, $conThieu);

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
        }

        // RecordPayment tra CA CỦA CHÍNH LƯỢT KHÁCH NÀY (tableSession->shift_id),
        // không tự lấy "ca đang mở bất kỳ" — nên khi ca gốc đã đóng, nó luôn từ
        // chối, bất kể có ca khác đang mở hay không. Việc "gán sang ca đang mở"
        // là quyết định của người (dòng 7 luôn cần người), không phải việc
        // SyncBatch tự làm — không có Action nào đổi shift_id để gọi ở đây.
        try {
            $payment = $this->recordPayment->handle(new RecordPaymentData(
                uuid: $uuid,
                tableSessionId: $session->id,
                method: PaymentMethod::Cash,
                amount: Money::fromInt($amount),
                tenderedAmount: isset($op->payload['tendered_amount']) ? Money::fromInt((int) $op->payload['tendered_amount']) : null,
                reference: null,
                receivedByUserId: $data->receivedByUserId,
            ));
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Ca của lượt khách này đã đóng')) {
                $conflict = $this->taoConflict(
                    op: $op,
                    data: $data,
                    kind: ConflictKind::CaDaDong,
                    autoAction: 'khong_lam_gi',
                    messageVi: "Máy POS số {$data->deviceId} thu {$this->tien($amount)} lúc {$op->occurredAt->format('H:i')} khi mất mạng, thuộc ca {$session->shift?->code} đã đóng. Tiền chưa được ghi nhận vào két ca nào.",
                    options: [
                        ['key' => 'gan_ca_dang_mo', 'label' => 'Gán tiền này vào ca đang mở hiện tại'],
                        ['key' => 'cho_mo_ca_moi', 'label' => 'Chờ — mở ca rồi xử lý lại phiếu này'],
                    ],
                    tableSessionId: $session->id,
                    isUrgent: false,
                );

                return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
            }

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Rejected->value, 'reason' => $e->getMessage()];
        }

        return $this->ketQuaApplied($op, $data, ['payment_id' => $payment->id]);
    }

    /**
     * Dòng 4/5 tách bốn — sửa 05/08 sau kiểm toán: bản cũ suy đoán nguyên nhân
     * chỉ bằng `paid_amount > 0`, sai trong hai tình huống thật:
     *  - `paid_amount > 0` KHÔNG có nghĩa là thu trùng — có thể là đã thu MỘT
     *    PHẦN ở nơi khác (VD bill 500k, đã thu 200k, offline lại thu 400k).
     *  - `paid_amount = 0` KHÔNG có nghĩa là đã giảm giá — tổng có thể giảm vì
     *    HUỶ MÓN, không phải khuyến mãi/giảm giá.
     * Không suy đoán bừa: đọc đủ dữ liệu thật (phanLoaiThuVuot()) rồi mới
     * chọn loại xung đột, và câu thông báo chỉ nêu NGUYÊN NHÂN CÓ CĂN CỨ.
     */
    private function taoConflictThuVuot(
        SyncOperationData $op,
        SyncBatchData $data,
        TableSession $session,
        int $amount,
        int $daThu,
        int $conThieu,
    ): SyncConflict {
        ['kind' => $kind, 'monHuySauDo' => $monHuySauDo] = $this->phanLoaiThuVuot($session, $amount, $daThu, $op->occurredAt);

        $chenhLech = $amount - $conThieu;

        $thongBao = "Máy POS số {$data->deviceId} thu {$this->tien($amount)} lúc {$op->occurredAt->format('H:i')} khi mất mạng.\n"
            ."Lượt khách {$session->code}: tổng phải trả {$this->tien($session->total_amount)}, đã thu {$this->tien($daThu)}, còn thiếu {$this->tien($conThieu)}.\n"
            ."Phiếu thu này nhiều hơn phần còn thiếu {$this->tien($chenhLech)}.";

        $thongBao .= match ($kind) {
            ConflictKind::ThuTienTrung => "\nBàn này đã thu đủ {$this->tien($daThu)} trước đó — nghi đây là thu lại đúng khoản đã thu.",
            ConflictKind::ThuMotPhanVuot => "\nBàn này đã thu {$this->tien($daThu)} ở nơi khác trước đó — có thể là phần khác của cùng hoá đơn, cộng lại vượt tổng phải trả.",
            ConflictKind::ThuVuotGiamGia => "\nBàn này đã được giảm giá {$this->tien($session->discount_amount)} lúc {$session->updated_at->format('H:i')}.",
            ConflictKind::ThuVuotTongDoiKhac => $monHuySauDo->isNotEmpty()
                ? "\nTổng bàn này đã đổi vì có món bị huỷ sau đó: ".$monHuySauDo
                    ->map(fn (OrderItem $m) => "{$m->product_name} lúc {$m->cancelled_at->format('H:i')}")
                    ->implode(', ').'.'
                : "\nTổng bàn này đã đổi vì lý do khác, không xác định được từ dữ liệu — kiểm tra kỹ trước khi chọn phương án.",
            default => '',
        };

        $luaChonBoPhieu = fn (string $key) => [
            'key' => $key,
            'label' => "Bỏ phiếu này — chọn cách này thì {$this->tien($amount)} tiền mặt đã nhận sẽ không được ghi vào hệ thống. Chỉ chọn khi chắc chắn khách chưa đưa tiền.",
        ];

        $luaChon = match ($kind) {
            ConflictKind::ThuTienTrung => [
                ['key' => 'ket_khong_thua', 'label' => 'Két không thừa — thu trùng, bỏ phiếu này'],
                ['key' => 'ket_co_thua', 'label' => 'Két có thừa — khách trả hai lần thật, ghi nhận rồi hoàn lại'],
            ],
            ConflictKind::ThuMotPhanVuot => [
                $luaChonBoPhieu('bo_phieu'),
                ['key' => 'ghi_nhan_hoan_phan_thua', 'label' => 'Ghi nhận đúng phần còn thiếu, hoàn lại phần thừa cho khách'],
            ],
            ConflictKind::ThuVuotGiamGia => [
                ['key' => 'thu_du_hoan_phan_thua', 'label' => 'Thu đúng tổng mới, hoàn lại phần thừa'],
                ['key' => 'bo_giam_gia', 'label' => 'Bỏ giảm giá, thu đủ số tiền máy POS đã ghi'],
            ],
            ConflictKind::ThuVuotTongDoiKhac => [
                $luaChonBoPhieu('bo_phieu'),
                ['key' => 'thu_du_hoan_phan_thua', 'label' => 'Thu đúng tổng hiện tại, hoàn lại phần thừa'],
            ],
            default => [],
        };

        return $this->taoConflict(
            op: $op,
            data: $data,
            kind: $kind,
            autoAction: 'khong_lam_gi',
            messageVi: $thongBao,
            options: $luaChon,
            tableSessionId: $session->id,
            isUrgent: false,
        );
    }

    /**
     * Đọc đủ ba thứ trước khi quyết định loại xung đột — không suy đoán:
     *  1. Đã có phiếu thu `completed` nào chưa, tổng bao nhiêu (đọc thẳng
     *     `paid_amount` — theo bất biến T5, cột này LUÔN bằng đúng tổng các
     *     phiếu thu chưa bị huỷ, không cần tự sum lại bảng `payments`).
     *  2. `discount_amount` hiện tại có > 0 không.
     *  3. Có `order_item` nào bị huỷ SAU thời điểm `occurred_at` của thao tác
     *     này không — chỉ dùng để nêu nguyên nhân CÓ CĂN CỨ cho
     *     ThuVuotTongDoiKhac, không đổi cách phân loại.
     *
     * @return array{kind: ConflictKind, monHuySauDo: Collection<int, OrderItem>}
     */
    private function phanLoaiThuVuot(TableSession $session, int $amount, int $daThu, CarbonImmutable $occurredAt): array
    {
        if ($daThu > 0) {
            return [
                'kind' => $amount === $daThu ? ConflictKind::ThuTienTrung : ConflictKind::ThuMotPhanVuot,
                'monHuySauDo' => collect(),
            ];
        }

        if ($session->discount_amount > 0) {
            return ['kind' => ConflictKind::ThuVuotGiamGia, 'monHuySauDo' => collect()];
        }

        $monHuySauDo = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('table_session_id', $session->id))
            ->where('status', OrderItemStatus::Cancelled)
            ->where('cancelled_at', '>', $occurredAt)
            ->orderByDesc('cancelled_at')
            ->get();

        return ['kind' => ConflictKind::ThuVuotTongDoiKhac, 'monHuySauDo' => $monHuySauDo];
    }

    private function tien(int $soTien): string
    {
        return Money::fromInt($soTien)->format();
    }

    // ── dòng 10: thiếu thao tác gốc ─────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyThieuThaoTacGoc(SyncOperationData $op, SyncBatchData $data): array
    {
        $khoaDem = "sync_deferred_count:{$op->opUuid}";
        $soLan = Cache::increment($khoaDem);

        if ($soLan < self::SO_LAN_DEFER_TOI_DA) {
            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Deferred->value, 'reason' => 'Chưa tìm thấy dữ liệu gốc mà thao tác này cần — thử lại ở gói sau.'];
        }

        Cache::forget($khoaDem);

        $conflict = $this->taoConflict(
            op: $op,
            data: $data,
            kind: ConflictKind::ThieuThaoTacGoc,
            autoAction: 'khong_lam_gi',
            messageVi: "Máy POS số {$data->deviceId} gửi lên một thao tác tham chiếu tới dữ liệu không tìm thấy trong hệ thống, sau {$soLan} lần thử. Có thể dữ liệu trên máy đó bị mất một phần.",
            options: [
                ['key' => 'tao_luot_moi', 'label' => 'Tạo lượt khách/dữ liệu mới cho thao tác này'],
                ['key' => 'bo_qua', 'label' => 'Bỏ qua thao tác này'],
            ],
            tableSessionId: null,
            isUrgent: false,
        );

        return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
    }

    // ── tiện ích chung ───────────────────────────────────────────────────

    /** Gọi một Action không có ma trận xung đột riêng — attach/detach/close/send/cancel. */
    private function xuLyGoiActionDon(SyncOperationData $op, SyncBatchData $data, Closure $goi): array
    {
        try {
            $ketQua = $goi();
        } catch (DomainException $e) {
            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Rejected->value, 'reason' => $e->getMessage()];
        }

        return $this->ketQuaApplied($op, $data, ['id' => $ketQua->id]);
    }

    /**
     * Ghi vào sổ cái chống trùng op_uuid NGAY SAU KHI Action chạy thành công
     * — chốt chặn cho việc gửi lại một thao tác ĐÃ áp dụng xong nhưng máy
     * POS không kịp nhận response (mạng rớt đúng lúc trả kết quả). Không ghi
     * ngay trong transaction của Action (mỗi Action giữ transaction riêng
     * theo mục 7.3, SyncBatch không được bọc thêm) — ghi ngay sau, không có
     * việc gì khác chen giữa hai bước này.
     *
     * @return array<string, mixed>
     */
    private function ketQuaApplied(SyncOperationData $op, SyncBatchData $data, array $serverIds): array
    {
        SyncAppliedOp::query()->create([
            'op_uuid' => $op->opUuid,
            'op_type' => $op->type->value,
            'device_id' => $data->deviceId,
            'result_payload' => ['server_ids' => $serverIds],
            'applied_at' => now(),
        ]);

        return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Applied->value, 'server_ids' => $serverIds];
    }

    /** @return array<string, mixed> */
    private function ketQuaDuplicate(SyncOperationData $op, array $serverIds): array
    {
        return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Duplicate->value, 'server_ids' => $serverIds];
    }

    /**
     * Dọn bớt sổ tay chống trùng op_uuid (`sync_applied_ops`) cũ hơn
     * SO_NGAY_GIU_SYNC_APPLIED_OP ngày — sổ này không phải dữ liệu giao dịch
     * (không thuộc luật CLAUDE.md mục 13), chỉ cần giữ đủ lâu để một gói cũ
     * gửi lại (máy POS mất mạng/khởi động lại) vẫn nhận đúng "duplicate".
     *
     * Chỉ chạy khi gói này thật sự CÓ áp dụng gì đó — gói toàn "duplicate"/
     * "conflict"/"rejected"/"deferred" thì bỏ qua, đỡ một lượt truy vấn
     * DELETE vô ích cho những gói không tạo thêm gì cần dọn.
     *
     * Giới hạn 1000 dòng mỗi lần — gói đồng bộ không phải lúc nào cũng phải
     * dọn hết nợ tồn đọng ngay, dọn dần qua nhiều gói vẫn đúng, miễn không
     * làm chậm gói đang xử lý. Lỗi ở đây CHỈ ghi log, không ném lên — một gói
     * đồng bộ đã áp dụng xong không được phép báo lỗi vì việc dọn dẹp phụ.
     *
     * @param  list<array<string, mixed>>  $ketQua
     */
    private function donDepSyncAppliedOpsCu(array $ketQua): void
    {
        $coApDung = collect($ketQua)->contains(
            fn (array $kq) => $kq['status'] === OperationStatus::Applied->value
        );

        if (! $coApDung) {
            return;
        }

        try {
            SyncAppliedOp::query()
                ->where('applied_at', '<', now()->subDays(self::SO_NGAY_GIU_SYNC_APPLIED_OP))
                ->limit(self::SO_DONG_XOA_TOI_DA_MOI_LAN)
                ->delete();
        } catch (Throwable $e) {
            Log::warning('Dọn sync_applied_ops cũ thất bại — không ảnh hưởng gói đồng bộ vừa xử lý.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function timIdLuotKhach(string $uuid): int
    {
        $session = TableSession::query()->where('uuid', $uuid)->first();
        if ($session === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        return $session->id;
    }

    /** Gom một thao tác con vào cụm của thao tác gốc đã bị conflict (mục 5.0). */
    private function gomVaoCum(int $conflictId, SyncOperationData $op): void
    {
        $conflict = SyncConflict::query()->find($conflictId);
        if ($conflict === null) {
            return;
        }

        $payload = $conflict->payload;
        $payload['cum'][] = $this->opThanhMang($op);
        $conflict->update(['payload' => $payload]);
    }

    /** @return array<string, mixed> */
    private function opThanhMang(SyncOperationData $op): array
    {
        return [
            'op_uuid' => $op->opUuid,
            'type' => $op->type->value,
            'occurred_at' => $op->occurredAt->toIso8601String(),
            'depends_on' => $op->dependsOn,
            'payload' => $op->payload,
        ];
    }

    /** @param list<array<string, mixed>> $options */
    private function taoConflict(
        SyncOperationData $op,
        SyncBatchData $data,
        ConflictKind $kind,
        ?string $autoAction,
        string $messageVi,
        array $options,
        ?int $tableSessionId,
        bool $isUrgent,
    ): SyncConflict {
        // Gửi lại đúng thao tác đã ghi xung đột (mạng rớt lúc trả kết quả,
        // hoặc chính op này là con trong một cụm đã xử lý ở gói trước) —
        // trả về đúng bản ghi cũ, không tạo bản ghi chờ thứ hai
        // (uq_sync_conflicts_op sẽ chặn INSERT trùng nếu không kiểm trước).
        $daCo = SyncConflict::query()->where('op_uuid', $op->opUuid)->first();
        if ($daCo !== null) {
            return $daCo;
        }

        return SyncConflict::query()->create([
            'op_uuid' => $op->opUuid,
            'batch_uuid' => $data->batchUuid,
            'device_id' => $data->deviceId,
            'op_type' => $op->type->value,
            'conflict_kind' => $kind->value,
            'is_urgent' => $isUrgent,
            'occurred_at' => $op->occurredAt,
            'received_at' => now(),
            'payload' => ['goc' => $this->opThanhMang($op), 'cum' => []],
            'server_state' => $this->trangThaiServerHienTai($tableSessionId),
            'auto_action' => $autoAction,
            'message_vi' => $messageVi,
            'options' => $options,
            'table_session_id' => $tableSessionId,
            'status' => ConflictStatus::Pending,
        ]);
    }

    /** @return array<string, mixed> */
    private function trangThaiServerHienTai(?int $tableSessionId): array
    {
        if ($tableSessionId === null) {
            return [];
        }

        $session = TableSession::query()->find($tableSessionId);
        if ($session === null) {
            return [];
        }

        return [
            'status' => $session->status->value,
            'total_amount' => $session->total_amount,
            'paid_amount' => $session->paid_amount,
        ];
    }
}
