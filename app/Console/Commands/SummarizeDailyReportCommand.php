<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reporting\Actions\SummarizeDailyReport;
use App\Domain\Reporting\Actions\SummarizeIngredientWasteMonthly;
use App\Domain\Reporting\Actions\SummarizeProductProfit;
use App\Domain\Reporting\Models\DailySummary;
use App\Exceptions\DomainException;
use App\Support\CauHinhQuan;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chạy tay lại việc tổng hợp báo cáo — Phase 2 Bước 8, mở rộng ở Phase 3 Bước 10.
 *
 * Chạy NGAY (không đẩy vào hàng đợi) để chủ dự án thấy kết quả tức thì khi gỡ
 * sự cố hoặc tổng hợp lại dữ liệu cũ.
 *
 * ── Gọi ĐỦ BA việc tổng hợp (trả nợ ghi ở docs/viec-ton.md 10/08) ──────────
 * Trước đây lệnh này chỉ gọi SummarizeDailyReport, nên lãi gộp và hao hụt chỉ
 * có số khi có người đóng ca thật hoặc gọi tay qua tinker. Giờ mỗi ngày chạy
 * cả SummarizeDailyReport + SummarizeProductProfit, và mỗi THÁNG chạm tới
 * chạy thêm SummarizeIngredientWasteMonthly một lần.
 *
 * ── Chạy lại theo khoảng ngày ─────────────────────────────────────────────
 *   php artisan report:summarize --tu=2026-06-01 --den=2026-08-09
 *
 * Chạy lại bao nhiêu lần cũng ra đúng một kết quả: cả ba Action đều xoá sạch
 * đúng ngày/tháng đang chạy rồi chép lại từ nguồn gốc (orders, order_items,
 * payments, stock_movements), không cộng dồn chồng lên số cũ.
 *
 * ── LÁ CHẮN: doanh thu ĐÃ CHỐT không được đổi ─────────────────────────────
 * Chạy lại chỉ chép lại các bảng tóm tắt, không ghi một chữ nào vào payments
 * hay order_items. Nhưng nếu sau ngày đó có phiếu thu bị huỷ (VoidPayment) thì
 * số tóm tắt cũ và số tính lại sẽ KHÁC NHAU — số mới đúng hơn, nhưng đó là
 * chuyện phải để chủ quán biết chứ không được đổi lặng lẽ. Nên mỗi ngày chạy
 * trong một giao dịch riêng: thấy doanh thu ngày đó đổi là QUAY LUI ngày đó và
 * DỪNG cả lệnh, trừ khi người chạy đã đọc con số và đồng ý bằng
 * --dong-y-doanh-thu-doi.
 */
final class SummarizeDailyReportCommand extends Command
{
    protected $signature = 'report:summarize
        {date? : Một ngày cần tổng hợp lại, định dạng YYYY-MM-DD — mặc định hôm nay}
        {--tu= : Ngày đầu khoảng cần tổng hợp lại, định dạng YYYY-MM-DD}
        {--den= : Ngày cuối khoảng cần tổng hợp lại — mặc định hôm nay}
        {--dong-y-doanh-thu-doi : Chạy tiếp kể cả khi doanh thu tóm tắt của một ngày cũ bị đổi}';

    protected $description = 'Tổng hợp lại báo cáo ngày, lãi gộp theo món và hao hụt tháng cho một ngày hoặc một khoảng ngày';

