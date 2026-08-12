<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Test tự chặn tái phát — CLAUDE.md mục 4.7 và 4.8:
 * "Mọi số tiền là số nguyên đơn vị đồng... Tuyệt đối không bao giờ dùng float."
 *
 * Phép chia `/` của PHP LUÔN đổi hai số nguyên thành số thực, kể cả khi chia
 * hết. Số thực chỉ giữ đúng được khoảng 15-16 chữ số, nên chia tiền kiểu đó
 * đúng nhờ số còn nhỏ, không đúng nhờ thiết kế. Đường duy nhất được phép:
 * App\Support\StockCost::lamTron() (làm tròn nửa lên bằng số nguyên) hoặc
 * intdiv().
 *
 * (Bug cũ: EstimateVariantCost dùng round($balance->total_cost / $balance->qty)
 * — phát hiện ở review Phase 3 mục 8.2-F, sửa 12/08.)
 *
 * ── CỘT NÀO ĐƯỢC COI LÀ "CỘT TIỀN" ───────────────────────────────────────
 * Danh sách dưới đây là thứ DUY NHẤT cần sửa khi schema có thêm cột tiền mới.
 * Quy ước đặt tên ở CLAUDE.md mục 4.15 là hậu tố `_amount`, nhưng nhóm kho ra
 * đời sau dùng thêm `_cost`, nên phải liệt kê tay cả hai họ.
 */
const COT_TIEN = [
    // Nhóm kho (Phase 3) — họ _cost
    'total_cost',
    'cost_delta',
    'cost_after',
    'line_cost',
    'unit_cost',
    'total_diff_cost',
    'cogs_amount',

    // Nhóm bán hàng (Phase 1-2) — họ _amount
    'total_amount',
    'subtotal_amount',
    'discount_amount',
    'paid_amount',
    'tendered_amount',
    'change_amount',
    'revenue_amount',
    'profit_amount',
    'unit_price',
    'line_amount',
    'expected_cash_amount',
    'counted_cash_amount',
];

/**
 * Dòng code được miễn trừ, kèm lý do. Miễn theo TỪNG DÒNG chứ không theo cả
 * file: sửa dòng đó một chữ là miễn trừ hết hiệu lực và test đỏ lại, buộc
 * người sửa phải đọc lại lý do. Mỗi dòng ở đây là một khoản nợ phải giải
 * thích được — đừng thêm vào chỉ để test xanh lại.
 *
 * @var array<string, list<string>> đường dẫn tương đối trong app/ => các dòng code (đã trim)
 */
const DONG_MIEN_TRU = [
    // Tiền ÷ tiền ra TỈ LỆ PHẦN TRĂM, kết quả không phải số tiền mà là một con
    // số để so với ngưỡng và in ra "chiếm 12% doanh thu". Sai một phần nghìn
    // phần trăm ở đây không làm lệch một đồng nào trên sổ sách.
    'Domain/Reporting/Queries/GetOwnerDashboard.php' => [
        'if ($d->revenue_amount > 0 && ($d->cancelled_item_amount / $d->revenue_amount) >= self::NGUONG_TI_LE_HUY) {',
        '$tiLe = round($d->cancelled_item_amount / $d->revenue_amount * 100);',
    ],
];

/** Bỏ mọi comment, giữ nguyên số dòng, để chữ trong comment không báo động giả. */
function boComment(string $ma): string
{
    $ketQua = '';

    foreach (token_get_all($ma) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            // Giữ lại đúng số xuống dòng để số dòng báo lỗi vẫn khớp file thật.
            $ketQua .= str_repeat("\n", substr_count($token[1], "\n"));

            continue;
        }

        $ketQua .= is_array($token) ? $token[1] : $token;
    }

    return $ketQua;
}

it('không có chỗ nào trong app/ chia số tiền bằng phép chia số thực', function () {
    $cotTien = implode('|', COT_TIEN);
    $viPham = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        $duongDan = str_replace('\\', '/', $file->getRelativePathname());

        $mienTru = DONG_MIEN_TRU[$duongDan] ?? [];
        $dongMa = explode("\n", boComment($file->getContents()));

        foreach ($dongMa as $i => $dong) {
            // Có nhắc tới cột tiền không?
            if (preg_match("/\b({$cotTien})\b/", $dong) !== 1) {
                continue;
            }

            // Có phép chia không? Loại trừ `//`, `/*`, `*/` (đã bỏ comment rồi
            // nhưng vẫn giữ cho chắc) và dấu `/` trong chuỗi đường dẫn.
            if (preg_match('#(?<![/*])/(?![/*])#', $dong) !== 1) {
                continue;
            }

            if (in_array(trim($dong), $mienTru, true)) {
                continue;
            }

            $viPham[] = "app/{$duongDan}:".($i + 1).' → '.trim($dong);
        }
    }

    expect($viPham)->toBe([], "Chia tiền bằng phép chia số thực. Dùng StockCost::lamTron() thay thế:\n".implode("\n", $viPham));
});

it('bản thân bộ quét bắt được đúng kiểu code sai', function () {
    // Chứng minh test trên không phải cái lưới thủng: dựng lại đúng dòng code
    // cũ của EstimateVariantCost và kiểm nó bị bắt.
    $maSai = boComment('<?php $x = round($balance->total_cost / $balance->qty);');
    $cotTien = implode('|', COT_TIEN);

    expect(preg_match("/\b({$cotTien})\b/", $maSai))->toBe(1)
        ->and(preg_match('#(?<![/*])/(?![/*])#', $maSai))->toBe(1);

    // Và không bắt nhầm dòng đã sửa đúng.
    $maDung = boComment('<?php $x = StockCost::lamTron($balance->total_cost, $balance->qty);');
    expect(preg_match('#(?<![/*])/(?![/*])#', $maDung))->toBe(0);
});
