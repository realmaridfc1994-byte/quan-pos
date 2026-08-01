<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Category;

/** Bật/tắt một nhóm món. Không có nghiệp vụ đặc biệt nào ở tầng nhóm món. */
final class ToggleCategoryActive
{
    public function handle(Category $category): Category
    {
        $category->update(['is_active' => ! $category->is_active]);

        return $category;
    }
}
