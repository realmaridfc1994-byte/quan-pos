<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\Actions\ReconcileStockLedger;
use App\Domain\Inventory\DTO\StockReconciliationResult;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Chạy tay đối soát sổ cái kho cho một khoảng ngày bất kỳ — Phase 3 Bước 9.
 *
 * Khoảng ngày (--tu/--den) CHỈ áp dụng cho mục 3 (đối chiếu served_at với sổ
 * cái) — mục 1 và 2 luôn kiểm TOÀN BỘ lịch sử vì đó là số cộng dồn từ đầu,
 * không cắt theo ngày được.
 */
final class ReconcileStockLedgerCommand extends Command
{
    protected $signature = 'stock:doi-soat {--tu= : Ngày bắt đầu đối chiếu served_at, mặc định hôm nay} {--den= : Ngày kết thúc, mặc định hôm nay}';

    protected $description = 'Đối soát sổ cái kho: số lượng, giá trị tồn, và dòng món đã phục vụ có đủ sổ cái tương ứng';

    public function handle(ReconcileStockLedger $action): int
    {
        $tuNgay = Carbon::parse($this->option('tu') ?? 'today');
        $denNgay = Carbon::parse($this->option('den') ?? 'today');

        $ketQua = $action->handle($tuNgay, $denNgay);

        $this->newLine();
        $this->line('<fg=cyan;options=bold>ĐỐI SOÁT SỔ CÁI KHO</>');
        $this->line("Đối chiếu dòng món phục vụ từ {$tuNgay->toDateString()} đến {$denNgay->toDateString()} (mục 1, 2 luôn kiểm toàn bộ lịch sử).");
        $this->newLine();

        $this->inMucSoLuong($ketQua);
        $this->inMucGiaTri($ketQua);
        $this->inMucServedAt($ketQua);

        $this->newLine();
        if ($ketQua->sach()) {
            $this->line('<fg=green;options=bold>✅ SỔ CÁI KHO SẠCH — KHÔNG LỆCH.</>');

            return self::SUCCESS;
        }

        $this->line('<fg=red;options=bold>❌ PHÁT HIỆN LỆCH — XEM CHI TIẾT Ở TRÊN.</>');

        return self::FAILURE;
    }

    private function inMucSoLuong(StockReconciliationResult $ketQua): void
    {
        $this->line('<options=bold>1. Số lượng: sổ cái so với bảng tồn</>');

        if ($ketQua->lechQty === []) {
            $this->line('   ✅ Khớp tuyệt đối với mọi nguyên liệu.');

            return;
        }

        $this->line('   ❌ Lệch ở '.count($ketQua->lechQty).' nguyên liệu:');
        foreach ($ketQua->lechQty as $dong) {
            $this->line("      - {$dong['ingredient_name']}: sổ cái {$dong['so_cai']}, bảng tồn {$dong['ton_kho']}, lệch {$dong['lech']}");
        }
    }

    private function inMucGiaTri(StockReconciliationResult $ketQua): void
    {
        $this->newLine();
        $this->line('<options=bold>2. Giá trị: sổ cái so với bảng tồn</>');

        if ($ketQua->lechCost === []) {
            $this->line('   ✅ Khớp tuyệt đối với mọi nguyên liệu.');

            return;
        }

        $this->line('   ❌ Lệch ở '.count($ketQua->lechCost).' nguyên liệu:');
        foreach ($ketQua->lechCost as $dong) {
            $lech = Money::fromInt(abs($dong['lech']))->format();
            $huong = $dong['lech'] < 0 ? 'thiếu' : 'thừa';
            $this->line("      - {$dong['ingredient_name']}: sổ cái ".number_format($dong['so_cai']).' đ, bảng tồn '.number_format($dong['ton_kho'])." đ, {$huong} {$lech}");
        }
    }

    private function inMucServedAt(StockReconciliationResult $ketQua): void
    {
        $this->newLine();
        $this->line('<options=bold>3. Dòng món đã phục vụ ↔ dòng sổ cái</>');

        if ($ketQua->thieuSoCai === [] && $ketQua->soCaiMoCoi === []) {
            $this->line('   ✅ Mọi dòng món đã phục vụ đều có sổ cái tương ứng, không có dòng sổ cái mồ côi.');

            return;
        }

        if ($ketQua->thieuSoCai !== []) {
            $this->line('   ❌ '.count($ketQua->thieuSoCai).' dòng món đã phục vụ nhưng THIẾU sổ cái:');
            foreach ($ketQua->thieuSoCai as $dong) {
                $this->line("      - Dòng #{$dong['order_item_id']} ({$dong['product_name']} — {$dong['variant_name']}), phục vụ lúc {$dong['served_at']}");
            }
        }

        if ($ketQua->soCaiMoCoi !== []) {
            $this->line('   ❌ '.count($ketQua->soCaiMoCoi).' dòng sổ cái MỒ CÔI (trỏ về dòng món chưa/không còn served_at):');
            foreach ($ketQua->soCaiMoCoi as $dong) {
                $this->line("      - Sổ cái #{$dong['stock_movement_id']} → order_item #{$dong['ref_id']}");
            }
        }
    }
}
