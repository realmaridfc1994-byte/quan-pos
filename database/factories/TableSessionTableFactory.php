<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSessionTable>
 */
class TableSessionTableFactory extends Factory
{
    protected $model = TableSessionTable::class;

    public function definition(): array
    {
        return [
            'table_session_id' => TableSession::factory(),
            'dining_table_id' => DiningTable::factory(),
            'is_primary' => true,
            'attached_at' => now(),
            'detached_at' => null,
            'attached_by_user_id' => User::factory(),
        ];
    }

    public function notPrimary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => false]);
    }

    public function detached(): static
    {
        return $this->state(fn (array $attributes) => ['detached_at' => now()]);
    }
}
