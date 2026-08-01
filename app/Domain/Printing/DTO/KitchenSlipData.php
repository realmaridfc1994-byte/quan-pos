<?php

declare(strict_types=1);

namespace App\Domain\Printing\DTO;

/**
 * Dữ liệu để in tem bếp.
 *
 * Tem bếp chứa những món của MỘT nơi làm (station) — các bếp khác nhau
 * hoặc các quầy pha chế khác nhau nhận tem riêng.
 */
final readonly class KitchenSlipData
{
    /** @param array<array{product_name: string, variant_name: string, quantity: int, note: ?string}> $items */
    public function __construct(
        public string $tableName,
        public string $orderTime,  // ISO 8601 hoặc định dạng "HH:mm"
        public string $staffName,  // Người gọi món
        public string $station,    // "Bếp" / "Quầy pha chế" / tên nơi làm
        public array $items,       // Danh sách món của station này
    ) {}
}
