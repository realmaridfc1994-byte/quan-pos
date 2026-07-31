<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\Station;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Category extends Model
{
    protected $fillable = [
        'name',
        'station',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'station' => Station::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<OptionGroup, $this> */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(OptionGroup::class);
    }
}