    public function handle(
        SummarizeDailyReport $tomTatNgay,
        SummarizeProductProfit $laiGopTheoMon,
        SummarizeIngredientWasteMonthly $haoHutThang,
        CauHinhQuan $cauHinh,
    ): int {
        try {
            [$tu, $den] = $this->docKhoangNgay();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $soNgay = (int) $tu->diffInDays($den) + 1;
        $this->line("Tổng hợp lại {$soNgay} ngày, từ {$tu->toDateString()} đến {$den->toDateString()}.");
        $this->newLine();

        $thangDaCham = [];
        $ngayDaChay = 0;

        for ($ngay = $tu->clone(); $ngay->lessThanOrEqualTo($den); $ngay->addDay()) {
            $ngayDaChay++;

            $ketQua = $this->chayMotNgay($ngay, $tomTatNgay, $laiGopTheoMon);

            if ($ketQua === null) {
                $this->newLine();
                $this->error('DỪNG LẠI. Ngày vừa rồi đã được quay lui, không có gì bị ghi đè.');
                $this->line("Đã tổng hợp xong {$ngayDaChay} ngày trước đó (không ngày nào đổi doanh thu).");
                $this->line('Đọc kỹ con số ở trên. Nếu đúng là số cũ sai (ví dụ có phiếu thu bị huỷ sau đó) thì chạy lại kèm --dong-y-doanh-thu-doi.');

                return self::FAILURE;
            }

            $this->inMotDong($ngayDaChay, $soNgay, $ngay, $ketQua);
            $thangDaCham[$ngay->format('Y-m')] = $ngay->clone()->startOfMonth();
        }

        $this->newLine();
        foreach ($thangDaCham as $dauThang) {
            $haoHutThang->handle($dauThang->toDateString());
            $this->line("Đã tổng hợp lại hao hụt tháng {$dauThang->format('m/Y')}.");
        }

        $this->newLine();
        $this->inMocNgay($tu, $den, $cauHinh);

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws DomainException
     */
    private function docKhoangNgay(): array
    {
        $motNgay = $this->argument('date');
        $tuTuyChon = $this->option('tu');
        $denTuyChon = $this->option('den');

        if ($motNgay !== null && ($tuTuyChon !== null || $denTuyChon !== null)) {
            throw new DomainException('Chọn một trong hai: ghi một ngày, HOẶC ghi --tu/--den cho một khoảng. Không ghi cả hai.');
        }

        if ($tuTuyChon === null && $denTuyChon === null) {
            $ngay = $motNgay !== null ? Carbon::parse($motNgay)->startOfDay() : Carbon::today();

            return [$ngay, $ngay->clone()];
        }

        $den = $denTuyChon !== null ? Carbon::parse($denTuyChon)->startOfDay() : Carbon::today();
        $tu = $tuTuyChon !== null ? Carbon::parse($tuTuyChon)->startOfDay() : $den->clone();

        if ($tu->greaterThan($den)) {
            throw new DomainException("Ngày đầu ({$tu->toDateString()}) phải trước hoặc bằng ngày cuối ({$den->toDateString()}).");
        }

        return [$tu, $den];
    }

    /**
     * Tổng hợp lại một ngày trong một giao dịch riêng. Trả về null nếu doanh
     * thu đã chốt của ngày đó bị đổi và người chạy chưa đồng ý — khi đó mọi
     * thay đổi của ngày đó đã được quay lui.
     *
     * @return array{doanh_thu: int, so_dong: int, thieu_gia_von: int, chua_bung_ra: int}|null
     */
    private function chayMotNgay(Carbon $ngay, SummarizeDailyReport $tomTatNgay, SummarizeProductProfit $laiGopTheoMon): ?array
    {
        $doanhThuCu = DailySummary::query()->where('date', $ngay->toDateString())->value('revenue_amount');

        try {
            return DB::transaction(function () use ($ngay, $tomTatNgay, $laiGopTheoMon, $doanhThuCu): array {
                $tomTat = $tomTatNgay->handle($ngay->toDateString());
                $dongLaiGop = $laiGopTheoMon->handle($ngay->toDateString());

                $doanhThuMoi = (int) $tomTat->revenue_amount;

                // Ném lỗi để CẢ ngày đó được quay lui — không được ghi đè số cũ
                // rồi mới báo, lúc đó số cũ đã mất, không ai đối chiếu được nữa.
                if ($doanhThuCu !== null && (int) $doanhThuCu !== $doanhThuMoi && ! $this->option('dong-y-doanh-thu-doi')) {
                    throw new DomainException(
                        "Ngày {$ngay->toDateString()}: doanh thu tóm tắt đang là ".Money::fromInt((int) $doanhThuCu)->format().
                        ', tính lại ra '.Money::fromInt($doanhThuMoi)->format().'.'
                    );
                }

                return [
                    'doanh_thu' => $doanhThuMoi,
                    'so_dong' => $dongLaiGop->count(),
                    'thieu_gia_von' => (int) $dongLaiGop->sum('qty_no_cost'),
                    'chua_bung_ra' => (int) $dongLaiGop->sum('qty_not_served'),
                ];
            });
        } catch (DomainException $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return null;
        }
    }

    /** @param  array{doanh_thu: int, so_dong: int, thieu_gia_von: int, chua_bung_ra: int}  $ketQua */
    private function inMotDong(int $thuTu, int $tong, Carbon $ngay, array $ketQua): void
    {
        $conLai = $tong - $thuTu;
        $canhBao = '';

        if ($ketQua['thieu_gia_von'] > 0) {
            $canhBao .= "  ⚠ {$ketQua['thieu_gia_von']} phần bán lúc kho âm";
        }
        if ($ketQua['chua_bung_ra'] > 0) {
            $canhBao .= "  ⚠ {$ketQua['chua_bung_ra']} phần bếp chưa bấm xong";
        }

        $this->line(sprintf(
            '[%d/%d] %s — doanh thu %s, %d món%s%s',
            $thuTu,
            $tong,
            $ngay->toDateString(),
            Money::fromInt($ketQua['doanh_thu'])->format(),
            $ketQua['so_dong'],
            $canhBao,
            $conLai > 0 ? "   (còn {$conLai} ngày)" : '',
        ));
    }

    /**
     * Hạ mốc "đã kiểm phần thiếu giá vốn" xuống ngày đầu khoảng vừa chạy.
     *
     * CHỈ hạ khi khoảng vừa chạy DÍNH LIỀN với vùng đã kiểm (ngày cuối chạm
     * tới mốc cũ hoặc muộn hơn). Chạy lẻ một ngày cũ giữa vùng chưa kiểm thì
     * KHÔNG hạ mốc — hạ xuống sẽ tuyên bố sạch cho cả một quãng chưa ai đếm,
     * đúng cái sai mà mốc này sinh ra để chặn.
     */
    private function inMocNgay(Carbon $tu, Carbon $den, CauHinhQuan $cauHinh): void
    {
        $mocCu = $cauHinh->mocNgayKiemThieuGiaVon();

        if ($tu->greaterThanOrEqualTo($mocCu)) {
            $this->line("Mốc đã kiểm thiếu giá vốn giữ nguyên: {$mocCu->toDateString()} (khoảng vừa chạy nằm trong vùng đã kiểm).");

            return;
        }

        if ($den->lessThan($mocCu->clone()->subDay())) {
            $this->warn("Mốc đã kiểm thiếu giá vốn GIỮ NGUYÊN {$mocCu->toDateString()}: khoảng vừa chạy còn hở với vùng đã kiểm.");
            $this->line("Muốn tắt cảnh báo trên màn hình lãi gộp thì chạy liền một mạch tới {$mocCu->clone()->subDay()->toDateString()}.");

            return;
        }

        $cauHinh->haMocNgayKiemThieuGiaVon($tu);
        $this->info("Mốc đã kiểm thiếu giá vốn: {$mocCu->toDateString()} → {$tu->toDateString()}.");
        $this->line('Màn hình lãi gộp sẽ hết cảnh báo cho phần dữ liệu từ ngày này trở đi.');
    }
}
