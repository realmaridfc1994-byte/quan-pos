<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Enums\PromotionAppliesTo;
use App\Domain\Billing\Enums\PromotionType;
use App\Domain\Billing\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * Bộ đếm tăng dần, KHÔNG dùng số ngẫu nhiên cho cột UNIQUE `code` — xem
     * luật CLAUDE.md mục 22.
     */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'code' => 'KMTEST'.str_pad((string) ++self::$sequence, 4, '0', STR_PAD_LEFT),
            'name' => 'Khuyến mãi diễn tập',
            'type' => PromotionType::Percent,
            'value' => 10,
            'min_order_amount' => null,
            'max_discount_amount' => null,
            'starts_at' => null,
            'ends_at' => null,
            'time_rules' => null,
            'applies_to' => PromotionAppliesTo::All,
            'target_id' => null,
            'max_usage' => null,
            'used_count' => 0,
            'is_active' => true,
        ];
    }

    public function percent(int $phanTram): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PromotionType::Percent,
            'value' => $phanTram,
        ]);
    }

    public function amount(int $soTien): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PromotionType::Amount,
            'value' => $soTien,
        ]);
    }

    /** @param array{days?: list<int>, from?: string, to?: string} $khungGio */
    public function happyHour(int $phanTram, array $khungGio): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PromotionType::HappyHour,
            'value' => $phanTram,
            'time_rules' => $khungGio,
        ]);
    }

    public function choDanhMuc(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => PromotionAppliesTo::Category,
            'target_id' => $categoryId,
        ]);
    }

    public function choMon(int $productId): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => PromotionAppliesTo::Product,
            'target_id' => $productId,
        ]);
    }

    public function ngungHoatDong(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
