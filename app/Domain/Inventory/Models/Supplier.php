<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    protected $fillable = [
        'name',
        'phone',
        'address',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
