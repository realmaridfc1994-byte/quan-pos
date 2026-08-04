<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Database\Factories\ProductSaleDailyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductSaleDaily extends Model
{
    /** @use HasFactory<ProductSaleDailyFactory> */
    use HasFactory;

    protected $table = 'product_sales_daily';

    protected static function newFactory(): ProductSaleDailyFactory
    {
        return ProductSaleDailyFactory::new();
    }

    protected $fillable = [
        'date',
        'product_id',
        'product_variant_id',
        'quantity_sold',
        'revenue_amount',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity_sold' => 'integer',
            'revenue_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
