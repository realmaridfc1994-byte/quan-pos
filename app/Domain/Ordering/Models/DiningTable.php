<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Models\BaseModel;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DiningTable extends BaseModel
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected static function newFactory(): DiningTableFactory
    {
        return DiningTableFactory::new();
    }

    protected $fillable = [
        'code',
        'public_code',
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
