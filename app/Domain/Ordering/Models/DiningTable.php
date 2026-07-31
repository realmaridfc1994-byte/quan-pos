<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DiningTable extends Model
{
    protected $fillable = [
        'code',
        'name',
        'area',
        'seats',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<TableSessionTable, $this> */
    public function tableSessionTables(): HasMany
    {
        return $this->hasMany(TableSessionTable::class);
    }
}
