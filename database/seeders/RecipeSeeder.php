<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Định lượng (recipes) cho các món đã có trong DatabaseSeeder, khớp với 60
 * nguyên liệu của InventorySeeder.
 *
 * Bia lon (Tiger/Heineken/Saigon) đi qua ĐÚNG một đoạn code với món nấu —
 * không có "trừ thẳng" riêng, đúng nguyên tắc ở docs/schema.md K.1: "1 lon
 * Tiger" là một dòng recipes (qty_base = 1), "Thùng" là một dòng (qty_base = 24).
 *
 * Món không tìm được nguyên liệu khớp trong 60 nguyên liệu đã seed (tráng
 * miệng, vài món hải sản/thịt hiếm) CHỦ Ý bỏ qua — deducts_stock giữ false,
 * xem docs/viec-ton.md.
 *
 * Chạy lại được nhiều lần: updateOrCreate theo (product_variant_id,
 * ingredient_id) — đúng cột UNIQUE thật của recipes.
 */
class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $ingredientMap = Ingredient::query()->pluck('id', 'code');

        // ── Bia lon: mỗi biến thể một dòng recipes, KHÔNG dùng cơ chế riêng ──
        $biaLon = [
            'TIGER' => 'TIGER-LON',
            'HEIN' => 'HEIN-LON',
            'SGN' => 'SGN-LON',
        ];

        foreach ($biaLon as $productCode => $ingredientCode) {
            $product = Product::query()->where('code', $productCode)->first();
            if ($product === null) {
                continue;
            }

            $ingredientId = $ingredientMap->get($ingredientCode);
            if ($ingredientId === null) {
                continue;
            }

            $this->datVaGanDinhLuong($product, 'Mặc định', [[$ingredientCode, 1]], $ingredientMap);
            $this->datVaGanDinhLuong($product, 'Lon', [[$ingredientCode, 1]], $ingredientMap);
            $this->datVaGanDinhLuong($product, 'Chai', [[$ingredientCode, 1]], $ingredientMap);
            $this->datVaGanDinhLuong($product, 'Thùng', [[$ingredientCode, 24]], $ingredientMap);
        }

        // ── Các món còn lại: định lượng cho biến thể "Mặc định" ──────────
        $dinhLuongMon = [
            'NCNGOT' => [['COCA-LON', 1]],
            'TRADA' => [['TRA-KHO', 10], ['NUOC-DA', 200]],
            'RUOUDE' => [['RUOUDE', 500]],

            'DAUPH' => [['DAU-PHONG', 150]],
            'MUCNN' => [['MUC-ONG', 200]],
            'GOICUON' => [['BANH-TRANG', 6], ['TOM-SU', 100], ['BUN-TUOI', 100], ['RAU-THOM', 30]],
            'GALUOC' => [['GA-TA', 500]],
            'CHANGA' => [['CHAN-GA', 300], ['CA-CHUA', 100]],
            'TOMHAP' => [['TOM-SU', 300]],

            'GANUOI' => [['GA-TA', 500], ['MUOI-HAT', 10]],
            'THBO' => [['THIT-BO', 250]],
            'TOMNUONG' => [['TOM-SU', 250]],
            'CANUONG' => [['CA-DIEUHONG', 400]],
            'XIENTC' => [['HEO-BACHI', 150], ['GA-TA', 150]],
            'CANHGA' => [['CANH-GA', 300]],
            'MUCNUONG' => [['MUC-ONG', 250]],

            'LAUTOM' => [['TOM-SU', 300], ['NAM-KIMCHAM', 100], ['RAU-MUONG', 100]],
            'LAUCUA' => [['GHE', 400], ['CA-CHUA', 100]],
            'LAUBO' => [['THIT-BO', 300], ['RAU-MUONG', 150]],
            'LAUGA' => [['GA-TA', 800], ['NAM-KIMCHAM', 150]],
            'LAUHS' => [['TOM-SU', 150], ['MUC-ONG', 150], ['NGHEU', 150], ['CA-BASA', 150]],
            'LAUMAM' => [['CA-BASA', 300], ['RAU-MUONG', 100]],
            'LAURIEU' => [['GHE', 200], ['CA-CHUA', 150], ['BAP-CAI', 100]],
            'LAUCC' => [['GHE', 400], ['CA-CHUA', 200]],

            'TOMSU' => [['TOM-SU', 400]],
            'MUCCC' => [['MUC-ONG', 250], ['OT-TUOI', 20]],
            'SONUONG' => [['SO-HUYET', 300]],
            'GHEXAO' => [['GHE', 300], ['DUA-LEO', 100]],

            'COMTAM' => [['HEO-SUON', 200]],
            'COMCHIEN' => [['XUC-XICH', 50], ['CA-CHUA', 30]],
            'PHO' => [['THIT-BO', 150], ['HANH-TAY', 30]],
            'BUNBO' => [['THIT-BO', 150], ['BUN-TUOI', 200]],
            'MIXAO' => [['GA-TA', 150], ['MI-GOI', 2]],
            'BANHMI' => [['HEO-BACHI', 100]],
            'SUPCUA' => [['GHE', 150]],

            'CANHCHUA' => [['TOM-SU', 150], ['CA-CHUA', 100], ['DUA-LEO', 50]],
            'DUIRAU' => [['CAI-CAY', 200]],
            'RAUMUONG' => [['RAU-MUONG', 250]],
            'CANHCUA' => [['GHE', 150]],

            'CHEBAMAU' => [['DAU-XANH', 50], ['DUONG-CAT', 30]],
        ];

        foreach ($dinhLuongMon as $productCode => $donDinhLuong) {
            $product = Product::query()->where('code', $productCode)->first();
            if ($product === null) {
                continue;
            }

            $this->datVaGanDinhLuong($product, 'Mặc định', $donDinhLuong, $ingredientMap);
        }
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $donDinhLuong  [mã nguyên liệu, số lượng theo đơn vị gốc]
     * @param  Collection<string, int>  $ingredientMap
     */
    private function datVaGanDinhLuong(Product $product, string $tenBienThe, array $donDinhLuong, $ingredientMap): void
    {
        $bienThe = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('name', $tenBienThe)
            ->first();

        if ($bienThe === null) {
            return;
        }

        if (! $bienThe->deducts_stock) {
            $bienThe->update(['deducts_stock' => true]);
        }

        foreach ($donDinhLuong as [$ingredientCode, $qtyBase]) {
            $ingredientId = $ingredientMap->get($ingredientCode);
            if ($ingredientId === null) {
                continue;
            }

            Recipe::query()->updateOrCreate(
                ['product_variant_id' => $bienThe->id, 'ingredient_id' => $ingredientId],
                ['qty_base' => $qtyBase],
            );
        }
    }
}
