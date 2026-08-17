<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Models\BaseModel;
use Database\Factories\IngredientUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IngredientUnit extends BaseModel
{
    /** @use HasFactory<IngredientUnitFactory> */
    use HasFactory;

    protected static function newFactory(): IngredientUnitFactory
    {
        return IngredientUnitFactory::new();
    }

    protected $fillable = [
        'ingredient_id',
        'unit_name',
        'factor',
        'is_purchase_default',
    ];

    protected function casts(): array
    {
        return [
            'factor' => 'integer',
            'is_purchase_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
