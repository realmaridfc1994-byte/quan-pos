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

/**
 * Chạy lại được nhiều lần, không xoá gì — chỉ updateOrCreate() theo cột định
 * danh ổn định. KHÔNG được đụng 8 bảng giao dịch (orders, order_items,
 * order_item_options, payments, shifts, table_sessions, table_session_tables,
 * cash_movements): seeder này chỉ dựng danh mục món/bàn và 4 tài khoản mẫu,
 * chạy lại giữa giờ quán đang bán không được làm mất một dòng giao dịch nào.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────────────────────────
        // 1. NGƯỜI DÙNG (4 người) — khoá theo phone (UNIQUE thật)
        // ──────────────────────────────────────────────────────────────
        $nhanVien = [
            ['name' => 'Chủ quán', 'username' => 'owner', 'phone' => '0900000001', 'role' => UserRole::Owner],
            ['name' => 'Thu ngân', 'username' => 'cashier', 'phone' => '0900000002', 'role' => UserRole::Cashier],
            ['name' => 'Phục vụ', 'username' => 'staff', 'phone' => '0900000003', 'role' => UserRole::Staff],
            ['name' => 'Bếp', 'username' => 'kitchen', 'phone' => '0900000004', 'role' => UserRole::Kitchen],
        ];

        foreach ($nhanVien as $nv) {
            User::query()->updateOrCreate(
                ['phone' => $nv['phone']],
                [
                    'name' => $nv['name'],
                    'username' => $nv['username'],
                    'password' => Hash::make('password'),
                    'pin_code' => Hash::make('1234'),
                    'role' => $nv['role'],
                    'is_active' => true,
                ]
            );
        }

        // ──────────────────────────────────────────────────────────────
        // 2. BÀN (12 bàn) — khoá theo code (UNIQUE thật)
        // ──────────────────────────────────────────────────────────────
        $ban = [];

        for ($i = 1; $i <= 8; $i++) {
            $ban[] = [
                'code' => sprintf('B%02d', $i),
                'name' => "Bàn {$i}",
                'area' => 'Trong nhà',
                'seats' => 4,
                'sort_order' => $i,
            ];
        }

        for ($i = 1; $i <= 4; $i++) {
            $ban[] = [
                'code' => sprintf('S%02d', $i),
                'name' => "Bàn sân {$i}",
                'area' => 'Sân',
                'seats' => 6,
                'sort_order' => 8 + $i,
            ];
        }

        foreach ($ban as $b) {
            DiningTable::query()->updateOrCreate(
                ['code' => $b['code']],
                [
                    'name' => $b['name'],
                    'area' => $b['area'],
                    'seats' => $b['seats'],
                    'sort_order' => $b['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        // ──────────────────────────────────────────────────────────────
        // 3. NHÓM MÓN (8 nhóm) — khoá theo name (UNIQUE thật)
        // ──────────────────────────────────────────────────────────────
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
            $categoryMap[$cat['name']] = Category::query()->updateOrCreate(
                ['name' => $cat['name']],
                [
                    'station' => $cat['station'],
                    'sort_order' => $cat['sort_order'],
                ]
            );
        }

        // ──────────────────────────────────────────────────────────────
        // 4. MÓN (60 món) — khoá theo code (UNIQUE thật)
        // ──────────────────────────────────────────────────────────────
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
            $product = Product::query()->updateOrCreate(
                ['code' => $productData['code']],
                [
                    'category_id' => $categoryMap[$productData['category']]->id,
                    'name' => $productData['name'],
                    'sort_order' => fake()->numberBetween(0, 100),
                ]
            );

            // Biến thể mặc định — khoá theo (product_id, name), UNIQUE thật.
            $price = $productData['cost'] + fake()->numberBetween(5000, 15000);
            ProductVariant::query()->updateOrCreate(
                ['product_id' => $product->id, 'name' => 'Mặc định'],
                [
                    'price' => $price,
                    'is_default' => true,
                    'is_active' => true,
                ]
            );

            // Nếu là bia, thêm biến thể lon/chai/thùng
            if (str_contains($product->code, 'TIGER') || str_contains($product->code, 'HEIN') || str_contains($product->code, 'SGN')) {
                ProductVariant::query()->updateOrCreate(
                    ['product_id' => $product->id, 'name' => 'Lon'],
                    ['price' => 25000, 'is_default' => false]
                );
                ProductVariant::query()->updateOrCreate(
                    ['product_id' => $product->id, 'name' => 'Chai'],
                    ['price' => 27000, 'is_default' => false]
                );
                ProductVariant::query()->updateOrCreate(
                    ['product_id' => $product->id, 'name' => 'Thùng'],
                    [
                        'price' => 550000,
                        'is_default' => false,
                        'tracks_inventory' => true,
                        'stock_unit' => 'lon',
                        'stock_factor' => 24,
                    ]
                );
            }

            if ($productData['hasOptions']) {
                $this->createOptionsForProduct($product);
            }
        }
    }

    private function createOptionsForProduct(Product $product): void
    {
        // Nhóm tùy chọn "Độ cay"
        if (in_array($product->code, ['GANUOI', 'THBO', 'TOMNUONG', 'CANUONG', 'XIENTC', 'CANHGA', 'MUCNUONG', 'COMCHIEN', 'PHO'])) {
            $spicyGroup = $this->updateOrCreateOptionGroup('Độ cay', $product, [
                'is_required' => false,
                'max_select' => 1,
            ]);

            $this->updateOrCreateOption($spicyGroup, 'Không cay', 0);
            $this->updateOrCreateOption($spicyGroup, 'Ít cay', 0);
            $this->updateOrCreateOption($spicyGroup, 'Cay vừa', 0);
            $this->updateOrCreateOption($spicyGroup, 'Cay', 0);
        }

        // Nhóm tùy chọn "Đá" cho nước uống
        if (in_array($product->code, ['NCNGOT', 'CADENM'])) {
            $iceGroup = $this->updateOrCreateOptionGroup('Đá', $product, [
                'is_required' => false,
                'max_select' => 1,
            ]);

            $this->updateOrCreateOption($iceGroup, 'Ít đá', 0);
            $this->updateOrCreateOption($iceGroup, 'Nhiều đá', 0);
            $this->updateOrCreateOption($iceGroup, 'Không đá', 0);
        }

        // Nhóm tùy chọn "Đủ bộ" cho gà luộc
        if ($product->code === 'GALUOC') {
            $setGroup = $this->updateOrCreateOptionGroup('Đủ bộ', $product, [
                'is_required' => false,
                'max_select' => 1,
            ]);

            $this->updateOrCreateOption($setGroup, 'Có nước dùng', 0);
            $this->updateOrCreateOption($setGroup, 'Không nước dùng', 0);
        }

        // Nhóm tùy chọn "Gia vị" cho bia
        if (in_array($product->code, ['TIGER', 'HEIN', 'SGN'])) {
            $seasoningGroup = $this->updateOrCreateOptionGroup('Gia vị', $product, [
                'is_required' => false,
                'max_select' => 1,
            ]);

            $this->updateOrCreateOption($seasoningGroup, 'Muối dưa chuột', 0);
            $this->updateOrCreateOption($seasoningGroup, 'Muối xổi cay', 0);
        }

        // Nhóm tùy chọn "Thêm thịt" cho cơm
        if (in_array($product->code, ['COMCHIEN', 'PHO', 'BUNBO', 'MIXAO', 'BANHMI'])) {
            $meatGroup = $this->updateOrCreateOptionGroup('Thêm thịt', $product, [
                'is_required' => false,
                'max_select' => 1,
            ]);

            $this->updateOrCreateOption($meatGroup, 'Thêm gà', 5000);
            $this->updateOrCreateOption($meatGroup, 'Thêm bò', 8000);
            $this->updateOrCreateOption($meatGroup, 'Thêm tôm', 10000);
        }
    }

    /**
     * Khoá theo (name, product_id, category_id) — đúng ba cột trong ràng buộc
     * ck_option_groups_scope. Không được khoá theo mỗi "name": nhiều món khác
     * nhau dùng chung tên nhóm (ví dụ "Độ cay" ở 9 món) — khoá theo mỗi name
     * sẽ gộp nhầm các nhóm của các món khác nhau thành một bản ghi.
     *
     * @param  array<string, mixed>  $thuocTinh
     */
    private function updateOrCreateOptionGroup(string $ten, Product $product, array $thuocTinh): OptionGroup
    {
        return OptionGroup::query()->updateOrCreate(
            ['name' => $ten, 'product_id' => $product->id, 'category_id' => null],
            $thuocTinh
        );
    }

    private function updateOrCreateOption(OptionGroup $nhom, string $ten, int $priceDelta): void
    {
        Option::query()->updateOrCreate(
            ['option_group_id' => $nhom->id, 'name' => $ten],
            ['price_delta' => $priceDelta]
        );
    }
}
