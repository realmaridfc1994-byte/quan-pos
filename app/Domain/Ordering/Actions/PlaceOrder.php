<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\PlaceOrderItemData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Gửi một phiếu gọi món cho một lượt khách.
 *
 * M2/M3: uuid do máy POS sinh trước khi gửi — gửi lại đúng uuid đó trả về
 * đúng phiếu cũ, không tạo phiếu thứ hai (chống bấm "Gửi" hai lần vì mạng lag).
 * M4: mọi tên món/biến thể/tùy chọn/giá đều CHỤP LẠI vào order_items và
 * order_item_options tại đúng thời điểm gọi — sửa giá thực đơn sau đó không
 * làm đổi một chữ nào trên phiếu đã gửi.
 * M6: số lượng luôn >= 1 (DB backstop bằng ck_order_items_qty).
 * M7: một phiếu chỉ chứa món của một nơi làm — trộn bếp và quầy trong cùng
 * một lần gửi bị từ chối thẳng, máy POS phải tách thành hai lần gửi riêng.
 * M8: chỉ gọi món được khi lượt khách đang open.
 * M9: số tùy chọn chọn trong một nhóm phải nằm giữa min_select và max_select.
 */
final class PlaceOrder
{
    public function handle(PlaceOrderData $data): Order
    {
        if ($data->items === []) {
            throw new DomainException('Phải gọi ít nhất một món.');
        }

        return DB::transaction(function () use ($data): Order {
            $donCu = Order::query()->where('uuid', $data->uuid)->first();
            if ($donCu !== null) {
                return $donCu;
            }

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if ($tableSession->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách này không còn mở, không gọi món được nữa.');
            }

            $station = null;

            foreach ($data->items as $item) {
                if ($item->quantity < 1) {
                    throw new DomainException('Số lượng phải lớn hơn 0.');
                }

                $product = Product::query()->with('category')->findOrFail($item->productId);
                $variant = ProductVariant::query()->findOrFail($item->productVariantId);

                if ($variant->product_id !== $product->id) {
                    throw new DomainException("Biến thể không thuộc món {$product->name}.");
                }

                if (! $product->is_active || ! $variant->is_active) {
                    throw new DomainException("Món {$product->name} hiện không bán.");
                }

                $tramCuaMon = $product->effectiveStation();
                $station ??= $tramCuaMon;

                if ($station !== $tramCuaMon) {
                    throw new DomainException('Không thể gộp món bếp và món quầy trong một lần gửi. Tách thành hai lần gửi riêng.');
                }

                $this->kiemTraTuyChon($product, $item);
            }

            $soThuTu = Order::query()->where('table_session_id', $tableSession->id)->max('sequence_no') + 1;

            $order = Order::query()->create([
                'uuid' => $data->uuid,
                'table_session_id' => $tableSession->id,
                'sequence_no' => $soThuTu,
                'station' => $station,
                'status' => OrderStatus::Sent,
                'created_by_user_id' => $data->createdByUserId,
                'sent_at' => now(),
                'note' => $data->note,
            ]);

            foreach ($data->items as $item) {
                $this->taoDongMon($order, $item);
            }

            app(RecalculateSessionSubtotal::class)->handle($tableSession);

            return $order;
        });
    }

    private function kiemTraTuyChon(Product $product, PlaceOrderItemData $item): void
    {
        $nhomApDung = OptionGroup::query()
            ->where('is_active', true)
            ->where(function ($q) use ($product) {
                $q->where('product_id', $product->id)->orWhere('category_id', $product->category_id);
            })
            ->with('options')
            ->get();

        $idDaChon = collect($item->options)->pluck('optionId');

        $idHopLe = $nhomApDung->flatMap(fn (OptionGroup $n) => $n->options->pluck('id'));
        $idKhongHopLe = $idDaChon->diff($idHopLe);
        if ($idKhongHopLe->isNotEmpty()) {
            throw new DomainException("Có tùy chọn không áp dụng cho món {$product->name}.");
        }

        foreach ($nhomApDung as $nhom) {
            $soLuongChon = $idDaChon->intersect($nhom->options->pluck('id'))->count();

            if ($soLuongChon < $nhom->min_select || $soLuongChon > $nhom->max_select) {
                throw new DomainException("Chọn tùy chọn nhóm \"{$nhom->name}\" không hợp lệ cho món {$product->name}.");
            }
        }
    }

    private function taoDongMon(Order $order, PlaceOrderItemData $item): void
    {
        $product = Product::query()->findOrFail($item->productId);
        $variant = ProductVariant::query()->findOrFail($item->productVariantId);

        $options = $item->options === []
            ? collect()
            : Option::query()->with('optionGroup')->findMany(collect($item->options)->pluck('optionId'));

        $tienTuyChon = $options->reduce(
            fn (Money $tong, Option $tuyChon) => $tong->plus(Money::fromInt($tuyChon->price_delta)),
            Money::zero()
        );

        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'unit_price' => $variant->price,
            'options_amount' => $tienTuyChon->amount,
            'quantity' => $item->quantity,
            'status' => OrderItemStatus::Ordered,
            'note' => $item->note,
        ]);

        foreach ($options as $tuyChon) {
            $orderItem->options()->create([
                'option_id' => $tuyChon->id,
                'option_group_name' => $tuyChon->optionGroup->name,
                'option_name' => $tuyChon->name,
                'price_delta' => $tuyChon->price_delta,
            ]);
        }
    }
}
