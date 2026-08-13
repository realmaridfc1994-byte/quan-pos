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
    // Bước 4B.0 — ba cột tiền của nhóm "thiếu giá vốn". Phải liệt kê tay: mẫu
    // \brevenue_amount\b KHÔNG khớp được item_revenue_amount vì gạch dưới cũng
    // là ký tự chữ, nên ba cột này lọt lưới nếu chỉ dựa vào cột cũ.
    'revenue_uncosted_amount',
    'item_revenue_amount',
    'item_revenue_uncosted_amount',
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

    // Cùng loại ngoại lệ với hai dòng trên: lãi gộp ÷ doanh thu ra TỈ LỆ LÃI để
    // xếp hạng món và in ra câu "con số lãi 32,5% đang cao hơn thực tế". Kết quả
    // không được lưu vào cột nào, không cộng vào số tiền nào, không chia tiền
    // cho ai. Dòng này chỉ hiện ra sau khi bộ quét được vá ngày 12/08 để nhìn cả
    // biến chứ không chỉ tên cột — trước đó nó lọt, không phải vì hợp lệ mà vì
    // cái lưới thủng.
    //
    // Ngoại lệ HẾT HIỆU LỰC ngay nếu tỉ lệ này được dùng để chia tiền thật (ví
    // dụ tính thưởng theo phần trăm) — lúc đó phải đổi sang số nguyên phần vạn.
    'Domain/Reporting/Queries/GetOwnerProfitDashboard.php' => [
        '$margin = $doanhThu > 0 ? $laiGop / $doanhThu : 0.0;',
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

/**
 * LỖ HỔNG CŨ CỦA BỘ QUÉT NÀY (vá 12/08, review Phase 3 Bước 10).
 *
 * Trước đây bộ quét chỉ nhìn xem DÒNG CODE CÓ NHẮC TÊN CỘT TIỀN hay không. Code
 * gán tiền vào một biến tên tiếng Việt rồi mới chia thì lọt qua sạch sẽ:
 *
 *     $doanhThu = (int) $dong->doanh_thu;     // doanh_thu là bí danh của revenue_amount
 *     $margin   = $laiGop / $doanhThu;        // không có chữ nào trong COT_TIEN → lọt
 *
 * Cái lưới thủng nguy hiểm hơn không có lưới, vì nó cho cảm giác an toàn rộng
 * hơn thứ nó thật sự bảo vệ. Giờ bộ quét đi theo ba bước cho từng file:
 *
 *   1. BÍ DANH: `SUM(revenue_amount) as doanh_thu` → `doanh_thu` cũng là tên tiền.
 *   2. BIẾN TIỀN: biến nào được gán từ một dòng có tên tiền thì chính nó là
 *      biến tiền, lan truyền cho tới khi không tìm thêm được biến nào nữa.
 *   3. Dòng nào có phép chia mà nhắc tới tên tiền hoặc biến tiền thì bị bắt.
 *
 * Giới hạn đã biết: bộ quét đọc từng DÒNG, nên một phép chia bị ngắt xuống hai
 * dòng sẽ lọt. Chấp nhận — mục đích của nó là chặn thói quen, không phải chứng
 * minh định lý.
 *
 * @param  list<string>  $dongMa
 * @return list<string> tên biến (không có dấu $) đang giữ số tiền
 */
function bienGiuTien(array $dongMa, string $cotTien): array
{
    $tenTien = [];

    // Bước 1 — bí danh trong selectRaw: `SUM(revenue_amount) as doanh_thu`.
    foreach ($dongMa as $dong) {
        if (preg_match_all("/\b(?:{$cotTien})\b[^,']*?\bas\s+(\w+)/i", $dong, $khop) === 0) {
            continue;
        }

        foreach ($khop[1] as $biDanh) {
            $tenTien[] = $biDanh;
        }
    }

    // Bước 2 — lan truyền sang biến, chạy lại cho tới khi không thêm được gì.
    $bienTien = [];

    do {
        $themDuoc = false;
        $moiTen = $tenTien === [] ? $cotTien : $cotTien.'|'.implode('|', array_unique($tenTien));
        $moiBien = $bienTien === [] ? null : implode('|', array_unique($bienTien));

        foreach ($dongMa as $dong) {
            if (preg_match('/\$(\w+)\s*=(?!=)/', $dong, $khopBien) !== 1) {
                continue;
            }

            $ten = $khopBien[1];
            if (in_array($ten, $bienTien, true)) {
                continue;
            }

            $vePhai = substr($dong, (int) strpos($dong, '=') + 1);

            $coTien = preg_match("/\b({$moiTen})\b/", $vePhai) === 1
                || ($moiBien !== null && preg_match("/\\\$({$moiBien})\b/", $vePhai) === 1);

            if ($coTien) {
                $bienTien[] = $ten;
                $themDuoc = true;
            }
        }
    } while ($themDuoc);

    return [...array_unique($tenTien), ...array_unique($bienTien)];
}

it('không có chỗ nào trong app/ chia số tiền bằng phép chia số thực', function () {
    $cotTien = implode('|', COT_TIEN);
    $viPham = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        $duongDan = str_replace('\\', '/', $file->getRelativePathname());

        $mienTru = DONG_MIEN_TRU[$duongDan] ?? [];
        $dongMa = explode("\n", boComment($file->getContents()));

        $tenTienThemVao = bienGiuTien($dongMa, $cotTien);
        $mauTien = $tenTienThemVao === []
            ? "/\b({$cotTien})\b/"
            : "/\b({$cotTien}|".implode('|', $tenTienThemVao).')\b/';

        foreach ($dongMa as $i => $dong) {
            // Có nhắc tới tiền không — tên cột, bí danh của cột, hay biến đang
            // giữ tiền?
            if (preg_match($mauTien, $dong) !== 1) {
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

it('bộ quét bắt được cả phép chia trên BIẾN đã gán từ cột tiền, không chỉ trên tên cột', function () {
    $cotTien = implode('|', COT_TIEN);

    $maSai = explode("\n", boComment(<<<'PHP'
        <?php
        $tong = $q->selectRaw('SUM(revenue_amount) as doanh_thu')->get();
        $doanhThu = (int) $tong->doanh_thu;
        $laiGop = $doanhThu - 1000;
        $margin = $laiGop / $doanhThu;
        PHP));

    $ten = bienGiuTien($maSai, $cotTien);

    // Bí danh và cả ba biến phải bị nhận là "đang giữ tiền".
    expect($ten)->toContain('doanh_thu')
        ->and($ten)->toContain('doanhThu')
        ->and($ten)->toContain('laiGop')
        ->and($ten)->toContain('margin');

    // Và dòng chia cuối cùng phải bị bắt.
    $mau = "/\b({$cotTien}|".implode('|', $ten).')\b/';
    expect(preg_match($mau, '$margin = $laiGop / $doanhThu;'))->toBe(1);
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
