<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\CreatePurchaseData;
use App\Domain\Inventory\DTO\PurchaseLineData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\PurchaseItem;
use App\Exceptions\DomainException;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tạo một phiếu nhập hàng ở trạng thái draft — chưa đụng tới kho, chỉ ghi lại
 * dự định nhập. Kho chỉ thay đổi khi ReceivePurchase chạy.
 */
final class CreatePurchase
{
    public function handle(CreatePurchaseData $data): Purchase
    {
        if ($data->lines === []) {
            throw new DomainException('Phiếu nhập phải có ít nhất một dòng nguyên liệu.');
        }

        $this->kiemTraTrungNguyenLieu($data->lines);

        return DB::transaction(function () use ($data): Purchase {
            // Mã hiển thị cần ID tự tăng mới sinh đúng — ghi trước bằng mã tạm
            // (uuid, chắc chắn không trùng ai), có id thật rồi mới gán mã thật,
            // cùng một transaction. Cùng cách làm với OpenTableSession.
            $purchase = Purchase::query()->create([
                'code' => substr((string) Str::uuid(), 0, 30),
                'supplier_id' => $data->supplierId,
                'status' => PurchaseStatus::Draft,
                'total_cost' => 0,
                'note' => $data->note,
                'invoice_no' => $data->invoiceNo,
                'created_by_user_id' => $data->createdByUserId,
            ]);
            $purchase->update(['code' => $this->sinhMaPhieuNhap($purchase->id, $purchase->created_at)]);

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

            $purchase->update(['total_cost' => $tongTien->amount]);

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

    /**
     * `$createdAt` PHẢI là thời điểm tạo phiếu, KHÔNG PHẢI now() lúc hàm này
     * chạy — cùng lý do với sinhMaLuotKhach() của OpenTableSession.
     */
    private function sinhMaPhieuNhap(int $id, Carbon $createdAt): string
    {
        $ngay = $createdAt->format('Ymd');

        return "NH-{$ngay}-".str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
}
