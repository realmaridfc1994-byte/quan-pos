<?php

declare(strict_types=1);

use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Support\StockMovementUuid;
use Symfony\Component\Finder\Finder;

/**
 * Test tự chặn tái phát (Bước 10) — mọi lời gọi ghi sổ cái kho trong app/ đều
 * phải mang mã vân tay.
 *
 * Vì sao quét văn bản chứ không kiểm hành vi như OnlyOneStockWriterTest: tham
 * số uuid là bắt buộc trong DTO nên PHP đã tự bắt lỗi thiếu — nhưng nó KHÔNG
 * bắt được kiểu "truyền đại (string) Str::uuid() ngay tại chỗ gọi", vốn là
 * cách sai phổ biến nhất (mỗi lần bấm lại ra một mã mới, chẳng chống được gì).
 * Quét văn bản là cách duy nhất nhìn thấy được điều đó.
 */

/** @return array<string, string> đường dẫn tương đối => nội dung file */
function docFileTrongApp(): array
{
    $files = [];

    foreach (Finder::create()->files()->in(base_path('app'))->name('*.php') as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

it('mọi lời gọi RecordStockMovement trong app/ đều truyền mã vân tay', function () {
    $thieu = [];

    foreach (docFileTrongApp() as $duongDan => $noiDung) {
        $doan = explode('new RecordStockMovementData(', $noiDung);
        array_shift($doan);

        foreach ($doan as $mot) {
            $thamSo = explode('));', $mot, 2)[0];

            if (! str_contains($thamSo, 'uuid:')) {
                $thieu[] = $duongDan;
            }
        }
    }

    expect($thieu)->toBe([]);
});

it('không chỗ nào trong app/ sinh mã vân tay ngẫu nhiên ngay tại chỗ ghi sổ cái', function () {
    $sai = [];

    foreach (docFileTrongApp() as $duongDan => $noiDung) {
        $doan = explode('new RecordStockMovementData(', $noiDung);
        array_shift($doan);

        foreach ($doan as $mot) {
            $thamSo = explode('));', $mot, 2)[0];

            // Str::uuid() ngay tại chỗ gọi nghĩa là mỗi lần bấm lại ra một mã
            // khác nhau — đúng cái mà mã vân tay sinh ra để chống.
            if (preg_match('/uuid:\s*\(string\)\s*Str::uuid\(\)/', $thamSo) === 1) {
                $sai[] = $duongDan;
            }
        }
    }

    expect($sai)->toBe([]);
});

it('mã vân tay tất định sinh lại từ cùng chứng từ luôn cho ra cùng một mã', function () {
    $lanDau = StockMovementUuid::tuChungTu(StockMovementRefType::OrderItem, 42, 7);
    $lanHai = StockMovementUuid::tuChungTu(StockMovementRefType::OrderItem, 42, 7);

    expect($lanHai)->toBe($lanDau)
        ->and($lanDau)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('chứng từ khác nhau thì mã vân tay khác nhau — không gộp nhầm hai dòng sổ cái', function () {
    $dongMon = StockMovementUuid::tuChungTu(StockMovementRefType::OrderItem, 42, 7);
    $khacNguyenLieu = StockMovementUuid::tuChungTu(StockMovementRefType::OrderItem, 42, 8);
    $khacChungTu = StockMovementUuid::tuChungTu(StockMovementRefType::PurchaseItem, 42, 7);

    expect($dongMon)->not->toBe($khacNguyenLieu)
        ->and($dongMon)->not->toBe($khacChungTu);
});
