<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

/**
 * Loại hao hụt — chỉ tồn tại ở tầng code, KHÔNG có cột DB riêng
 * (docs/schema.md chốt stock_movements.type chỉ có 7 giá trị cố định, không
 * đổi schema ở Bước 6). WriteOffStock mã hoá giá trị này thành tiền tố của
 * cột reason (VARCHAR 255 có sẵn) — xem WriteOffStock::ghepLyDo().
 */
enum WasteReasonCategory: string
{
    case Broken = 'vo_hong';
    case Expired = 'het_han';
    case NaturalLoss = 'hao_hut_tu_nhien';
    case InternalUse = 'dung_noi_bo';

    public function label(): string
    {
        return match ($this) {
            self::Broken => 'Vỡ/hỏng',
            self::Expired => 'Hết hạn',
            self::NaturalLoss => 'Hao hụt tự nhiên',
            self::InternalUse => 'Dùng nội bộ',
        };
    }
}
