<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Inventory\Enums\IngredientBaseUnit;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * 60 nguyên liệu thật của quán nhậu Việt Nam, kèm bảng quy đổi đơn vị.
 *
 * Chạy lại được nhiều lần, không xoá gì — chỉ updateOrCreate() theo cột định
 * danh ổn định (`code` cho ingredients, `name` cho suppliers, cặp
 * (ingredient_id, unit_name) cho ingredient_units). Đây là danh mục kho
 * (giống thực đơn), không phải dữ liệu giao dịch.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSuppliers();
        $this->seedIngredients();
    }

    private function seedSuppliers(): void
    {
        $nhaCungCap = [
            ['name' => 'Đại lý bia Sài Gòn - Quận 7', 'phone' => '0909111222'],
            ['name' => 'Vựa hải sản Bình Điền', 'phone' => '0909333444'],
            ['name' => 'Chợ đầu mối rau củ Hóc Môn', 'phone' => '0909555666'],
            ['name' => 'Lò gà ta Củ Chi', 'phone' => '0909777888'],
            ['name' => 'Đại lý gia vị Bình Tây', 'phone' => '0909999000'],
        ];

        foreach ($nhaCungCap as $ncc) {
            Supplier::query()->updateOrCreate(
                ['name' => $ncc['name']],
                ['phone' => $ncc['phone'], 'is_active' => true],
            );
        }
    }

    /**
     * Mỗi phần tử: mã, tên, nhóm, đơn vị gốc, mức cảnh báo tồn thấp, và danh
     * sách đơn vị quy đổi [tên đơn vị, factor, có phải đơn vị nhập mặc định].
     */
    private function seedIngredients(): void
    {
        $g = IngredientBaseUnit::Gram;
        $ml = IngredientBaseUnit::Mililit;
        $lon = IngredientBaseUnit::Lon;
        $cai = IngredientBaseUnit::Cai;

        $nguyenLieu = [
            // ── BIA - NƯỚC GIẢI KHÁT (12) ──────────────────────────────
            ['TIGER-LON', 'Bia Tiger lon', 'Bia rượu', $lon, 24, [['Thùng', 24, true]]],
            ['HEIN-LON', 'Bia Heineken lon', 'Bia rượu', $lon, 24, [['Thùng', 24, true]]],
            ['SGN-LON', 'Bia Sài Gòn Lager lon', 'Bia rượu', $lon, 24, [['Thùng', 24, true]]],
            ['SGNSPEC-LON', 'Bia Sài Gòn Special lon', 'Bia rượu', $lon, 24, [['Thùng', 24, true]]],
            ['333-LON', 'Bia 333 lon', 'Bia rượu', $lon, 24, [['Thùng', 24, true]]],
            ['BIAHOI', 'Bia hơi Hà Nội', 'Bia rượu', $ml, 5000, [['Lít', 1000, true], ['Vại', 500, false]]],
            ['RUOUDE', 'Rượu đế Gò Đen', 'Bia rượu', $ml, 2000, [['Chai', 500, true], ['Lít', 1000, false]]],
            ['VODKA', 'Rượu Vodka Hà Nội', 'Bia rượu', $ml, 700, [['Chai', 700, true]]],
            ['COCA-LON', 'Coca-Cola lon', 'Nước giải khát', $lon, 24, [['Thùng', 24, true]]],
            ['PEPSI-LON', 'Pepsi lon', 'Nước giải khát', $lon, 24, [['Thùng', 24, true]]],
            ['STING-LON', 'Nước tăng lực Sting', 'Nước giải khát', $lon, 24, [['Thùng', 24, true]]],
            ['SODA-CHAI', 'Nước soda chai', 'Nước giải khát', $lon, 24, [['Két', 24, true]]],

            // ── THỊT (10) ────────────────────────────────────────────────
            ['GA-TA', 'Gà ta', 'Thịt', $g, 2000, [['Kg', 1000, true], ['Con', 1200, false]]],
            ['GA-CN', 'Gà công nghiệp', 'Thịt', $g, 2000, [['Kg', 1000, true]]],
            ['THIT-BO', 'Thịt bò', 'Thịt', $g, 3000, [['Kg', 1000, true]]],
            ['HEO-BACHI', 'Thịt heo ba chỉ', 'Thịt', $g, 3000, [['Kg', 1000, true]]],
            ['HEO-SUON', 'Sườn heo', 'Thịt', $g, 3000, [['Kg', 1000, true]]],
            ['VIT-THIT', 'Thịt vịt', 'Thịt', $g, 2000, [['Kg', 1000, true]]],
            ['LONG-HEO', 'Lòng heo', 'Thịt', $g, 1000, [['Kg', 1000, true]]],
            ['CHAN-GA', 'Chân gà', 'Thịt', $g, 1000, [['Kg', 1000, true]]],
            ['CANH-GA', 'Cánh gà', 'Thịt', $g, 2000, [['Kg', 1000, true]]],
            ['XUC-XICH', 'Xúc xích', 'Thịt', $g, 1000, [['Kg', 1000, true]]],

            // ── HẢI SẢN (8) ──────────────────────────────────────────────
            ['TOM-SU', 'Tôm sú', 'Hải sản', $g, 2000, [['Kg', 1000, true]]],
            ['MUC-ONG', 'Mực ống', 'Hải sản', $g, 2000, [['Kg', 1000, true]]],
            ['NGHEU', 'Nghêu', 'Hải sản', $g, 3000, [['Kg', 1000, true]]],
            ['OC-HUONG', 'Ốc hương', 'Hải sản', $g, 2000, [['Kg', 1000, true]]],
            ['CA-BASA', 'Cá basa', 'Hải sản', $g, 2000, [['Kg', 1000, true]]],
            ['CA-DIEUHONG', 'Cá điêu hồng', 'Hải sản', $g, 2000, [['Kg', 1000, true]]],
            ['SO-HUYET', 'Sò huyết', 'Hải sản', $g, 2000, [['Kg', 1000, true]]],
            ['GHE', 'Ghẹ', 'Hải sản', $g, 2000, [['Kg', 1000, true]]],

            // ── RAU CỦ (12) ──────────────────────────────────────────────
            ['RAU-MUONG', 'Rau muống', 'Rau củ', $g, 1000, [['Bó', 300, true], ['Kg', 1000, false]]],
            ['NAM-KIMCHAM', 'Nấm kim châm', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],
            ['NAM-ROM', 'Nấm rơm', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],
            ['CAI-CAY', 'Cải cay', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],
            ['XA-LACH', 'Xà lách', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],
            ['DUA-LEO', 'Dưa leo', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],
            ['CA-CHUA', 'Cà chua', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],
            ['HANH-TAY', 'Hành tây', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],
            ['HANH-LA', 'Hành lá', 'Rau củ', $g, 500, [['Bó', 100, true], ['Kg', 1000, false]]],
            ['RAU-THOM', 'Rau thơm', 'Rau củ', $g, 300, [['Bó', 50, true], ['Kg', 1000, false]]],
            ['KHOAI-TAY', 'Khoai tây', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],
            ['BAP-CAI', 'Bắp cải', 'Rau củ', $g, 1000, [['Kg', 1000, true]]],

            // ── GIA VỊ - NƯỚC CHẤM (10) ─────────────────────────────────
            ['NUOC-MAM', 'Nước mắm', 'Gia vị', $ml, 1000, [['Chai', 500, true], ['Lít', 1000, false]]],
            ['NUOC-TUONG', 'Nước tương', 'Gia vị', $ml, 500, [['Chai', 500, true]]],
            ['TUONG-OT', 'Tương ớt', 'Gia vị', $ml, 500, [['Chai', 250, true]]],
            ['DAU-AN', 'Dầu ăn', 'Gia vị', $ml, 1000, [['Lít', 1000, true]]],
            ['MUOI-HAT', 'Muối hạt', 'Gia vị', $g, 1000, [['Kg', 1000, true]]],
            ['DUONG-CAT', 'Đường cát', 'Gia vị', $g, 1000, [['Kg', 1000, true]]],
            ['TIEU-XAY', 'Tiêu xay', 'Gia vị', $g, 200, [['Gói', 100, true]]],
            ['TOI', 'Tỏi', 'Gia vị', $g, 500, [['Kg', 1000, true]]],
            ['OT-TUOI', 'Ớt tươi', 'Gia vị', $g, 300, [['Kg', 1000, true]]],
            ['SA-CAY', 'Sả cây', 'Gia vị', $g, 300, [['Kg', 1000, true]]],

            // ── ĐỒ ĂN KÈM - KHÁC (8) ────────────────────────────────────
            ['DAU-PHONG', 'Đậu phộng rang', 'Đồ khô', $g, 500, [['Kg', 1000, true]]],
            ['BANH-TRANG', 'Bánh tráng', 'Đồ khô', $cai, 50, [['Ram', 100, true]]],
            ['MI-GOI', 'Mì gói ăn kèm', 'Đồ khô', $cai, 20, [['Thùng', 30, true]]],
            ['CHANH-TUOI', 'Chanh tươi', 'Đồ khô', $g, 500, [['Kg', 1000, true]]],
            ['NUOC-DA', 'Nước đá cây', 'Đồ khô', $g, 8000, [['Cây', 4000, true], ['Bao', 5000, false]]],
            ['TRA-KHO', 'Trà khô', 'Đồ khô', $g, 200, [['Kg', 1000, true]]],
            ['DAU-XANH', 'Đậu xanh cà vỏ', 'Đồ khô', $g, 500, [['Kg', 1000, true]]],
            ['BUN-TUOI', 'Bún tươi', 'Đồ khô', $g, 1000, [['Kg', 1000, true]]],
        ];

        foreach ($nguyenLieu as [$code, $name, $category, $baseUnit, $minQty, $donViQuyDoi]) {
            $ingredient = Ingredient::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'base_unit' => $baseUnit,
                    'min_qty' => $minQty,
                    'is_active' => true,
                ],
            );

            foreach ($donViQuyDoi as [$unitName, $factor, $laMacDinh]) {
                $ingredient->units()->updateOrCreate(
                    ['unit_name' => $unitName],
                    ['factor' => $factor, 'is_purchase_default' => $laMacDinh],
                );
            }
        }
    }
}
