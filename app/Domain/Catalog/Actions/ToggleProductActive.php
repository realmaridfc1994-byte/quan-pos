<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;

/** Bật/tắt một món. Ngưng bán một món không vi phạm bất biến nào ở tầng biến thể. */
final class ToggleProductActive
{
    public function handle(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);

        return $product;
    }
}
