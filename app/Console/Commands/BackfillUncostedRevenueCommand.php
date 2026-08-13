<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reporting\Actions\SummarizeDailyReport;
use App\Domain\Reporting\Actions\SummarizeProductProfit;
use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\ProductProfitDaily;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tính lại phần "doanh thu chưa biết giá vốn" cho những ngày đã tổng hợp TRƯỚC
 * khi có Bước 4B.0 — Phase 4 Bước 4B.0.
 *
 * VÌ SAO PHẢI CÓ LỆNH RIÊNG, KHÔNG LÀM TRONG MIGRATION:
 * Migration chạy tự động lúc cài đặt, không ai ngồi đọc kết quả. Việc này đổi
 * CON SỐ LÃI GỘP của những ngày đã qua — lãi gộp sẽ TỤT XUỐNG, đúng như thiết
 * kế, vì phần không biết giá vốn thôi không được tính thành lãi nữa. Chủ quán
 * phải nhìn thấy nó tụt bao nhiêu, ngày nào, trước khi nó tụt. Nên: một lệnh
 * riêng, chạy tay, có chế độ chạy thử.
 *
 *   php artisan report:backfill-gia-von --tu=2026-07-01 --den=2026-07-31 --thu
 *   php artisan report:backfill-gia-von --tu=2026-07-01 --den=2026-07-31
 *
 * `--thu` (chạy thử): làm đủ mọi phép tính rồi QUAY LUI SẠCH, chỉ in bảng so
 * sánh trước/sau. Không một chữ nào được ghi xuống. Đây là cách duy nhất in
 * được cột "sau" mà vẫn không đụng dữ liệu — có tính thật mới biết số thật.
 *
 * CHẠY TỪNG THÁNG MỘT, không chạy một phát cả năm: bảng in ra dài quá thì
 * không ai đọc, mà không đọc thì chạy thử vô nghĩa.
 *
 * Lệnh này KHÔNG ghi gì vào orders/order_items/payments/stock_movements — chỉ
 * chép lại hai bảng tóm tắt từ nguồn gốc. Nhưng nếu sau ngày đó có phiếu thu bị
 * huỷ thì doanh thu tóm tắt cũ và số tính lại sẽ khác nhau; khi đó lệnh DỪNG
 * ngày đó lại và quay lui, trừ khi người chạy đã đọc con số và đồng ý bằng
 * --dong-y-doanh-thu-doi. Giống hệt lá chắn của `report:summarize`.
 */
final class BackfillUncostedRevenueCommand extends Command
{
    protected $signature = 'report:backfill-gia-von
        {--tu= : Ngày đầu khoảng cần tính lại, định dạng YYYY-MM-DD}
        {--den= : Ngày cuối khoảng cần tính lại — mặc định hôm nay}
        {--thu : CHẠY THỬ — tính đủ, in bảng so sánh, rồi quay lui sạch, không ghi gì}
        {--dong-y-doanh-thu-doi : Chạy tiếp kể cả khi doanh thu tóm tắt của một ngày cũ bị đổi}';

    protected $description = 'Tính lại phần doanh thu chưa biết giá vốn cho các ngày đã tổng hợp trước Bước 4B.0';

