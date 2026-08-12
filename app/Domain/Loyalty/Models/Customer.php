<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Sổ khách quen — không xoá, nghỉ chơi thì tắt is_active (docs/schema.md PHẦN L, luật 14 CLAUDE.md).
 *
 * Sổ cái điểm thưởng (point_transactions/point_balances) đang HOÃN — xem
 * docs/viec-ton.md "Tích điểm thành viên — HOÃN (12/08/2026)". Model này vì
 * vậy CHƯA có quan hệ pointBalance()/pointTransactions() — thêm lại khi làm
 * tiếp, tham khảo branch park/loyalty-4a1.
 */
final class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected $fillable = [
        'phone',
        'name',
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
