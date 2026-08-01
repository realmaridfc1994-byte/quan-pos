<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ProductVariant;
use App\Exceptions\DomainException;

/**
 * Bật/tắt một biến thể món.
 *
 * E1: mỗi món phải có ít nhất một biến thể đang bán — chặn tắt biến thể
 * đang bán cuối cùng của một món.
 */
final class ToggleProductVariantActive
{
    public function handle(ProductVariant $variant): ProductVariant
    {
        if ($variant->is_active) {
            $soBienTheDangBan = ProductVariant::query()
                ->where('product_id', $variant->product_id)
                ->where('is_active', true)
                ->count();

            if ($soBienTheDangBan <= 1) {
                throw new DomainException('Đây là biến thể cuối cùng đang bán của món này. Phải có ít nhất một biến thể đang bán.');
            }
        }

        $variant->update(['is_active' => ! $variant->is_active]);

        return $variant;
    }
}
