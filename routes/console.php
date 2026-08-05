<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Việc dọn sổ cái chống trùng op_uuid (sync_applied_ops) KHÔNG đăng ký lịch
// chạy nền ở đây nữa (quyết định 05/08) — một cửa sổ dòng lệnh phải mở suốt
// đời (schedule:work) là thứ chắc chắn hỏng ở quán (tắt máy, mất điện, đóng
// nhầm cửa sổ) mà không ai biết cho tới khi có chuyện. Việc dọn dẹp này giờ
// chạy NGAY TRONG luồng đồng bộ, xem
// App\Domain\Sync\Actions\SyncBatch::donDepSyncAppliedOpsCu(). Lệnh
// `sync:cleanup-applied-ops` vẫn còn để chạy tay khi cần (xem
// app/Console/Commands/CleanupSyncAppliedOps.php).
