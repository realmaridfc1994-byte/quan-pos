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
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

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
            return $lock->block(5, fn () => $this->apDungCaGoi($data));
        } catch (LockTimeoutException) {
            throw new SyncBatchLockedException('Một gói đồng bộ khác đang được xử lý. Thử lại sau ít giây.');
        }
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

    // ── record_payment (dòng 4, 5, 7) ────────────────────────────────────

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
        $remaining = $session->total_amount - $session->paid_amount;

        if ($amount > $remaining) {
            if ($session->paid_amount > 0) {
                $conflict = $this->taoConflict(
                    op: $op,
                    data: $data,
                    kind: ConflictKind::ThuTienTrung,
                    autoAction: 'khong_lam_gi',
                    messageVi: "Máy POS số {$data->deviceId} thu {$this->tien($amount)} lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng lượt khách {$session->code} đã được thu tiền ở nơi khác trước đó. Kiểm tra két: có thừa tiền không?",
                    options: [
                        ['key' => 'ket_khong_thua', 'label' => 'Két không thừa — thu trùng, bỏ phiếu này'],
                        ['key' => 'ket_co_thua', 'label' => 'Két có thừa — khách trả hai lần thật, ghi nhận rồi hoàn lại'],
                    ],
                    tableSessionId: $session->id,
                    isUrgent: false,
                );
            } else {
                $conflict = $this->taoConflict(
                    op: $op,
                    data: $data,
                    kind: ConflictKind::ThuVuotGiamGia,
                    autoAction: 'khong_lam_gi',
                    messageVi: "Máy POS số {$data->deviceId} thu {$this->tien($amount)} lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng bàn này đã được giảm giá trong lúc đó, tổng còn {$this->tien($session->total_amount)}.",
                    options: [
                        ['key' => 'thu_du_hoan_phan_thua', 'label' => 'Thu đúng tổng mới, hoàn lại phần thừa'],
                        ['key' => 'bo_giam_gia', 'label' => 'Bỏ giảm giá, thu đủ số tiền máy POS đã ghi'],
                    ],
                    tableSessionId: $session->id,
                    isUrgent: false,
                );
            }

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
