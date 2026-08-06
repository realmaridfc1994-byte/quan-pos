<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Ingredient;

/** Bật/tắt một nguyên liệu. Không xoá — chỉ ngừng dùng. */
final class ToggleIngredientActive
{
    public function handle(Ingredient $ingredient): Ingredient
    {
        $ingredient->update(['is_active' => ! $ingredient->is_active]);

        return $ingredient;
    }
}
