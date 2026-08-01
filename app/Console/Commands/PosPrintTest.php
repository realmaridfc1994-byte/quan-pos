<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Printing\DTO\BillData;
use App\Domain\Printing\DTO\KitchenSlipData;
use App\Domain\Printing\Templates\FinalBillTemplate;
use App\Domain\Printing\Templates\KitchenSlipTemplate;
use App\Domain\Printing\Templates\ProvisionalBillTemplate;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Lệnh test in: sinh ba mẫu ESC/POS (tem bếp, tạm tính, bill)
 * và lưu thành file để verify trước khi kết nối máy in thật.
 *
 * Cách dùng:
 *   php artisan pos:print-test               — in ra stdout
 *   php artisan pos:print-test > /tmp/test.bin — lưu binary vào file
 */
final class PosPrintTest extends Command
{
    protected $signature = 'pos:print-test';

    protected $description = 'Test in tem bếp, tạm tính, bill bằng dữ liệu mẫu';

    public function handle(): int
    {
        $this->info('Sinh mẫu ESC/POS...');

        // Dữ liệu mẫu
        $kitchenItems = [
            ['product_name' => 'Gà nướng', 'variant_name' => 'Phần', 'quantity' => 2, 'note' => 'Ít muối'],
            ['product_name' => 'Mực xào', 'variant_name' => 'Phần', 'quantity' => 1, 'note' => null],
        ];

        $billItems = [
            ['product_name' => 'Gà nướng', 'variant_name' => 'Phần', 'quantity' => 2, 'unit_price' => 120_000, 'line_amount' => 240_000],
            ['product_name' => 'Mực xào', 'variant_name' => 'Phần', 'quantity' => 1, 'unit_price' => 150_000, 'line_amount' => 150_000],
            ['product_name' => 'Tiger', 'variant_name' => 'Lon 330ml', 'quantity' => 3, 'unit_price' => 25_000, 'line_amount' => 75_000],
        ];

        // 1. Tem bếp
        $this->line('');
        $this->info('=== TEM BẾP ===');
        $kitchenSlip = new KitchenSlipData(
            tableName: '3',
            orderTime: now()->format('H:i'),
            staffName: 'Minh Anh',
            station: 'Bếp',
            items: $kitchenItems,
        );
        $kitchenOutput = KitchenSlipTemplate::render($kitchenSlip);
        $this->comment('Độ dài: '.strlen($kitchenOutput).' bytes');

        // 2. Tạm tính
        $this->line('');
        $this->info('=== PHIẾU TẠM TÍNH ===');
        $provisionalBill = new BillData(
            tableName: '3',
            sessionCode: 'BAN-003-001',
            items: $billItems,
            subtotal: Money::fromInt(465_000),
            discountAmount: Money::fromInt(0),
            discountReason: null,
            total: Money::fromInt(465_000),
            tenderedAmount: null,
            changeAmount: null,
            paymentMethod: null,
            paymentReference: null,
        );
        $provisionalOutput = ProvisionalBillTemplate::render($provisionalBill);
        $this->comment('Độ dài: '.strlen($provisionalOutput).' bytes');

        // 3. Bill cuối (tiền mặt)
        $this->line('');
        $this->info('=== HÓA ĐƠN CUỐI (TIỀN MẶT) ===');
        $finalBillCash = new BillData(
            tableName: '3',
            sessionCode: 'BAN-003-001',
            items: $billItems,
            subtotal: Money::fromInt(465_000),
            discountAmount: Money::fromInt(15_000),
            discountReason: 'Khách quen',
            total: Money::fromInt(450_000),
            tenderedAmount: Money::fromInt(500_000),
            changeAmount: Money::fromInt(50_000),
            paymentMethod: 'cash',
            paymentReference: null,
        );
        $finalBillCashOutput = FinalBillTemplate::render($finalBillCash);
        $this->comment('Độ dài: '.strlen($finalBillCashOutput).' bytes');

        // 4. Bill cuối (chuyển khoản)
        $this->line('');
        $this->info('=== HÓA ĐƠN CUỐI (CHUYỂN KHOẢN) ===');
        $finalBillTransfer = new BillData(
            tableName: '5',
            sessionCode: 'BAN-005-002',
            items: $billItems,
            subtotal: Money::fromInt(465_000),
            discountAmount: Money::fromInt(0),
            discountReason: null,
            total: Money::fromInt(465_000),
            tenderedAmount: null,
            changeAmount: null,
            paymentMethod: 'transfer',
            paymentReference: 'FT26080100123456',
        );
        $finalBillTransferOutput = FinalBillTemplate::render($finalBillTransfer);
        $this->comment('Độ dài: '.strlen($finalBillTransferOutput).' bytes');

        // Lưu các mẫu vào file để kiểm tra
        $testDir = storage_path('app/print-test');
        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        file_put_contents("{$testDir}/kitchen-slip.bin", $kitchenOutput);
        file_put_contents("{$testDir}/provisional-bill.bin", $provisionalOutput);
        file_put_contents("{$testDir}/final-bill-cash.bin", $finalBillCashOutput);
        file_put_contents("{$testDir}/final-bill-transfer.bin", $finalBillTransferOutput);

        $this->line('');
        $this->info("✅ Các mẫu đã được lưu vào: {$testDir}/");
        $this->info('Để kiểm tra, dùng lệnh:');
        $this->comment("  cat {$testDir}/kitchen-slip.bin | od -An -tx1");
        $this->comment('hoặc đưa file .bin vào máy in USB để test.');

        return self::SUCCESS;
    }
}
