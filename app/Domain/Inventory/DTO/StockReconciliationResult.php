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
     * @param  list<array{ingredient_id: int, ingredient_name: string, so_dong: int}>  $thieuGiaVon
     * @param  list<array{order_item_id: int, product_name: string, variant_name: string, table_session_code: string, closed_at: string}>  $quenBamXong
     * @param  list<array{stock_movement_id: int, ref_id: ?int, nguon: string, note: string, reason: string, acknowledged_by: string, acknowledged_at: string}>  $moCoiDaGhiChu
     * @param  list<array{purchase_item_id: int, purchase_id: int, purchase_code: string, ingredient_name: string, received_at: string}>  $thieuSoCaiNhapHang
     * @param  list<array{stock_movement_id: int, ref_id: ?int, nguon: string}>  $moCoiNhapHang
     * @param  list<array{stock_take_item_id: int, stock_take_id: int, ingredient_name: string, diff_qty: int, closed_at: string}>  $thieuSoCaiKiemKe
     * @param  list<array{stock_movement_id: int, ref_id: ?int, nguon: string}>  $moCoiKiemKe
     */
    public function __construct(
        public array $lechQty,
        public array $lechCost,
        public array $thieuSoCai,
        public array $soCaiMoCoi,
        public array $thieuGiaVon = [],
        public array $quenBamXong = [],
        public array $moCoiDaGhiChu = [],
        public array $thieuSoCaiNhapHang = [],
        public array $moCoiNhapHang = [],
        public array $thieuSoCaiKiemKe = [],
        public array $moCoiKiemKe = [],
    ) {}

    /**
     * CỐ Ý không tính vào đây: hai mục cảnh báo (thieuGiaVon, quenBamXong) và
     * dòng mồ côi ĐÃ có người xem xác nhận (moCoiDaGhiChu).
     *
     * Bán lúc tồn âm và bếp quên bấm xong là chuyện CẦN DỌN, không phải sổ
     * sách sai. Nếu tính vào thì lệnh đối soát đỏ gần như mỗi đêm, và người ta
     * sẽ quen với màu đỏ tới mức bỏ qua cả lệch thật — đúng cái mà đối soát
     * sinh ra để bắt.
     */
    public function sach(): bool
    {
        return $this->lechQty === []
            && $this->lechCost === []
            && $this->thieuSoCai === []
            && $this->soCaiMoCoi === []
            && $this->thieuSoCaiNhapHang === []
            && $this->moCoiNhapHang === []
            && $this->thieuSoCaiKiemKe === []
            && $this->moCoiKiemKe === [];
    }

    /** Có gì cần chủ quán dọn không — khác với "sổ sách có lệch không". */
    public function coCanhBao(): bool
    {
        return $this->thieuGiaVon !== [] || $this->quenBamXong !== [];
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
            'thieu_gia_von' => $this->thieuGiaVon,
            'quen_bam_xong' => $this->quenBamXong,
            'mo_coi_da_ghi_chu' => $this->moCoiDaGhiChu,
            'thieu_so_cai_nhap_hang' => $this->thieuSoCaiNhapHang,
            'mo_coi_nhap_hang' => $this->moCoiNhapHang,
            'thieu_so_cai_kiem_ke' => $this->thieuSoCaiKiemKe,
            'mo_coi_kiem_ke' => $this->moCoiKiemKe,
        ];
    }
}