    public function handle(
        SummarizeDailyReport $tomTatNgay,
        SummarizeProductProfit $laiGopTheoMon,
    ): int {
        try {
            [$tu, $den] = $this->docKhoangNgay();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $chayThu = (bool) $this->option('thu');
        $soNgay = (int) $tu->diffInDays($den) + 1;

        if ($chayThu) {
            $this->info("CHẠY THỬ {$soNgay} ngày ({$tu->toDateString()} → {$den->toDateString()}). Không ghi gì xuống database.");
        } else {
            $this->warn("GHI THẬT {$soNgay} ngày ({$tu->toDateString()} → {$den->toDateString()}).");
        }
        $this->newLine();

        $bang = [];
        $daChay = 0;

        for ($ngay = $tu->clone(); $ngay->lessThanOrEqualTo($den); $ngay->addDay()) {
            $truoc = $this->docSoLieu($ngay);
            $sau = $this->chayMotNgay($ngay, $tomTatNgay, $laiGopTheoMon, $chayThu);

            if ($sau === null) {
                $this->newLine();
                $this->error('DỪNG LẠI. Ngày vừa rồi đã được quay lui, không có gì bị ghi đè.');
                $this->line("Đã xử lý xong {$daChay} ngày trước đó.");
                $this->line('Đọc kỹ con số ở trên. Nếu đúng là số cũ sai (ví dụ có phiếu thu bị huỷ sau đó) thì chạy lại kèm --dong-y-doanh-thu-doi.');

                return self::FAILURE;
            }

            $daChay++;
            $bang[] = $this->dongBang($ngay, $truoc, $sau);
        }

        $this->table(
            ['Ngày', 'Doanh thu món', 'Lãi gộp CŨ', 'Lãi gộp MỚI', 'Chưa biết giá vốn', 'Tỉ lệ'],
            $bang,
        );

        $this->newLine();

        if ($chayThu) {
            $this->info('Đã quay lui sạch. Database không đổi một chữ nào.');
            $this->line('Đọc bảng trên. Cột "Lãi gộp MỚI" thấp hơn cột "Lãi gộp CŨ" đúng bằng phần không biết giá vốn — đó là điều mong đợi, không phải mất tiền.');
            $this->line('Đồng ý thì chạy lại bỏ cờ --thu.');

            return self::SUCCESS;
        }

        $this->info("Đã ghi lại {$daChay} ngày.");
        $this->line('Màn hình lãi gộp từ giờ đọc đúng số cho khoảng ngày này.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws DomainException
     */
    private function docKhoangNgay(): array
    {
        $tuTuyChon = $this->option('tu');

        if ($tuTuyChon === null) {
            throw new DomainException('Phải ghi rõ khoảng ngày: --tu=YYYY-MM-DD [--den=YYYY-MM-DD]. Lệnh này cố ý không có mặc định "cả lịch sử".');
        }

        $tu = Carbon::parse($tuTuyChon)->startOfDay();
        $den = $this->option('den') !== null
            ? Carbon::parse($this->option('den'))->startOfDay()
            : Carbon::today();

        if ($tu->greaterThan($den)) {
            throw new DomainException("Ngày đầu ({$tu->toDateString()}) phải trước hoặc bằng ngày cuối ({$den->toDateString()}).");
        }

        return [$tu, $den];
    }

    /**
     * Tính lại một ngày trong một giao dịch riêng. Trả về số liệu SAU khi tính
     * lại, hoặc null nếu doanh thu đã chốt của ngày đó bị đổi mà người chạy
     * chưa đồng ý — khi đó ngày đó đã được quay lui sạch.
     *
     * Chế độ chạy thử quay lui NGAY SAU KHI đã đọc xong số mới: có tính thật
     * mới biết số thật, nhưng không giữ lại một chữ nào.
     *
     * @return array{doanh_thu_ket: int, doanh_thu_mon: int, lai_gop_cu: int, lai_gop_moi: int, chua_biet: int}|null
     */
    private function chayMotNgay(
        Carbon $ngay,
        SummarizeDailyReport $tomTatNgay,
        SummarizeProductProfit $laiGopTheoMon,
        bool $chayThu,
    ): ?array {
        $doanhThuCu = DailySummary::query()->where('date', $ngay->toDateString())->value('revenue_amount');

        DB::beginTransaction();

        try {
            $tomTatNgay->handle($ngay->toDateString());
            $laiGopTheoMon->handle($ngay->toDateString());

            $sau = $this->docSoLieu($ngay);

            if ($chayThu) {
                DB::rollBack();

                return $sau;
            }

            if ($doanhThuCu !== null && (int) $doanhThuCu !== $sau['doanh_thu_ket'] && ! $this->option('dong-y-doanh-thu-doi')) {
                DB::rollBack();

                $this->newLine();
                $this->error(
                    "Ngày {$ngay->toDateString()}: doanh thu tóm tắt đang là ".Money::fromInt((int) $doanhThuCu)->format().
                    ', tính lại ra '.Money::fromInt($sau['doanh_thu_ket'])->format().'.'
                );

                return null;
            }

            DB::commit();

            return $sau;
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Ảnh chụp số liệu của một ngày, đọc từ hai bảng tóm tắt.
     *
     * "Lãi gộp CŨ" là công thức trước Bước 4B.0 (doanh thu trừ giá vốn, coi
     * phần không biết giá vốn như lãi 100%) — dựng lại ở đây CHỈ để so sánh cho
     * chủ quán thấy, không có chỗ nào trong hệ thống còn dùng nó.
     *
     * @return array{doanh_thu_ket: int, doanh_thu_mon: int, lai_gop_cu: int, lai_gop_moi: int, chua_biet: int}
     */
    private function docSoLieu(Carbon $ngay): array
    {
        $tong = ProductProfitDaily::query()
            ->where('date', $ngay->toDateString())
            ->selectRaw('COALESCE(SUM(revenue_amount), 0) as a')
            ->selectRaw('COALESCE(SUM(cost_amount), 0) as b')
            ->selectRaw('COALESCE(SUM(revenue_uncosted_amount), 0) as c')
            ->first();

        $doanhThuMon = (int) ($tong->a ?? 0);
        $giaVon = (int) ($tong->b ?? 0);
        $chuaBiet = (int) ($tong->c ?? 0);

        return [
            'doanh_thu_ket' => (int) DailySummary::query()->where('date', $ngay->toDateString())->value('revenue_amount'),
            'doanh_thu_mon' => $doanhThuMon,
            'lai_gop_cu' => $doanhThuMon - $giaVon,
            'lai_gop_moi' => $doanhThuMon - $chuaBiet - $giaVon,
            'chua_biet' => $chuaBiet,
        ];
    }

    /**
     * @param  array{doanh_thu_ket: int, doanh_thu_mon: int, lai_gop_cu: int, lai_gop_moi: int, chua_biet: int}  $truoc
     * @param  array{doanh_thu_ket: int, doanh_thu_mon: int, lai_gop_cu: int, lai_gop_moi: int, chua_biet: int}  $sau
     * @return list<string>
     */
    private function dongBang(Carbon $ngay, array $truoc, array $sau): array
    {
        return [
            $ngay->toDateString(),
            $this->truocSau($truoc['doanh_thu_mon'], $sau['doanh_thu_mon']),
            $this->laiGop($truoc['lai_gop_cu']),
            $this->laiGop($sau['lai_gop_moi']),
            $this->truocSau($truoc['chua_biet'], $sau['chua_biet']),
            $this->tiLeChuaBiet($sau['chua_biet'], $sau['doanh_thu_mon']),
        ];
    }

    /**
     * Lãi gộp CÓ THỂ ÂM (bán lỗ vẫn phải ghi được), nên không đi qua Money —
     * Money cố ý chặn số âm. Cùng ranh giới đã ghi ở CLAUDE.md: cột nào có thể
     * mang dấu trừ thì không phải Money.
     */
    private function laiGop(int $soTien): string
    {
        $dau = $soTien < 0 ? '-' : '';

        return $dau.Money::fromInt(abs($soTien))->format();
    }

    /** In "cũ → mới" khi hai số khác nhau, in một số khi đứng yên. */
    private function truocSau(int $truoc, int $sau): string
    {
        if ($truoc === $sau) {
            return Money::fromInt($truoc)->format();
        }

        return Money::fromInt($truoc)->format().' → '.Money::fromInt($sau)->format();
    }

    /**
     * Tỉ lệ phần trăm ĐỂ IN RA MÀN HÌNH, không phải số tiền: tính bằng intdiv
     * (số nguyên) chứ không phải phép chia số thực, đúng luật cấm float cho
     * tiền ở CLAUDE.md mục 7.
     */
    private function tiLeChuaBiet(int $chuaBiet, int $mauSo): string
    {
        if ($mauSo === 0) {
            return '—';
        }

        return intdiv($chuaBiet * 100, $mauSo).'%';
    }
}
