<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Models\BaseModel;
use Database\Factories\ProductProfitDailyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * profit_amount do MySQL tự tính (GENERATED ALWAYS ... STORED) — cố tình
 * KHÔNG có trong $fillable, giống purchase_items.qty_base.
 */
final class ProductProfitDaily extends BaseModel
{
    /** @use HasFactory<ProductProfitDailyFactory> */
    use HasFactory;

    protected $table = 'product_profit_daily';

    protected static function newFactory(): ProductProfitDailyFactory
    {
        return ProductProfitDailyFactory::new();
    }

    protected $fillable = [
        'date',
        'product_id',
        'product_variant_id',
        'quantity_sold',
        'revenue_amount',
        'cost_amount',
        'qty_no_cost',
        'qty_not_served',
        'revenue_uncosted_amount',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity_sold' => 'integer',
            'revenue_amount' => 'integer',
            'cost_amount' => 'integer',
            'qty_no_cost' => 'integer',
            'qty_not_served' => 'integer',
            'revenue_uncosted_amount' => 'integer',
            'profit_amount' => 'integer',
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
