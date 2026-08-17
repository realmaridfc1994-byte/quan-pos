<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\OpenStockTakeData;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockTake;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mở phiếu kiểm kê — chụp lại tồn hệ thống của mọi nguyên liệu đang dùng
 * TẠI THỜI ĐIỂM NÀY vào từng dòng stock_take_items (system_qty), không đổi
 * về sau dù kho có biến động tiếp trong lúc đếm.
 *
 * Chặn khi còn bàn đang phục vụ/chưa tính tiền xong (status open/billing):
 * món đang bán ra sẽ tiếp tục trừ kho trong lúc đếm, làm số liệu đổi giữa
 * chừng — kiểm kê chỉ có ý nghĩa khi kho đứng yên.
 *
 * K13 (uq_stock_takes_only_one_open) chặn ở DB — kiểm tra trước để báo lỗi
 * tiếng Việt dễ hiểu thay vì để lộ lỗi khoá duy nhất thô.
 */
final class OpenStockTake
{
    public function handle(OpenStockTakeData $data): StockTake
    {
        $conBanChuaTinhTien = TableSession::query()
            ->whereIn('status', [TableSessionStatus::Open, TableSessionStatus::Billing])
            ->exists();

        if ($conBanChuaTinhTien) {
            throw new DomainException('Còn bàn đang phục vụ hoặc chưa tính tiền xong — số liệu kho sẽ đổi giữa chừng. Phải kiểm kê lúc không còn bàn nào mở.');
        }

        return DB::connection('tenant')->transaction(function () use ($data): StockTake {
            $daCoPhieuMo = StockTake::query()->where('status', StockTakeStatus::Open)->exists();
            if ($daCoPhieuMo) {
                throw new DomainException('Đã có một phiếu kiểm kê đang mở — phải chốt phiếu đó trước khi mở phiếu mới.');
            }

            // Ghi mã tạm bằng uuid (cắt vừa VARCHAR 30) rồi cập nhật mã thật từ id
            // ngay sau đó — cùng cách chống đua tranh mã đã dùng ở OpenTableSession.
            $stockTake = StockTake::query()->create([
                'code' => substr((string) Str::uuid(), 0, 30),
                'status' => StockTakeStatus::Open,
                'note' => $data->note,
                'opened_at' => now(),
                'opened_by_user_id' => $data->openedByUserId,
            ]);

            $stockTake->update([
                'code' => 'KK-'.now()->format('Ymd').'-'.str_pad((string) $stockTake->id, 4, '0', STR_PAD_LEFT),
            ]);

            $nguyenLieuDangDung = Ingredient::query()->where('is_active', true)->orderBy('id')->get(['id']);
            $tonHienTai = StockBalance::query()
                ->whereIn('ingredient_id', $nguyenLieuDangDung->pluck('id'))
                ->pluck('qty', 'ingredient_id');

            foreach ($nguyenLieuDangDung as $nguyenLieu) {
                $stockTake->items()->create([
                    'ingredient_id' => $nguyenLieu->id,
                    'system_qty' => $tonHienTai[$nguyenLieu->id] ?? 0,
                    'counted_qty' => null,
                ]);
            }

            return $stockTake->refresh();
        });
    }
}
