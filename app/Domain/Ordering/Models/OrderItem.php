<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Staffing\Models\User;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'unit_price',
        'options_amount',
        'quantity',
        'status',
        'served_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancel_reason',
        'split_from_item_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderItemStatus::class,
            'unit_price' => 'integer',
            'options_amount' => 'integer',
            'quantity' => 'integer',
            'line_amount' => 'integer',
            'served_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @return BelongsTo<self, $this> */
    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_item_id');
    }

    /** @return HasMany<self, $this> */
    public function splitInto(): HasMany
    {
        return $this->hasMany(self::class, 'split_from_item_id');
    }

    /** @return HasMany<OrderItemOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
