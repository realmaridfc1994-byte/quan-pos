<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Huỷ một dòng món — toàn bộ hoặc một phần số lượng.
 *
 * H4: huỷ một phần (ví dụ 1 trong 5 lon) = tách dòng gốc thành phần còn giữ
 * lại (vẫn "ordered"/"served" như cũ) và một dòng MỚI mang đúng số lượng bị
 * huỷ, đã ở trạng thái "cancelled" ngay, ghi lại `split_from_item_id`. Tổng
 * số lượng hai dòng sau khi tách luôn bằng số lượng dòng gốc trước khi tách.
 * H1: không xoá dòng nào — huỷ toàn bộ cũng chỉ đổi status của chính dòng đó.
 * H2: bắt buộc đủ ai/lúc nào/vì sao mới ghi được (ck_order_items_cancel).
 * H3: dòng đã huỷ tự động không tính vào tạm tính (RecalculateSessionSubtotal
 * đã lọc status != cancelled từ Bước 4, không cần làm gì thêm ở đây).
 * H5: món đã "served" bắt buộc có PIN duyệt của chủ quán/thu ngân — gọi lại
 * VerifyApproverPin (Bước 1), không viết lại logic khoá/chống dò PIN.
 *
 * Huỷ MỘT PHẦN (H4) tạo ra một dòng order_items MỚI và sao chép các dòng
 * order_item_options của nó — cả hai loại bản ghi này nhận uuid do MÁY POS
 * gửi lên (Phase 2 Bước 2, giống PlaceOrder), vì bấm huỷ cũng là thao tác
 * nhân viên cần chống tạo trùng khi mạng lag/bấm hai lần.
 */
final class CancelOrderItem
{
    public function __construct(
        private readonly VerifyApproverPin $verifyApproverPin,
    ) {}

    public function handle(CancelOrderItemData $data): OrderItem
    {
        return DB::transaction(function () use ($data): OrderItem {
            $order = Order::query()->lockForUpdate()->findOrFail($data->orderId);
            $item = OrderItem::query()->where('order_id', $order->id)->lockForUpdate()->findOrFail($data->orderItemId);

            if ($item->status === OrderItemStatus::Cancelled) {
                throw new DomainException('Món này đã huỷ rồi.');
            }

            if ($data->quantity < 1 || $data->quantity > $item->quantity) {
                throw new DomainException("Số lượng huỷ không hợp lệ. Dòng này còn {$item->quantity}, không huỷ được {$data->quantity}.");
            }

            $lyDo = trim($data->reason);
            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do huỷ món.');
            }

            if ($item->status === OrderItemStatus::Served) {
                $this->duyetBangPin($data);
            }

            $dongDaHuy = $data->quantity === $item->quantity
                ? $this->huyToanBo($item, $data, $lyDo)
                : $this->tachVaHuyMotPhan($item, $data, $lyDo);

            app(RecalculateSessionSubtotal::class)->handle($order->tableSession);

            return $dongDaHuy;
        });
    }

    private function duyetBangPin(CancelOrderItemData $data): void
    {
        if ($data->approverUserId === null || $data->approverPin === null) {
            throw new DomainException('Món đã phục vụ ra bàn, phải có người duyệt bằng mã PIN mới huỷ được.');
        }

        $this->verifyApproverPin->handle(new PinVerifyData(
            userId: $data->approverUserId,
            pin: $data->approverPin,
            requestedByUserId: $data->cancelledByUserId,
        ));
    }

    private function huyToanBo(OrderItem $item, CancelOrderItemData $data, string $lyDo): OrderItem
    {
        $item->update([
            'status' => OrderItemStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $data->cancelledByUserId,
            'cancel_reason' => $lyDo,
        ]);

        return $item;
    }

    private function tachVaHuyMotPhan(OrderItem $item, CancelOrderItemData $data, string $lyDo): OrderItem
    {
        if ($data->newItemUuid === null) {
            throw new DomainException('Huỷ một phần phải kèm uuid do máy POS sinh cho dòng món mới.');
        }

        // Bấm huỷ hai lần vì mạng lag: trả lại đúng dòng đã tách lần đầu,
        // không trừ số lượng và không tách thêm lần nữa.
        $daTachRoi = OrderItem::query()->where('uuid', $data->newItemUuid)->first();
        if ($daTachRoi !== null) {
            return $daTachRoi;
        }

        $soLuongConLai = $item->quantity - $data->quantity;
        $item->update(['quantity' => $soLuongConLai]);

        $dongMoi = OrderItem::query()->create([
            'uuid' => $data->newItemUuid,
            'order_id' => $item->order_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'unit_price' => $item->unit_price,
            'options_amount' => $item->options_amount,
            'quantity' => $data->quantity,
            // Kế thừa served_at của dòng gốc: Phase 3 đọc cột này để biết nguyên
            // liệu đã dùng hay chưa — món đã bưng ra bàn (served_at có giá trị)
            // rồi mới bị trả một phần thì phần bị huỷ đó vẫn là phần ĐÃ DÙNG,
            // không phải chưa phục vụ. Để NULL ở đây sẽ hoàn kho nhầm một lon
            // bia đã uống (xem docs/kiem-toan-offline.md mục 5).
            'served_at' => $item->served_at,
            'status' => OrderItemStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $data->cancelledByUserId,
            'cancel_reason' => $lyDo,
            'split_from_item_id' => $item->id,
            'note' => $item->note,
        ]);

        foreach ($item->options as $tuyChon) {
            $uuidTuyChon = $data->optionUuids[$tuyChon->id] ?? null;
            if ($uuidTuyChon === null) {
                throw new DomainException("Thiếu uuid do máy POS gửi cho tuỳ chọn (dòng gốc id {$tuyChon->id}).");
            }

            $dongMoi->options()->create([
                'uuid' => $uuidTuyChon,
                'option_id' => $tuyChon->option_id,
                'option_group_name' => $tuyChon->option_group_name,
                'option_name' => $tuyChon->option_name,
                'price_delta' => $tuyChon->price_delta,
            ]);
        }

        // line_amount là cột database tự tính (M5) — bản ghi vừa tạo trong bộ nhớ
        // chưa có giá trị này, phải nạp lại từ CSDL mới đọc đúng.
        return $dongMoi->refresh();
    }
}
