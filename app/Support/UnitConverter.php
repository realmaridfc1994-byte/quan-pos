<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Inventory\Models\Ingredient;
use InvalidArgumentException;

/**
 * Quy đổi số lượng nguyên liệu sang đơn vị gốc (gam, ml, lon, chai, cái).
 *
 * Chỉ quy đổi MỘT CẤP: đơn vị nhập (VD "Thùng") thẳng sang đơn vị gốc, đọc
 * factor từ đúng một dòng `ingredient_units`. KHÔNG bắc cầu qua đơn vị trung
 * gian (VD Thùng → Lon → ml) — nếu có nhiều cấp thì mỗi cấp phải có sẵn một
 * dòng `ingredient_units` ghi thẳng factor ra đơn vị gốc.
 *
 * Kết quả luôn là số nguyên đơn vị gốc — cùng lý do với tiền (CLAUDE.md mục
 * 4.7): không có nguyên liệu lẻ nửa gam trong kho. Làm tròn bằng round() —
 * làm tròn đến số nguyên gần nhất, giống App\Support\Money::withPercent —
 * không dùng floor()/ceil() để tránh thiên lệch một phía khi quy đổi lặp lại
 * nhiều lần.
 */
final class UnitConverter
{
    public function toBaseQty(Ingredient $ingredient, string $unitName, int|float $qty): int
    {
        if ($qty < 0) {
            throw new InvalidArgumentException("Số lượng không được âm: {$qty}");
        }

        $factor = $this->resolveFactor($ingredient, $unitName);

        return (int) round($qty * $factor);
    }

    private function resolveFactor(Ingredient $ingredient, string $unitName): int
    {
        if ($this->laDonViGoc($ingredient, $unitName)) {
            return 1;
        }

        $donVi = $ingredient->units()
            ->where('unit_name', $unitName)
            ->first();

        if ($donVi === null) {
            throw new InvalidArgumentException(
                "Không có đường quy đổi từ đơn vị \"{$unitName}\" sang đơn vị gốc ".
                "\"{$ingredient->base_unit->label()}\" của nguyên liệu \"{$ingredient->name}\"."
            );
        }

        return $donVi->factor;
    }

    private function laDonViGoc(Ingredient $ingredient, string $unitName): bool
    {
        return mb_strtolower($unitName) === mb_strtolower($ingredient->base_unit->value)
            || mb_strtolower($unitName) === mb_strtolower($ingredient->base_unit->label());
    }
}
