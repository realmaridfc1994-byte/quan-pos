<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\PurchaseLineData;
use App\Domain\Inventory\DTO\UpdatePurchaseData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\PurchaseItem;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Sửa một phiếu nhập CHỈ khi còn ở trạng thái draft — chưa nhận hàng nên
 * chưa đụng tới kho, xoá hết dòng cũ và ghi lại dòng mới không ảnh hưởng gì.
 */
final class UpdatePurchase
{
    public function handle(UpdatePurchaseData $data): Purchase
    {
        if ($data->lines === []) {
            throw new DomainException('Phiếu nhập phải có ít nhất một dòng nguyên liệu.');
        }

        $this->kiemTraTrungNguyenLieu($data->lines);

        return DB::connection('tenant')->transaction(function () use ($data): Purchase {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($data->purchaseId);

            if ($purchase->status !== PurchaseStatus::Draft) {
                throw new DomainException('Phiếu nhập đã nhận hàng hoặc đã huỷ, không sửa được nữa.');
            }

            $purchase->items()->delete();

            $tongTien = Money::zero();

            foreach ($data->lines as $line) {
                $factorSnapshot = $this->layFactorSnapshot($line);

                PurchaseItem::query()->create([
                    'purchase_id' => $purchase->id,
                    'ingredient_id' => $line->ingredientId,
                    'unit_name' => $line->unitName,
                    'qty_input' => $line->qtyInput,
                    'factor_snapshot' => $factorSnapshot,
                    'unit_cost' => $line->unitCost,
                ]);

                $tongTien = $tongTien->plus(Money::fromInt($line->unitCost)->times($line->qtyInput));
            }

            $purchase->update([
                'supplier_id' => $data->supplierId,
                'note' => $data->note,
                'invoice_no' => $data->invoiceNo,
                'total_cost' => $tongTien->amount,
            ]);

            return $purchase;
        });
    }

    /**
     * @param  list<PurchaseLineData>  $lines
     */
    private function kiemTraTrungNguyenLieu(array $lines): void
    {
        $ingredientIds = array_map(fn (PurchaseLineData $line): int => $line->ingredientId, $lines);

        if (count($ingredientIds) !== count(array_unique($ingredientIds))) {
            throw new DomainException('Phiếu nhập có hai dòng cùng một nguyên liệu — gộp lại thành một dòng.');
        }
    }

    private function layFactorSnapshot(PurchaseLineData $line): int
    {
        $ingredient = Ingredient::query()->find($line->ingredientId);

        if ($ingredient === null) {
            throw new DomainException('Nguyên liệu không tồn tại.');
        }

        $donVi = IngredientUnit::query()
            ->where('ingredient_id', $line->ingredientId)
            ->where('unit_name', $line->unitName)
            ->first();

        if ($donVi === null) {
            throw new DomainException(
                "Đơn vị \"{$line->unitName}\" chưa được khai báo cho nguyên liệu \"{$ingredient->name}\". ".
                'Vào màn hình Nguyên liệu để thêm đơn vị quy đổi trước khi nhập hàng.'
            );
        }

        return $donVi->factor;
    }
}
