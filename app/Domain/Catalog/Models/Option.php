<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\OptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Option extends Model
{
    /** @use HasFactory<OptionFactory> */
    use HasFactory;

    protected static function newFactory(): OptionFactory
    {
        return OptionFactory::new();
    }

    protected $fillable = [
        'option_group_id',
        'name',
        'price_delta',
        'is_default',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<OptionGroup, $this> */
    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class);
    }
}
