<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Catalog\Models\Option;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItemOption extends Model
{
    protected $fillable = [
        'order_item_id',
        'option_id',
        'option_group_name',
        'option_name',
        'price_delta',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
        ];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Option, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }
}
