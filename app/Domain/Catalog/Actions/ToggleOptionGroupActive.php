<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\OptionGroup;

/** Bật/tắt một nhóm tùy chọn. */
final class ToggleOptionGroupActive
{
    public function handle(OptionGroup $optionGroup): OptionGroup
    {
        $optionGroup->update(['is_active' => ! $optionGroup->is_active]);

        return $optionGroup;
    }
}
