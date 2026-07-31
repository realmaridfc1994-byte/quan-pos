<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    protected $fillable = [
        'product_id',
        'name',
        'price',
        'is_default',
        'is_active',
        'sort_order',
        'tracks_inventory',
        'stock_unit',
        'stock_factor',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'tracks_inventory' => 'boolean',
            'stock_factor' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
