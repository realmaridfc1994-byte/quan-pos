<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Domain\Inventory\Models\Ingredient;
use App\Models\BaseModel;
use Database\Factories\IngredientWasteMonthlyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IngredientWasteMonthly extends BaseModel
{
    /** @use HasFactory<IngredientWasteMonthlyFactory> */
    use HasFactory;

    protected $table = 'ingredient_waste_monthly';

    protected static function newFactory(): IngredientWasteMonthlyFactory
    {
        return IngredientWasteMonthlyFactory::new();
    }

    protected $fillable = [
        'month',
        'ingredient_id',
        'waste_qty',
        'waste_cost',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'waste_qty' => 'integer',
            'waste_cost' => 'integer',
        ];
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
