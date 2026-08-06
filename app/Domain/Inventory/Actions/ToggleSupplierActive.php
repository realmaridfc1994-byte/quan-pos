<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Supplier;

/** Bật/tắt một nhà cung cấp. Không xoá — chỉ ngừng dùng. */
final class ToggleSupplierActive
{
    public function handle(Supplier $supplier): Supplier
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return $supplier;
    }
}
