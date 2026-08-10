<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

/**
 * Kết quả một lần đối soát sổ cái kho — Phase 3 Bước 9.
 */
final readonly class StockReconciliationResult
{
    /**
     * @param  list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}>  $lechQty
     * @param  list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}>  $lechCost
     * @param  list<array{order_item_id: int, product_name: string, variant_name: string, served_at: string}>  $thieuSoCai
     * @param  list<array{stock_movement_id: int, ref_id: ?int}>  $soCaiMoCoi
     */
    public function __construct(
        public array $lechQty,
        public array $lechCost,
        public array $thieuSoCai,
        public array $soCaiMoCoi,
    ) {}

    public function sach(): bool
    {
        return $this->lechQty === [] && $this->lechCost === [] && $this->thieuSoCai === [] && $this->soCaiMoCoi === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sach' => $this->sach(),
            'lech_qty' => $this->lechQty,
            'lech_cost' => $this->lechCost,
            'thieu_so_cai' => $this->thieuSoCai,
            'so_cai_mo_coi' => $this->soCaiMoCoi,
        ];
    }
}
