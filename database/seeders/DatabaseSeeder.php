<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────────────────────────
        // 1. NGƯỜI DÙNG (4 người)
        // ──────────────────────────────────────────────────────────────
        User::query()->where('phone', '0900000001')->delete();
        User::query()->where('phone', '0900000002')->delete();
        User::query()->where('phone', '0900000003')->delete();
        User::query()->where('phone', '0900000004')->delete();

        User::factory()->create([
            'name' => 'Chủ quán',
            'username' => 'owner',
            'phone' => '0900000001',
            'password' => Hash::make('password'),
            'pin_code' => Hash::make('1234'),
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Quản lý',
            'username' => 'manager',
            'phone' => '0900000002',
            'password' => Hash::make('password'),
            'pin_code' => Hash::make('1234'),
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Phục vụ',
            'username' => 'staff',
            'phone' => '0900000003',
            'password' => Hash::make('password'),
            'pin_code' => Hash::make('1234'),
            'role' => UserRole::Staff,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Bếp',
            'username' => 'kitchen',
            'phone' => '0900000004',
            'password' => Hash::make('password'),
            'pin_code' => Hash::make('1234'),
            'role' => UserRole::Kitchen,
            'is_active' => true,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 2. BÀN (12 bàn)
        // ──────────────────────────────────────────────────────────────
        DiningTable::query()->delete();

        // 8 bàn trong nhà (B01-B08), 4 chỗ
        for ($i = 1; $i <= 8; $i++) {
            DiningTable::factory()->indoorTable($i)->create();
        }

        // 4 bàn sân (S01-S04), 6 chỗ
        for ($i = 1; $i <= 4; $i++) {
            DiningTable::factory()->outsideTable($i)->create();
        }

        // ──────────────────────────────────────────────────────────────
        // 3. NHÓM MÓN (8 nhóm)
        // ──────────────────────────────────────────────────────────────
        Category::query()->delete();

        $categories = [
            ['name' => 'Bia & nước', 'station' => Station::Bar, 'sort_order' => 1],
            ['name' => 'Đồ nhắm', 'station' => Station::Kitchen, 'sort_order' => 2],
            ['name' => 'Nướng', 'station' => Station::Kitchen, 'sort_order' => 3],
            ['name' => 'Lẩu', 'station' => Station::Kitchen, 'sort_order' => 4],
            ['name' => 'Hải sản', 'station' => Station::Kitchen, 'sort_order' => 5],
            ['name' => 'Món chính', 'station' => Station::Kitchen, 'sort_order' => 6],
            ['name' => 'Rau & canh', 'station' => Station::Kitchen, 'sort_order' => 7],
            ['name' => 'Tráng miệng', 'station' => Station::Kitchen, 'sort_order' => 8],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['name']] = Category::factory()->create([
                'name' => $cat['name'],
                'station' => $cat['station'],
                'sort_order' => $cat['sort_order'],
            ]);
        }

        // ──────────────────────────────────────────────────────────────
        // 4. MÓN (60 món)
        // ──────────────────────────────────────────────────────────────
        Product::query()->delete();
        ProductVariant::query()->delete();
        OptionGroup::query()->delete();
        Option::query()->delete();

        $products = [
            // Bia & nước (8 món)
            ['name' => 'Bia Tiger', 'category' => 'Bia & nước', 'code' => 'TIGER', 'cost' => 8000, 'hasOptions' => true],
            ['name' => 'Bia Heineken', 'category' => 'Bia & nước', 'code' => 'HEIN', 'cost' => 9000, 'hasOptions' => true],
            ['name' => 'Bia Saigon', 'category' => 'Bia & nước', 'code' => 'SGN', 'cost' => 7500, 'hasOptions' => true],
            ['name' => 'Nước ngọt', 'category' => 'Bia & nước', 'code' => 'NCNGOT', 'cost' => 2000, 'hasOptions' => true],
            ['name' => 'Nước suối', 'category' => 'Bia & nước', 'code' => 'NSUOI', 'cost' => 1500, 'hasOptions' => false],
            ['name' => 'Cà phê đen', 'category' => 'Bia & nước', 'code' => 'CADENM', 'cost' => 3000, 'hasOptions' => false],
            ['name' => 'Trà đá', 'category' => 'Bia & nước', 'code' => 'TRADA', 'cost' => 1000, 'hasOptions' => false],
            ['name' => 'Rượu đế', 'category' => 'Bia & nước', 'code' => 'RUOUDE', 'cost' => 6000, 'hasOptions' => false],

            // Đồ nhắm (8 món)
            ['name' => 'Đậu phộng rang', 'category' => 'Đồ nhắm', 'code' => 'DAUPH', 'cost' => 5000, 'hasOptions' => false],
            ['name' => 'Mực một nắng', 'category' => 'Đồ nhắm', 'code' => 'MUCNN', 'cost' => 12000, 'hasOptions' => false],
            ['name' => 'Gỏi cuốn', 'category' => 'Đồ nhắm', 'code' => 'GOICUON', 'cost' => 4000, 'hasOptions' => false],
            ['name' => 'Gà luộc', 'category' => 'Đồ nhắm', 'code' => 'GALUOC', 'cost' => 6000, 'hasOptions' => true],
            ['name' => 'Chân gà cà chua', 'category' => 'Đồ nhắm', 'code' => 'CHANGA', 'cost' => 5500, 'hasOptions' => false],
            ['name' => 'Thịt xông khói', 'category' => 'Đồ nhắm', 'code' => 'THITXK', 'cost' => 8000, 'hasOptions' => false],
            ['name' => 'Tôm tươi hấp', 'category' => 'Đồ nhắm', 'code' => 'TOMHAP', 'cost' => 10000, 'hasOptions' => false],
            ['name' => 'Nhum biển', 'category' => 'Đồ nhắm', 'code' => 'NUMBIEN', 'cost' => 15000, 'hasOptions' => false],

            // Nướng (8 món)
            ['name' => 'Gà nướng muối ớt', 'category' => 'Nướng', 'code' => 'GANUOI', 'cost' => 8000, 'hasOptions' => true],
            ['name' => 'Thịt bò nướng', 'category' => 'Nướng', 'code' => 'THBO', 'cost' => 12000, 'hasOptions' => true],
            ['name' => 'Tôm nướng', 'category' => 'Nướng', 'code' => 'TOMNUONG', 'cost' => 10000, 'hasOptions' => true],
            ['name' => 'Cá nướng', 'category' => 'Nướng', 'code' => 'CANUONG', 'cost' => 11000, 'hasOptions' => true],
            ['name' => 'Xiên nướng thập cẩm', 'category' => 'Nướng', 'code' => 'XIENTC', 'cost' => 9000, 'hasOptions' => true],
            ['name' => 'Lươn nướng', 'category' => 'Nướng', 'code' => 'LUON', 'cost' => 13000, 'hasOptions' => false],
            ['name' => 'Cánh gà nướng', 'category' => 'Nướng', 'code' => 'CANHGA', 'cost' => 7000, 'hasOptions' => true],
            ['name' => 'Mực nướng', 'category' => 'Nướng', 'code' => 'MUCNUONG', 'cost' => 11000, 'hasOptions' => true],

            // Lẩu (8 món)
            ['name' => 'Lẩu tôm', 'category' => 'Lẩu', 'code' => 'LAUTOM', 'cost' => 15000, 'hasOptions' => false],
            ['name' => 'Lẩu cua', 'category' => 'Lẩu', 'code' => 'LAUCUA', 'cost' => 18000, 'hasOptions' => false],
            ['name' => 'Lẩu bò', 'category' => 'Lẩu', 'code' => 'LAUBO', 'cost' => 16000, 'hasOptions' => false],
            ['name' => 'Lẩu gà', 'category' => 'Lẩu', 'code' => 'LAUGA', 'cost' => 14000, 'hasOptions' => false],
            ['name' => 'Lẩu hải sản', 'category' => 'Lẩu', 'code' => 'LAUHS', 'cost' => 22000, 'hasOptions' => false],
            ['name' => 'Lẩu mắm tomyum', 'category' => 'Lẩu', 'code' => 'LAUMAM', 'cost' => 17000, 'hasOptions' => false],
            ['name' => 'Lẩu riêu', 'category' => 'Lẩu', 'code' => 'LAURIEU', 'cost' => 15000, 'hasOptions' => false],
            ['name' => 'Lẩu cua cà chua', 'category' => 'Lẩu', 'code' => 'LAUCC', 'cost' => 19000, 'hasOptions' => false],

            // Hải sản (8 món)
            ['name' => 'Tôm sú hấp muối', 'category' => 'Hải sản', 'code' => 'TOMSU', 'cost' => 20000, 'hasOptions' => false],
            ['name' => 'Cua hoàng đế', 'category' => 'Hải sản', 'code' => 'CUAHD', 'cost' => 25000, 'hasOptions' => false],
            ['name' => 'Mực ống xào chua cay', 'category' => 'Hải sản', 'code' => 'MUCCC', 'cost' => 12000, 'hasOptions' => false],
            ['name' => 'Cá hồi nướng', 'category' => 'Hải sản', 'code' => 'CAHOI', 'cost' => 18000, 'hasOptions' => false],
            ['name' => 'Sò nướng', 'category' => 'Hải sản', 'code' => 'SONUONG', 'cost' => 14000, 'hasOptions' => false],
            ['name' => 'Mít tôm', 'category' => 'Hải sản', 'code' => 'MITTOM', 'cost' => 16000, 'hasOptions' => false],
            ['name' => 'Ghẹ xào với dưa leo', 'category' => 'Hải sản', 'code' => 'GHEXAO', 'cost' => 13000, 'hasOptions' => false],
            ['name' => 'Cá chép sốt chuối', 'category' => 'Hải sản', 'code' => 'CACHEP', 'cost' => 11000, 'hasOptions' => false],

            // Món chính (8 món)
            ['name' => 'Cơm tấm sườn cốt lết', 'category' => 'Món chính', 'code' => 'COMTAM', 'cost' => 6500, 'hasOptions' => false],
            ['name' => 'Cơm chiên dương châu', 'category' => 'Món chính', 'code' => 'COMCHIEN', 'cost' => 5500, 'hasOptions' => true],
            ['name' => 'Phở bò', 'category' => 'Món chính', 'code' => 'PHO', 'cost' => 4500, 'hasOptions' => true],
            ['name' => 'Bún bò Huế', 'category' => 'Món chính', 'code' => 'BUNBO', 'cost' => 5000, 'hasOptions' => false],
            ['name' => 'Mì xào gà', 'category' => 'Món chính', 'code' => 'MIXAO', 'cost' => 4500, 'hasOptions' => true],
            ['name' => 'Bánh mì thịt nạc', 'category' => 'Món chính', 'code' => 'BANHMI', 'cost' => 3500, 'hasOptions' => true],
            ['name' => 'Cơm bình dân', 'category' => 'Món chính', 'code' => 'COMBK', 'cost' => 4000, 'hasOptions' => false],
            ['name' => 'Súp cua', 'category' => 'Món chính', 'code' => 'SUPCUA', 'cost' => 5500, 'hasOptions' => false],

            // Rau & canh (6 món)
            ['name' => 'Canh chua tôm', 'category' => 'Rau & canh', 'code' => 'CANHCHUA', 'cost' => 4000, 'hasOptions' => false],
            ['name' => 'Dúi rau cải', 'category' => 'Rau & canh', 'code' => 'DUIRAU', 'cost' => 3000, 'hasOptions' => false],
            ['name' => 'Rau muống luộc', 'category' => 'Rau & canh', 'code' => 'RAUMUONG', 'cost' => 2500, 'hasOptions' => false],
            ['name' => 'Canh tảo cua', 'category' => 'Rau & canh', 'code' => 'CANHCUA', 'cost' => 4500, 'hasOptions' => false],
            ['name' => 'Đậu bắp luộc', 'category' => 'Rau & canh', 'code' => 'DAUBAP', 'cost' => 2500, 'hasOptions' => false],
            ['name' => 'Canh bí đao', 'category' => 'Rau & canh', 'code' => 'CANHBIDAO', 'cost' => 3500, 'hasOptions' => false],

            // Tráng miệng (6 món)
            ['name' => 'Chè ba màu', 'category' => 'Tráng miệng', 'code' => 'CHEBAMAU', 'cost' => 3000, 'hasOptions' => false],
            ['name' => 'Kem vani', 'category' => 'Tráng miệng', 'code' => 'KEMVANI', 'cost' => 4000, 'hasOptions' => false],
            ['name' => 'Bánh flan', 'category' => 'Tráng miệng', 'code' => 'BANHFLAN', 'cost' => 2500, 'hasOptions' => false],
            ['name' => 'Chè khoai', 'category' => 'Tráng miệng', 'code' => 'CHEKHOAI', 'cost' => 2500, 'hasOptions' => false],
            ['name' => 'Trái cây thập cẩm', 'category' => 'Tráng miệng', 'code' => 'TRAICAY', 'cost' => 5000, 'hasOptions' => false],
            ['name' => 'Sữa chua', 'category' => 'Tráng miệng', 'code' => 'SUACHUA', 'cost' => 2000, 'hasOptions' => false],
        ];

        foreach ($products as $productData) {
            $product = Product::factory()->create([
                'category_id' => $categoryMap[$productData['category']]->id,
                'name' => $productData['name'],
                'code' => $productData['code'],
                'sort_order' => fake()->numberBetween(0, 100),
            ]);

            // Tạo biến thể mặc định với giá
            $price = $productData['cost'] + fake()->numberBetween(5000, 15000);
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'name' => 'Mặc định',
                'price' => $price,
                'is_default' => true,
                'is_active' => true,
            ]);

            // Nếu là bia, tạo thêm biến thể lon/chai/thùng
            if (str_contains($product->code, 'TIGER') || str_contains($product->code, 'HEIN') || str_contains($product->code, 'SGN')) {
                ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'name' => 'Lon',
                    'price' => 25000,
                    'is_default' => false,
                ]);
                ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'name' => 'Chai',
                    'price' => 27000,
                    'is_default' => false,
                ]);
                ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'name' => 'Thùng',
                    'price' => 550000,
                    'is_default' => false,
                    'tracks_inventory' => true,
                    'stock_unit' => 'lon',
                    'stock_factor' => 24,
                ]);
            }

            // Tạo tùy chọn cho một số món
            if ($productData['hasOptions']) {
                $this->createOptionsForProduct($product);
            }
        }
    }

    private function createOptionsForProduct(Product $product): void
    {
        // Nhóm tùy chọn "Độ cay"
        if (in_array($product->code, ['GANUOI', 'THBO', 'TOMNUONG', 'CANUONG', 'XIENTC', 'CANHGA', 'MUCNUONG', 'COMCHIEN', 'PHO'])) {
            $spicyGroup = OptionGroup::factory()->forProduct($product)->create([
                'name' => 'Độ cay',
                'is_required' => false,
                'max_select' => 1,
            ]);

            Option::factory()->create([
                'option_group_id' => $spicyGroup->id,
                'name' => 'Không cay',
                'price_delta' => 0,
            ]);
            Option::factory()->create([
                'option_group_id' => $spicyGroup->id,
                'name' => 'Ít cay',
                'price_delta' => 0,
            ]);
            Option::factory()->create([
                'option_group_id' => $spicyGroup->id,
                'name' => 'Cay vừa',
                'price_delta' => 0,
            ]);
            Option::factory()->create([
                'option_group_id' => $spicyGroup->id,
                'name' => 'Cay',
                'price_delta' => 0,
            ]);
        }

        // Nhóm tùy chọn "Đá" cho nước uống
        if (in_array($product->code, ['NCNGOT', 'CADENM'])) {
            $iceGroup = OptionGroup::factory()->forProduct($product)->create([
                'name' => 'Đá',
                'is_required' => false,
                'max_select' => 1,
            ]);

            Option::factory()->create([
                'option_group_id' => $iceGroup->id,
                'name' => 'Ít đá',
                'price_delta' => 0,
            ]);
            Option::factory()->create([
                'option_group_id' => $iceGroup->id,
                'name' => 'Nhiều đá',
                'price_delta' => 0,
            ]);
            Option::factory()->create([
                'option_group_id' => $iceGroup->id,
                'name' => 'Không đá',
                'price_delta' => 0,
            ]);
        }

        // Nhóm tùy chọn "Đủ bộ" cho gà luộc
        if ($product->code === 'GALUOC') {
            $setGroup = OptionGroup::factory()->forProduct($product)->create([
                'name' => 'Đủ bộ',
                'is_required' => false,
                'max_select' => 1,
            ]);

            Option::factory()->create([
                'option_group_id' => $setGroup->id,
                'name' => 'Có nước dùng',
                'price_delta' => 0,
            ]);
            Option::factory()->create([
                'option_group_id' => $setGroup->id,
                'name' => 'Không nước dùng',
                'price_delta' => 0,
            ]);
        }

        // Nhóm tùy chọn "Gia vị" cho bia
        if (in_array($product->code, ['TIGER', 'HEIN', 'SGN'])) {
            $seasoningGroup = OptionGroup::factory()->forProduct($product)->create([
                'name' => 'Gia vị',
                'is_required' => false,
                'max_select' => 1,
            ]);

            Option::factory()->create([
                'option_group_id' => $seasoningGroup->id,
                'name' => 'Muối dưa chuột',
                'price_delta' => 0,
            ]);
            Option::factory()->create([
                'option_group_id' => $seasoningGroup->id,
                'name' => 'Muối xổi cay',
                'price_delta' => 0,
            ]);
        }

        // Nhóm tùy chọn "Thêm thịt" cho cơm
        if (in_array($product->code, ['COMCHIEN', 'PHO', 'BUNBO', 'MIXAO', 'BANHMI'])) {
            $meatGroup = OptionGroup::factory()->forProduct($product)->create([
                'name' => 'Thêm thịt',
                'is_required' => false,
                'max_select' => 1,
            ]);

            Option::factory()->create([
                'option_group_id' => $meatGroup->id,
                'name' => 'Thêm gà',
                'price_delta' => 5000,
            ]);
            Option::factory()->create([
                'option_group_id' => $meatGroup->id,
                'name' => 'Thêm bò',
                'price_delta' => 8000,
            ]);
            Option::factory()->create([
                'option_group_id' => $meatGroup->id,
                'name' => 'Thêm tôm',
                'price_delta' => 10000,
            ]);
        }
    }
}
