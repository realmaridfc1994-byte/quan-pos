<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Option;

/** Bật/tắt một tùy chọn cụ thể. */
final class ToggleOptionActive
{
    public function handle(Option $option): Option
    {
        $option->update(['is_active' => ! $option->is_active]);

        return $option;
    }
}
