<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gán uuid cho các dòng sổ cái kho CŨ đã tạo trước khi Phase 3 Bước 10 thêm
 * cột `uuid` vào stock_movements.
 *
 * Chỉ chạy MỘT LẦN sau khi migrate — không nằm trong migration vì backfill dữ
 * liệu không phải việc của migration (migration chỉ đổi cấu trúc bảng).
 *
 * uuid gán ở đây chỉ để thoả khoá UNIQUE cho các dòng lịch sử, không mang ý
 * nghĩa chống trùng: dòng cũ đã ghi xong, không còn ai gửi lại nữa.
 */
final class BackfillStockMovementUuids extends Command
{
    protected $signature = 'stock:backfill-uuid';

    protected $description = 'Gán uuid cho các dòng sổ cái kho cũ chưa có ở stock_movements';

    public function handle(): int
    {
        $soDong = 0;

        StockMovement::query()
            ->whereNull('uuid')
            ->chunkById(500, function ($dongDuLieu) use (&$soDong): void {
                foreach ($dongDuLieu as $mot) {
                    // Ghi thẳng qua tầng truy vấn, KHÔNG qua $mot->update():
                    // sổ cái đã bị khoá cứng không cho sửa ở tầng Model (K1,
                    // xem StockMovement::performUpdate()). Đây là ngoại lệ DUY
                    // NHẤT và có chủ đích — vá dữ liệu cũ một lần cho một cột
                    // vừa mới thêm, không phải sửa nghiệp vụ. Cố tình KHÔNG mở
                    // một "cửa được phép" trên Model như StockBalance có, vì
                    // cửa đó sẽ sống mãi còn việc này chỉ chạy một lần.
                    DB::connection('tenant')->table('stock_movements')
                        ->where('id', $mot->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                    $soDong++;
                }
            });

        $this->line("stock_movements: đã gán uuid cho {$soDong} dòng.");

        return self::SUCCESS;
    }
}
