<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Printing\Templates\TemBanQrTemplate;
use App\Support\MaBanCongKhai;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Vẽ tem QR để in và dán lên mặt bàn — Phase 4.
 *
 *   php artisan pos:in-tem-ban --tat-ca
 *   php artisan pos:in-tem-ban --ban=B01 --ban=B02
 *
 * File SVG rơi vào `storage/app/tem-ban/`. Mở bằng trình duyệt rồi in.
 *
 * ── LỆNH NÀY KHÔNG BAO GIỜ SINH MÃ MỚI ────────────────────────────────────
 * Nó chỉ VẼ LẠI ảnh từ mã đã có. Đây là điều quan trọng nhất của cả file:
 * mã bàn sinh đúng một lần lúc tạo bàn và sống mãi. Nếu lệnh in lỡ sinh mã
 * mới thì mọi tem đang dán trên bàn thành vô dụng, và không ai biết cho tới
 * khi khách quét không được giữa giờ cao điểm. Có test riêng gác việc này.
 *
 * ── KHI NÀO PHẢI IN LẠI ───────────────────────────────────────────────────
 * Chỉ khi đổi địa chỉ máy quán (`pos.khach_tu_goi.duong_dan_goc`). Tem cũ trỏ
 * về địa chỉ cũ nên chết hết. Lệnh in cảnh báo địa chỉ đang dùng ở đầu mỗi
 * lần chạy để người in đối chiếu trước khi tốn giấy.
 */
final class InTemBanCommand extends Command
{
    protected $signature = 'pos:in-tem-ban
        {--ban=* : Mã bàn cần in, ví dụ --ban=B01 --ban=B02}
        {--tat-ca : In tem cho toàn bộ bàn đang hoạt động}';

    protected $description = 'Vẽ tem mã QR (SVG) để in và dán lên mặt bàn';

    private const THU_MUC = 'tem-ban';

    public function handle(TemBanQrTemplate $mau): int
    {
        $maBan = (array) $this->option('ban');
        $tatCa = (bool) $this->option('tat-ca');

        if ($maBan === [] && ! $tatCa) {
            $this->error('Phải chọn: --tat-ca cho mọi bàn, hoặc --ban=B01 cho từng bàn.');

            return self::FAILURE;
        }

        $ban = DiningTable::query()
            ->where('is_active', true)
            ->when(! $tatCa, fn ($q) => $q->whereIn('code', $maBan))
            ->orderBy('sort_order')
            ->get();

        if ($ban->isEmpty()) {
            $this->error('Không tìm thấy bàn nào đang hoạt động khớp yêu cầu.');

            return self::FAILURE;
        }

        $this->canhBaoDiaChi();

        foreach ($ban as $b) {
            $duongDanFile = self::THU_MUC.'/'.$b->code.'.svg';
            Storage::disk('local')->put($duongDanFile, $mau->render($b));

            $this->line("  {$b->code} — {$b->name}  →  {$duongDanFile}");
        }

        $this->newLine();
        $this->info('Đã vẽ '.$ban->count().' tem vào '.Storage::disk('local')->path(self::THU_MUC).'.');
        $this->line('Mở file .svg bằng trình duyệt rồi in. Mã bàn KHÔNG đổi — tem cũ vẫn dùng được bình thường.');

        return self::SUCCESS;
    }

    /**
     * In địa chỉ đang dùng TRƯỚC KHI vẽ, để người in đối chiếu. Địa chỉ sai
     * thì tem in ra vô dụng, mà phải tới lúc khách quét mới phát hiện.
     */
    private function canhBaoDiaChi(): void
    {
        $goc = (string) config('pos.khach_tu_goi.duong_dan_goc');

        $this->warn("Địa chỉ máy quán đang dùng: {$goc}");
        $this->line('Ví dụ đường link trong tem: '.MaBanCongKhai::duongDan('xxxxxxxxxxxxxxxxxxxxxx'));
        $this->line('Sai địa chỉ này thì tem in ra không quét được. Đổi địa chỉ sau này là PHẢI IN LẠI TOÀN BỘ TEM.');
        $this->newLine();
    }
}
