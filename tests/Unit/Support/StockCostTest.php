<?php

declare(strict_types=1);

use App\Exceptions\StockCostOverflowException;
use App\Support\StockCost;

// ── Tầng 1 — ví dụ chạy tay mục 3 của docs/thiet-ke-gia-von.md ────────────

it('ví dụ chạy tay: nhập 100@20.000, nhập 50@24.000, bán 30 ra đúng 640.000', function () {
    // Nhập 100 lon @ 20.000
    $qty = 100;
    $totalCost = 2_000_000;

    // Nhập 50 lon @ 24.000
    $qty += 50;
    $totalCost += 1_200_000;

    expect($qty)->toBe(150)->and($totalCost)->toBe(3_200_000);

    // Bán 30 lon
    $ketQua = StockCost::giaVonXuat($qty, $totalCost, 30);

    expect($ketQua['cost'])->toBe(640_000)
        ->and($ketQua['has_cost'])->toBeTrue();

    $conLai = $totalCost - $ketQua['cost'];
    expect($conLai)->toBe(2_560_000);

    // Giá trung bình trước và sau khi bán không đổi.
    $giaTbTruoc = intdiv($totalCost, $qty);
    $giaTbSau = intdiv($conLai, $qty - 30);
    expect($giaTbTruoc)->toBe($giaTbSau);
});

it('lamTron làm tròn nửa lên đúng công thức intdiv(2a+b, 2b)', function () {
    // 3.200.000 * 30 / 150 = 640.000 chẵn, không cần làm tròn.
    expect(StockCost::lamTron(3_200_000 * 30, 150))->toBe(640_000);

    // Trường hợp có phần lẻ ≥ 0.5: 10/3 = 3,33 → làm tròn thường 3,
    // nhưng lamTron(10,3) = intdiv(23,6) = 3.
    expect(StockCost::lamTron(10, 3))->toBe(3);

    // 5/2 = 2,5 → làm tròn nửa lên = 3: intdiv(15,4) = 3.
    expect(StockCost::lamTron(5, 2))->toBe(3);
});

// ── Tầng 2 — bảy trường hợp biên mục 6 của tài liệu ────────────────────────

it('trường hợp biên 1: xuất đúng bằng tồn thì total_cost về đúng 0, không đồng lẻ', function () {
    $ketQua = StockCost::giaVonXuat(qty: 100, totalCost: 2_137_000, n: 100);

    expect($ketQua['cost'])->toBe(2_137_000)
        ->and($ketQua['has_cost'])->toBeTrue();
});

it('trường hợp biên 2: xuất nhiều hơn tồn thì lấy hết vốn, has_cost = false', function () {
    $ketQua = StockCost::giaVonXuat(qty: 20, totalCost: 400_000, n: 50);

    expect($ketQua['cost'])->toBe(400_000)
        ->and($ketQua['has_cost'])->toBeFalse();
});

it('trường hợp biên 3: xuất khi tồn đang âm thì giá vốn 0, has_cost = false', function () {
    $ketQua = StockCost::giaVonXuat(qty: -10, totalCost: 0, n: 5);

    expect($ketQua['cost'])->toBe(0)
        ->and($ketQua['has_cost'])->toBeFalse();
});

it('trường hợp biên 4: tồn về 0 rồi nhập lại thì giá trung bình mới hoàn toàn, không nhớ giá cũ', function () {
    // Tồn đã về 0 (total_cost cũng đã về 0 theo K9), nhập lại 40 lon giá mới.
    $ketQuaXuatHetSach = StockCost::giaVonXuat(qty: 40, totalCost: 800_000, n: 40);
    expect($ketQuaXuatHetSach['cost'])->toBe(800_000);

    // Nhập lại — giá vốn nhập luôn là tiền thật (knownCost), không tính từ StockCost.
    // Ở đây chỉ xác nhận giá trung bình cũ không còn ảnh hưởng gì tới lần xuất sau.
    $qtySauKhiNhapLai = 0 + 40;
    $totalCostSauKhiNhapLai = 0 + 1_600_000; // giá mới, gấp đôi giá cũ

    $ketQuaXuatSau = StockCost::giaVonXuat($qtySauKhiNhapLai, $totalCostSauKhiNhapLai, 10);
    // Giá vốn tính hoàn toàn theo lô giá mới (40.000/lon), không dính giá cũ (20.000/lon).
    expect($ketQuaXuatSau['cost'])->toBe(400_000);
});

it('trường hợp biên 5: nhập khi tồn đang âm thì cộng bình thường, giá TB lệch cao tạm thời', function () {
    // Đây là hành vi của giaVonNhapTheoTrungBinh khi qty <= 0 (mục 5.1 trường hợp biên).
    $ketQua = StockCost::giaVonNhapTheoTrungBinh(qty: -5, totalCost: 0, n: 20);

    // Không có giá trung bình để dùng lúc tồn âm — giá vốn 0, has_cost = false,
    // cảnh báo cho chủ quán tự nhập giá tay nếu muốn (xử lý ở tầng Action).
    expect($ketQua['cost'])->toBe(0)
        ->and($ketQua['has_cost'])->toBeFalse();
});

it('trường hợp biên 6: kiểm kê thừa lúc qty <= 0 thì giá vốn 0, has_cost = false', function () {
    $ketQua = StockCost::giaVonNhapTheoTrungBinh(qty: 0, totalCost: 0, n: 15);

    expect($ketQua['cost'])->toBe(0)
        ->and($ketQua['has_cost'])->toBeFalse();
});

it('trường hợp biên 6b: kiểm kê thừa lúc qty > 0 thì giữ nguyên giá trung bình', function () {
    // 150 lon, giá TB 21.333,33 — thừa 10 lon phải giữ giá TB không đổi.
    $ketQua = StockCost::giaVonNhapTheoTrungBinh(qty: 150, totalCost: 3_200_000, n: 10);

    expect($ketQua['has_cost'])->toBeTrue();

    $qtySau = 150 + 10;
    $totalCostSau = 3_200_000 + $ketQua['cost'];
    $giaTbTruoc = intdiv(3_200_000, 150);
    $giaTbSau = intdiv($totalCostSau, $qtySau);
    expect($giaTbSau)->toBe($giaTbTruoc);
});

it('trường hợp biên 7: total_cost × n tràn số nguyên 64 bit thì ném lỗi rõ ràng', function () {
    StockCost::lamTron(PHP_INT_MAX, 2);
})->throws(StockCostOverflowException::class);

it('trường hợp biên 7b: giaVonXuat tràn số khi nhân total_cost với n', function () {
    StockCost::giaVonXuat(qty: 10, totalCost: PHP_INT_MAX, n: 5);
})->throws(StockCostOverflowException::class);

// ── Kiểm tra đầu vào không hợp lệ ──────────────────────────────────────────

it('lamTron chặn tử số âm', function () {
    StockCost::lamTron(-1, 10);
})->throws(InvalidArgumentException::class);

it('lamTron chặn mẫu số không dương', function () {
    StockCost::lamTron(10, 0);
})->throws(InvalidArgumentException::class);
