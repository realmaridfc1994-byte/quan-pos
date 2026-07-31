<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\Station;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Product extends Model
{
    protected $fillable = [
        'category_id',
        'code',
        'name',
        'description',
        'station_override',
        'is_active',
        'sort_order',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'station_override' => Station::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<OptionGroup, $this> */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(OptionGroup::class);
    }
}
