<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\IngredientBaseUnit;
use App\Models\BaseModel;
use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Ingredient extends BaseModel
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    protected static function newFactory(): IngredientFactory
    {
        return IngredientFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'base_unit',
        'category',
        'min_qty',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_unit' => IngredientBaseUnit::class,
            'min_qty' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<IngredientUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(IngredientUnit::class);
    }

    /** @return HasOne<StockBalance, $this> */
    public function stockBalance(): HasOne
    {
        return $this->hasOne(StockBalance::class);
    }
}
