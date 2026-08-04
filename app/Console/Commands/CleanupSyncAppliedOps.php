<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Sync\Models\SyncAppliedOp;
use Illuminate\Console\Command;

/**
 * Dọn sổ cái chống trùng op_uuid (Phase 2 Bước 4) — chỉ cần giữ đủ lâu để
 * máy POS gửi lại một gói cũ (mạng lag/khởi động lại) vẫn nhận đúng
 * "duplicate", không cần giữ mãi. Không chạy tự động — chủ quán/quản trị
 * gọi tay hoặc lên lịch riêng, KHÔNG đụng tới sync_conflicts (đó là dữ liệu
 * giao dịch, không được xoá).
 */
final class CleanupSyncAppliedOps extends Command
{
    protected $signature = 'sync:cleanup-applied-ops {--ngay=7 : Xoá bản ghi cũ hơn bao nhiêu ngày}';

    protected $description = 'Dọn sổ cái chống trùng op_uuid của đồng bộ (sync_applied_ops) cũ hơn N ngày';

    public function handle(): int
    {
        $soNgay = (int) $this->option('ngay');
        $moc = now()->subDays($soNgay);

        $soDaXoa = SyncAppliedOp::query()->where('applied_at', '<', $moc)->delete();

        $this->line("Đã xoá {$soDaXoa} bản ghi sync_applied_ops cũ hơn {$soNgay} ngày (trước {$moc->format('d/m/Y H:i')}).");

        return self::SUCCESS;
    }
}
