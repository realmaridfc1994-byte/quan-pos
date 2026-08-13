<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Ordering\Models\OrderItem;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Kết quả của một lần soát "ngày hôm đó, dòng món nào chưa biết giá vốn".
 *
 * Đây là hình dạng dữ liệu dùng chung cho HAI việc tổng hợp (lãi gộp theo món
 * và tóm tắt ngày), để hai bảng báo cáo không bao giờ nói hai con số khác nhau
 * về cùng một ngày.
 */
final readonly class KetQuaThieuGiaVonNgay
{
    /**
     * @param  Collection<int, OrderItem>  $dongMon  Các dòng món tính vào doanh thu ngày đó
     * @param  array<int, int>  $doanhThuTheoDongMon  [order_item_id => doanh thu đã phân bổ giảm giá]
     * @param  array<int, int>  $giaVonTheoDongMon  [order_item_id => giá vốn tại thời điểm bán]
     * @param  list<int>  $idThieuGiaVon  Dòng bán lúc kho âm (has_cost = false)
     * @param  list<int>  $idChuaBungRa  Dòng bếp chưa bấm xong, ở lượt khách ĐÃ đóng
     */
    public function __construct(
        public Collection $dongMon,
        private array $doanhThuTheoDongMon,
        private array $giaVonTheoDongMon,
        public array $idThieuGiaVon,
        public array $idChuaBungRa,
    ) {}

    public function doanhThuCua(OrderItem $dong): int
    {
        return $this->doanhThuTheoDongMon[$dong->id] ?? $dong->line_amount;
    }

    public function giaVonCua(OrderItem $dong): int
    {
        return $this->giaVonTheoDongMon[$dong->id] ?? 0;
    }

    /**
     * Hai tập id KHÔNG BAO GIỜ giao nhau (K19): một dòng món hoặc có sổ cái kho
     * nhưng không định được giá (thuộc idThieuGiaVon), hoặc chưa có sổ cái nào
     * vì chưa phục vụ (thuộc idChuaBungRa) — gộp thẳng, không lo đếm trùng.
     *
     * @return list<int>
     */
    public function idKhongDangTin(): array
    {
        return array_values(array_unique([...$this->idThieuGiaVon, ...$this->idChuaBungRa]));
    }

    /** Tổng doanh thu theo dòng món của ngày — MẪU SỐ khi tính "bao nhiêu % thiếu giá vốn". */
    public function tongDoanhThu(): int
    {
        return $this->congDon($this->dongMon);
    }

    /** Phần doanh thu chưa xác định được giá vốn — TỬ SỐ của tỉ lệ trên. */
    public function tongDoanhThuThieuGiaVon(): int
    {
        $idKhongDangTin = $this->idKhongDangTin();

        return $this->congDon(
            $this->dongMon->filter(fn (OrderItem $dong): bool => in_array($dong->id, $idKhongDangTin, true))
        );
    }

    /** @param  Collection<int, OrderItem>  $dongMon */
    private function congDon(Collection $dongMon): int
    {
        return $dongMon
            ->reduce(
                fn (Money $tong, OrderItem $dong): Money => $tong->plus(Money::fromInt($this->doanhThuCua($dong))),
                Money::zero(),
            )
            ->amount;
    }
}
