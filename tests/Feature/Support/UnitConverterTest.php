<?php

declare(strict_types=1);

use App\Domain\Inventory\Enums\IngredientBaseUnit;
use App\Domain\Inventory\Models\Ingredient;
use App\Support\UnitConverter;

beforeEach(function () {
    $this->converter = new UnitConverter;
});

it('quy đổi 5 thùng bia (24 lon/thùng) ra đúng 120 lon', function () {
    $bia = Ingredient::factory()
        ->baseUnit(IngredientBaseUnit::Lon)
        ->create();
    $bia->units()->create(['unit_name' => 'Thùng', 'factor' => 24]);

    expect($this->converter->toBaseQty($bia, 'Thùng', 5))->toBe(120);
});

it('quy đổi 2,5 kg gà ra đúng 2500 gam', function () {
    $ga = Ingredient::factory()
        ->baseUnit(IngredientBaseUnit::Gram)
        ->create();
    $ga->units()->create(['unit_name' => 'Kg', 'factor' => 1000]);

    expect($this->converter->toBaseQty($ga, 'Kg', 2.5))->toBe(2500);
});

it('quy đổi thùng thẳng sang ml khớp với quy đổi lon rồi nhân lại — không bắc cầu nhưng dữ liệu nhất quán', function () {
    // Bia đóng chai 330ml: base_unit = ml. "Lon" và "Thùng" đều ghi thẳng
    // factor ra ml (KHÔNG bắc cầu Thùng → Lon → ml trong bộ chuyển đổi),
    // nhưng người nhập dữ liệu phải tự giữ hai factor nhất quán với nhau.
    $bia = Ingredient::factory()
        ->baseUnit(IngredientBaseUnit::Mililit)
        ->create();
    $bia->units()->create(['unit_name' => 'Lon', 'factor' => 330]);
    $bia->units()->create(['unit_name' => 'Thùng', 'factor' => 330 * 24]);

    $quyDoiThangThung = $this->converter->toBaseQty($bia, 'Thùng', 1);
    $quyDoiQuaLon = $this->converter->toBaseQty($bia, 'Lon', 24);

    expect($quyDoiThangThung)->toBe($quyDoiQuaLon)->toBe(7920);
});

it('không có đường quy đổi thì ném lỗi tiếng Việt, không trả về 0', function () {
    $ga = Ingredient::factory()
        ->baseUnit(IngredientBaseUnit::Gram)
        ->create(['name' => 'Gà ta test']);

    expect(fn () => $this->converter->toBaseQty($ga, 'Rổ', 3))
        ->toThrow(InvalidArgumentException::class, 'Không có đường quy đổi từ đơn vị "Rổ" sang đơn vị gốc "Gam" của nguyên liệu "Gà ta test".');
});

it('quy đổi ra số lẻ thì làm tròn đến số nguyên gần nhất, không cắt bỏ phần lẻ', function () {
    $ga = Ingredient::factory()
        ->baseUnit(IngredientBaseUnit::Gram)
        ->create();
    $ga->units()->create(['unit_name' => 'Kg', 'factor' => 1000]);

    // 1/3 kg = 333,333... gam → làm tròn thành 333 (round(), không phải floor())
    expect($this->converter->toBaseQty($ga, 'Kg', 1 / 3))->toBe(333);

    // 2/3 kg = 666,666... gam → làm tròn thành 667, chứng minh không phải floor() luôn
    expect($this->converter->toBaseQty($ga, 'Kg', 2 / 3))->toBe(667);
});

it('quy đổi thẳng bằng đơn vị gốc thì factor là 1, không cần dòng ingredient_units', function () {
    $ga = Ingredient::factory()
        ->baseUnit(IngredientBaseUnit::Gram)
        ->create();

    expect($this->converter->toBaseQty($ga, 'g', 500))->toBe(500);
});

it('số lượng âm thì ném lỗi ngay, không quy đổi', function () {
    $ga = Ingredient::factory()
        ->baseUnit(IngredientBaseUnit::Gram)
        ->create();
    $ga->units()->create(['unit_name' => 'Kg', 'factor' => 1000]);

    expect(fn () => $this->converter->toBaseQty($ga, 'Kg', -1))
        ->toThrow(InvalidArgumentException::class);
});
