<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Đặt một biến thể làm mặc định của món.
 *
 * E2: mỗi món có đúng một biến thể mặc định — bỏ cờ mặc định của mọi biến
 * thể khác cùng món trước khi đặt cờ cho biến thể này, trong cùng một giao dịch.
 */
final class SetDefaultProductVariant
{
    public function handle(ProductVariant $variant): ProductVariant
    {
        return DB::transaction(function () use ($variant): ProductVariant {
            ProductVariant::query()
                ->where('product_id', $variant->product_id)
                ->where('id', '!=', $variant->id)
                ->update(['is_default' => false]);

            $variant->update(['is_default' => true]);

            return $variant;
        });
    }
}
