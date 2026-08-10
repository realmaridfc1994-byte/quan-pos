<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng phiếu nhập. qty_base và line_cost do MySQL tự tính (GENERATED
 * ALWAYS ... STORED) — cố tình KHÔNG có trong $fillable, xem
 * tests/Feature/Database/GeneratedColumnsTest.php.
 */
final class PurchaseItem extends Model
{
    /** @use HasFactory<PurchaseItemFactory> */
    use HasFactory;

    protected static function newFactory(): PurchaseItemFactory
    {
        return PurchaseItemFactory::new();
    }

    protected $fillable = [
        'purchase_id',
        'ingredient_id',
        'unit_name',
        'qty_input',
        'factor_snapshot',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'qty_input' => 'integer',
            'factor_snapshot' => 'integer',
            'qty_base' => 'integer',
            'unit_cost' => 'integer',
            'line_cost' => 'integer',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
