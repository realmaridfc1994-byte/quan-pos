<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ordering\Models\DiningTable;
use App\Support\MaBanCongKhai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    protected $model = DiningTable::class;

    /**
     * Bộ đếm tăng dần, KHÔNG dùng số ngẫu nhiên — xem luật CLAUDE.md mục 22.
     * Tiền tố `T` cố ý khác `B`/`S` mà indoorTable()/outsideTable() dùng, để
     * mã tự sinh không bao giờ đụng mã cố định ai đó truyền tay trong test.
     */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'code' => 'T'.str_pad((string) ++self::$sequence, 4, '0', STR_PAD_LEFT),
            // NGOẠI LỆ của luật 23 giống cột uuid: không gian giá trị 22 ký tự
            // chữ-số lớn tới mức trùng nhau không phải rủi ro thực tế, và đổi
            // sang bộ đếm sẽ phá đúng tính chất "không đoán được" mà cột này
            // sinh ra để có.
            'public_code' => MaBanCongKhai::sinh(),
            'name' => fake()->word(),
            'area' => 'Trong nhà',
            'seats' => 4,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function indoorTable(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => sprintf('B%02d', $number),
            'name' => "Bàn $number",
            'area' => 'Trong nhà',
            'seats' => 4,
            'sort_order' => $number,
        ]);
    }

    public function outsideTable(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => sprintf('S%02d', $number),
            'name' => "Bàn sân $number",
            'area' => 'Sân',
            'seats' => 6,
            'sort_order' => 8 + $number,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
