<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\DetachTableData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nhả một bàn ra khỏi lượt khách đang mở.
 *
 * B5: không nhả được bàn cuối cùng — muốn nhả hết thì phải đóng lượt khách.
 * Kiểm tra B5 LUÔN thực hiện trước, không bao giờ rơi vào nhánh chọn bàn
 * chính mới bên dưới.
 *
 * B3: một lượt khách luôn có đúng một bàn chính. Nhả đúng bàn chính mà còn
 * bàn khác thì tự đôn bàn chính mới theo đúng ba mức ưu tiên (đã thống nhất
 * với chủ dự án):
 *   1. Chỉ xét các bàn ĐANG CHIẾM (detached_at IS NULL) của lượt khách này.
 *   2. Bàn có attached_at (lần chiếm HIỆN TẠI, không phải lần chiếm đầu tiên
 *      trong lịch sử) sớm nhất được chọn — vì mỗi bàn chỉ có tối đa một dòng
 *      đang chiếm cùng lúc (uq_tst_one_session_per_table), lọc detached_at
 *      IS NULL rồi sắp theo attached_at đã tự động phản ánh đúng lần ghép
 *      gần nhất, không lẫn với lần ghép-nhả-ghép lại trước đó.
 *   3. attached_at bằng nhau thì lấy id nhỏ hơn, để kết quả luôn xác định.
 *
 * Nhả bàn và gán bàn chính mới nằm trong CÙNG một DB::transaction — không có
 * khoảnh khắc nào lượt khách không có bàn chính.
 */
final class DetachTable
{
    public function handle(DetachTableData $data): TableSessionTable
    {
        return DB::transaction(function () use ($data): TableSessionTable {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if ($tableSession->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách này không còn mở, không nhả bàn được nữa.');
            }

            $banDangChiem = TableSessionTable::query()
                ->where('table_session_id', $tableSession->id)
                ->whereNull('detached_at')
                ->orderBy('attached_at')
                ->orderBy('id')
                ->get();

            $banCanNha = $banDangChiem->firstWhere('dining_table_id', $data->diningTableId);

            if ($banCanNha === null) {
                throw new DomainException('Bàn này không thuộc lượt khách đang chọn.');
            }

            if ($banDangChiem->count() <= 1) {
                throw new DomainException('Không nhả được bàn cuối cùng. Muốn dọn hết thì đóng lượt khách.');
            }

            $banCanNha->update(['detached_at' => now()]);

            if ($banCanNha->is_primary) {
                $banChinhMoi = $banDangChiem->first(fn (TableSessionTable $t) => $t->id !== $banCanNha->id);
                $banChinhMoi->update(['is_primary' => true]);
            }

            return $banCanNha;
        });
    }
}
