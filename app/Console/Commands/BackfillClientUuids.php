<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use App\Domain\Ordering\Models\TableSession;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gán uuid cho dữ liệu CŨ đã tạo trước khi Phase 2 Bước 2 thêm cột `uuid` vào
 * table_sessions, order_items, order_item_options.
 *
 * Chỉ chạy MỘT LẦN sau khi migrate — không nằm trong migration vì backfill
 * dữ liệu không phải việc của migration (migration chỉ đổi cấu trúc bảng).
 *
 * Dữ liệu cũ không có uuid "thật" do máy POS sinh — uuid gán ở đây chỉ để
 * thoả ràng buộc UNIQUE cho các dòng lịch sử, không mang ý nghĩa chống trùng
 * với máy POS (dữ liệu cũ vốn đã ghi xong, không còn gửi lại được nữa).
 */
final class BackfillClientUuids extends Command
{
    protected $signature = 'pos:backfill-uuid';

    protected $description = 'Gán uuid cho các dòng cũ chưa có ở table_sessions, order_items, order_item_options';

    public function handle(): int
    {
        $this->backfillMotBang(TableSession::query()->whereNull('uuid'), 'table_sessions');
        $this->backfillMotBang(OrderItem::query()->whereNull('uuid'), 'order_items');
        $this->backfillMotBang(OrderItemOption::query()->whereNull('uuid'), 'order_item_options');

        return self::SUCCESS;
    }

    /** @param Builder<Model> $query */
    private function backfillMotBang(Builder $query, string $tenBang): void
    {
        $soDong = 0;

        $query->chunkById(500, function ($dongDuLieu) use (&$soDong): void {
            foreach ($dongDuLieu as $mot) {
                $mot->update(['uuid' => (string) Str::uuid()]);
                $soDong++;
            }
        });

        $this->line("{$tenBang}: đã gán uuid cho {$soDong} dòng.");
    }
}
