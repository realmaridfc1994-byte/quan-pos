<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Carbon;

/**
 * HAO HỤT THEO NGƯỜI GHI, theo tháng — Phase 3 Bước 6.
 *
 * Vì sao báo cáo này quan trọng hơn cả ngưỡng PIN: ngưỡng chỉ chặn được từng
 * lần ghi lớn. Nó không thấy được người ghi "5 lon vỡ" mỗi tối — mỗi lần vài
 * chục nghìn, không lần nào chạm ngưỡng, nhưng ba tháng là 450 lon. Đặt cạnh
 * nhau số lần và tổng tiền của từng người thì chuyện đó lộ ra ngay, không cần
 * ngưỡng nào cả.
 *
 * ── MỘT NGOẠI LỆ CÓ CHỦ Ý, CẦN OPUS/CHỦ QUÁN BIẾT ────────────────────────
 * Mọi màn hình chủ quán khác chỉ đọc bảng chốt (`product_profit_daily`,
 * `ingredient_waste_monthly`), không đọc thẳng sổ cái — luật đặt ở
 * docs/kiem-toan-kho.md mục 4. Query này ĐỌC THẲNG `stock_movements`, vì
 * `ingredient_waste_monthly` chốt theo NGUYÊN LIỆU, không có chiều NGƯỜI GHI,
 * nên không có bảng chốt nào trả lời được câu hỏi này. Làm đúng luật thì phải
 * thêm một bảng chốt mới — mà thêm bảng là đổi schema, phải hỏi trước
 * (CLAUDE.md mục 7.2).
 *
 * Đọc thẳng ở đây rẻ: một câu GROUP BY trên `stock_movements` lọc theo
 * type='waste' và một tháng, có sẵn chỉ mục `idx_stock_movements_type_time`.
 * Quán 5-15 bàn một tháng có vài chục dòng hao hụt.
 */
final class GetWasteByUser
{
    /**
     * @return list<array{user_id: int, user_name: string, so_lan: int, tong_qty: int, tong_cost: int}>
     */
    public function handle(?Carbon $thang = null): array
    {
        $dauThang = ($thang ?? Carbon::today())->clone()->startOfMonth();
        $cuoiThang = $dauThang->clone()->endOfMonth();

        // NGOẠI LỆ CÓ CHỦ Ý, KHÔNG PHẢI CHỖ QUÊN (chốt lại 11/08).
        // Luật "màn hình chủ quán chỉ đọc bảng chốt" sinh ra để chống màn hình
        // chậm dần theo năm tháng — dashboard quét cả bảng orders sau sáu tháng
        // là sập. Hao hụt vài chục dòng mỗi tháng không phải trường hợp đó, nên
        // thêm một bảng tổng hợp ở đây là đổi một vấn đề không có lấy một vấn
        // đề thật (thêm bảng = thêm chỗ lệch số, thêm Action tổng hợp lúc đóng
        // ca). Xem lại quyết định này nếu số dòng hao hụt vượt vài nghìn mỗi
        // tháng — đã ghi nợ trong docs/viec-ton.md.
        $tongHop = StockMovement::query()
            ->where('type', StockMovementType::Waste)
            ->whereBetween('occurred_at', [$dauThang, $cuoiThang])
            ->selectRaw('created_by_user_id, COUNT(*) as so_lan, SUM(-qty_delta) as tong_qty, SUM(-cost_delta) as tong_cost')
            ->groupBy('created_by_user_id')
            ->get();

        if ($tongHop->isEmpty()) {
            return [];
        }

        $ten = User::query()
            ->whereIn('id', $tongHop->pluck('created_by_user_id'))
            ->pluck('name', 'id');

        return $tongHop
            ->map(fn ($dong): array => [
                'user_id' => (int) $dong->created_by_user_id,
                'user_name' => $ten[$dong->created_by_user_id] ?? '(người dùng đã xoá)',
                'so_lan' => (int) $dong->so_lan,
                'tong_qty' => (int) $dong->tong_qty,
                'tong_cost' => (int) $dong->tong_cost,
            ])
            ->sortByDesc('tong_cost')
            ->values()
            ->all();
    }
}
