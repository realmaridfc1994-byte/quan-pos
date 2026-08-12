<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\Actions\ReconcileStockLedger;
use App\Domain\Inventory\DTO\StockReconciliationResult;
use App\Domain\Inventory\Enums\StockMovementRefType;
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
        $this->line("Đối chiếu chứng từ từ {$tuNgay->toDateString()} đến {$denNgay->toDateString()} (mục 1, 2 luôn kiểm toàn bộ lịch sử).");
        $this->newLine();

        $this->inMucSoLuong($ketQua);
        $this->inMucGiaTri($ketQua);
        $this->inMucServedAt($ketQua);
        $this->inMucThieuGiaVon($ketQua);
        $this->inMucQuenBamXong($ketQua);
        $this->inMucNhapHang($ketQua);
        $this->inMucKiemKe($ketQua);

        $this->newLine();
        if ($ketQua->sach()) {
            $this->line('<fg=green;options=bold>✅ SỔ CÁI KHO SẠCH — KHÔNG LỆCH.</>');

            if ($ketQua->coCanhBao()) {
                $this->line('<fg=yellow>⚠️  Nhưng có việc cần dọn ở mục 4 và/hoặc 5 — xem ở trên.</>');
            }

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

        if ($ketQua->thieuSoCai === [] && $ketQua->soCaiMoCoi === [] && $ketQua->moCoiDaGhiChu === []) {
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
            $this->line('   <fg=gray>Xem xong mà thấy không phải lỗi thì chạy: php artisan stock:ghi-chu-mo-coi &lt;số hiệu&gt; --ghi-chu="..." --ly-do="..."</>');
        }

        $this->inMoCoiDaGhiChu($ketQua, StockMovementRefType::OrderItem, 'order_item');
    }

    /**
     * Mục 6 — NHẬP HÀNG: phiếu đã nhận ↔ dòng sổ cái. Cùng cấu trúc và cùng
     * ảnh hưởng tới mã lỗi như mục 3.
     */
    private function inMucNhapHang(StockReconciliationResult $ketQua): void
    {
        $this->newLine();
        $this->line('<options=bold>6. Dòng phiếu nhập đã nhận ↔ dòng sổ cái</>');

        if ($ketQua->thieuSoCaiNhapHang === [] && $ketQua->moCoiNhapHang === []) {
            $this->line('   ✅ Mọi dòng hàng đã nhận đều có sổ cái tương ứng, không có dòng sổ cái mồ côi.');
        }

        if ($ketQua->thieuSoCaiNhapHang !== []) {
            $this->line('   ❌ '.count($ketQua->thieuSoCaiNhapHang).' dòng hàng ĐÃ NHẬN nhưng THIẾU sổ cái:');
            foreach ($ketQua->thieuSoCaiNhapHang as $dong) {
                $this->line("      - Phiếu {$dong['purchase_code']}, dòng #{$dong['purchase_item_id']} ({$dong['ingredient_name']}), nhận lúc {$dong['received_at']}");
            }
        }

        if ($ketQua->moCoiNhapHang !== []) {
            $this->line('   ❌ '.count($ketQua->moCoiNhapHang).' dòng sổ cái MỒ CÔI (trỏ về dòng phiếu nhập không còn ở trạng thái đã nhận):');
            foreach ($ketQua->moCoiNhapHang as $dong) {
                $this->line("      - Sổ cái #{$dong['stock_movement_id']} → purchase_item #{$dong['ref_id']}");
            }
        }

        $this->inMoCoiDaGhiChu($ketQua, StockMovementRefType::PurchaseItem, 'purchase_item');
    }

    /**
     * Mục 7 — KIỂM KÊ: dòng lệch của phiếu đã chốt ↔ dòng sổ cái điều chỉnh.
     * Dòng chưa đếm và dòng khớp KHÔNG sinh sổ cái nên không nằm ở đây.
     */
    private function inMucKiemKe(StockReconciliationResult $ketQua): void
    {
        $this->newLine();
        $this->line('<options=bold>7. Dòng kiểm kê lệch đã chốt ↔ dòng sổ cái</>');

        if ($ketQua->thieuSoCaiKiemKe === [] && $ketQua->moCoiKiemKe === []) {
            $this->line('   ✅ Mọi chênh lệch kiểm kê đã chốt đều có sổ cái tương ứng, không có dòng sổ cái mồ côi.');
        }

        if ($ketQua->thieuSoCaiKiemKe !== []) {
            $this->line('   ❌ '.count($ketQua->thieuSoCaiKiemKe).' dòng kiểm kê LỆCH đã chốt nhưng THIẾU sổ cái:');
            foreach ($ketQua->thieuSoCaiKiemKe as $dong) {
                $this->line("      - Phiếu kiểm kê #{$dong['stock_take_id']}, dòng #{$dong['stock_take_item_id']} ({$dong['ingredient_name']}), lệch {$dong['diff_qty']}, chốt lúc {$dong['closed_at']}");
            }
        }

        if ($ketQua->moCoiKiemKe !== []) {
            $this->line('   ❌ '.count($ketQua->moCoiKiemKe).' dòng sổ cái MỒ CÔI (trỏ về dòng kiểm kê chưa chốt, không lệch, hoặc chưa đếm):');
            foreach ($ketQua->moCoiKiemKe as $dong) {
                $this->line("      - Sổ cái #{$dong['stock_movement_id']} → stock_take_item #{$dong['ref_id']}");
            }
        }

        $this->inMoCoiDaGhiChu($ketQua, StockMovementRefType::StockTakeItem, 'stock_take_item');
    }

    /**
     * Dòng mồ côi ĐÃ có người xem và xác nhận: vẫn LIỆT KÊ đầy đủ, nhưng không
     * đếm vào số lỗi và không làm lệnh trả về mã lỗi — xem
     * AcknowledgeOrphanMovement.
     */
    private function inMoCoiDaGhiChu(StockReconciliationResult $ketQua, StockMovementRefType $nguon, string $tenChungTu): void
    {
        $cuaMuc = array_filter($ketQua->moCoiDaGhiChu, fn ($dong) => $dong['nguon'] === $nguon->value);

        if ($cuaMuc === []) {
            return;
        }

        $this->line('   ✔️  '.count($cuaMuc).' dòng sổ cái mồ côi ĐÃ XEM (không tính vào số lỗi):');
        foreach ($cuaMuc as $dong) {
            $this->line("      - Sổ cái #{$dong['stock_movement_id']} → {$tenChungTu} #{$dong['ref_id']}");
            $this->line("        {$dong['acknowledged_by']} xác nhận lúc {$dong['acknowledged_at']}: {$dong['reason']}");
        }
    }

    /**
     * Mục 4 — CẢNH BÁO, KHÔNG PHẢI LỖI. Sổ sách vẫn khớp; chỉ là những dòng
     * này bán lúc kho đang âm nên chưa biết giá vốn thật, làm lãi gộp trông
     * cao hơn thực tế.
     */
    private function inMucThieuGiaVon(StockReconciliationResult $ketQua): void
    {
        $this->newLine();
        $this->line('<options=bold>4. Dòng sổ cái chưa xác định được giá vốn (bán lúc tồn âm)</>');

        if ($ketQua->thieuGiaVon === []) {
            $this->line('   ✅ Không có dòng nào — mọi lần xuất kho trong kỳ đều biết giá vốn.');

            return;
        }

        $tongDong = array_sum(array_column($ketQua->thieuGiaVon, 'so_dong'));
        $this->line("   <fg=yellow>⚠️  {$tongDong} dòng ở ".count($ketQua->thieuGiaVon).' nguyên liệu. Đây KHÔNG phải lỗi sổ sách — là tồn âm cần dọn:</>');

        foreach ($ketQua->thieuGiaVon as $dong) {
            $this->line("      - {$dong['ingredient_name']}: {$dong['so_dong']} dòng");
        }

        $this->line('   <fg=gray>Lãi gộp của những món dùng các nguyên liệu này đang CAO HƠN thực tế.</>');
    }

    /**
     * Mục 5 — CẢNH BÁO, KHÔNG PHẢI LỖI. Bếp quên bấm "xong" nên kho chưa trừ,
     * trong khi tiền đã tính đủ cho khách.
     */
    private function inMucQuenBamXong(StockReconciliationResult $ketQua): void
    {
        $this->newLine();
        $this->line('<options=bold>5. Món đã tính tiền mà bếp chưa bấm xong</>');

        if ($ketQua->quenBamXong === []) {
            $this->line('   ✅ Không có dòng nào — mọi món của lượt khách đã đóng đều được bấm xong.');

            return;
        }

        $this->line('   <fg=yellow>⚠️  '.count($ketQua->quenBamXong).' dòng món thuộc lượt khách ĐÃ ĐÓNG mà chưa bấm xong:</>');

        foreach ($ketQua->quenBamXong as $dong) {
            $this->line("      - Dòng #{$dong['order_item_id']} ({$dong['product_name']} — {$dong['variant_name']}), lượt khách {$dong['table_session_code']}, đóng lúc {$dong['closed_at']}");
        }

        $this->line('   <fg=gray>Những dòng này chưa trừ kho nhưng đã tính tiền — lãi gộp món đó đang CAO HƠN thực tế.</>');
    }
}
