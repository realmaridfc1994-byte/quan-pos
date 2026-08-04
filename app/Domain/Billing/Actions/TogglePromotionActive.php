<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Promotion;

/**
 * Bật/tắt một khuyến mãi — KHÔNG xoá. Hoá đơn cũ vẫn trỏ đúng
 * `table_sessions.promotion_id` dù chương trình đã tắt.
 */
final class TogglePromotionActive
{
    public function handle(Promotion $promotion): Promotion
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return $promotion;
    }
}
