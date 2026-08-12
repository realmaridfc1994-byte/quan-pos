# REVIEW PHASE 3 — PHẦN A (mục 1, 2, 3)

> Hồ sơ soát cuối Phase 3 (Kho và lợi nhuận) để Opus review trước khi đóng phase.
> Ngày lập: 10/08/2026. Nhánh `master`, HEAD `bc61354`.
>
> **Phần A chứa:** mục 1 (danh sách file theo bước), mục 2 (nội dung đầy đủ phần TIỀN VÀ SỔ CÁI), mục 3 (báo cáo lãi gộp).
> **Phần B (`docs/review-phase3-b.md`) chứa:** mục 4 (bảng bất biến K1–K15), mục 5 (mọi đường ghi vào kho), mục 6 (chỗ nối kho ↔ bán hàng), mục 7 (thứ tự khoá), mục 8 (chỗ tự quyết / chưa chắc), và nguyên văn output 6 lệnh.

Phase 3 nằm trong đúng hai commit:

- `f7e00cb` — `feat(p3-b1-b2): schema kho + nguyen lieu, nha cung cap, quy doi don vi`
- `bc61354` — `feat(p3-b3-b9): dinh luong mon, nhap hang, tru kho, hao hut, kiem ke, lai gop, doi soat`

Tổng cộng **161 file thay đổi, +10.237 dòng**.

---

# 1. DANH SÁCH FILE ĐÃ TẠO/SỬA Ở PHASE 3, NHÓM THEO BƯỚC

Số test ghi ở cuối mỗi bước là số test **mới thêm ở bước đó** (đếm bằng số lời gọi `it(...)`/`test(...)` trong file test của bước). Toàn bộ bộ test hiện có **587 test / 3.876 khẳng định**, xanh hết (xem output ở Phần B).

---

## Bước 0 — Kiểm toán chuẩn bị kho (CHỈ BÁO CÁO)

| File | Nó làm gì |
|---|---|
| `docs/kiem-toan-kho.md` (mới, 65 dòng) | Báo cáo kiểm toán trước khi động vào kho: 5 mục cần chốt (một cửa ghi kho, phân bổ giảm giá về dòng món, tồn âm, dòng tách khi huỷ một phần, nguồn đọc của màn hình báo cáo) |

**Số test: 0** (bước chỉ báo cáo, đúng khoá phạm vi).

---

## Bước 1 — Schema kho: 9 bảng + bất biến nhóm K

### Migration (mới)

| File | Nó làm gì |
|---|---|
| `database/migrations/2026_08_06_000001_create_suppliers_table.php` | Bảng nhà cung cấp |
| `database/migrations/2026_08_06_000002_create_ingredients_table.php` | Bảng nguyên liệu: mã, tên, đơn vị gốc, tồn tối thiểu, cờ `is_active` |
| `database/migrations/2026_08_06_000003_create_ingredient_units_table.php` | Đơn vị quy đổi nhiều cấp. Chốt `ck_ingredient_units_factor` (K8) và `uq_ingredient_units_default` (K14) |
| `database/migrations/2026_08_06_000004_create_recipes_table.php` | Định lượng món (BOM). Chốt `uq_recipes_variant_ingredient`, `ck_recipes_qty` |
| `database/migrations/2026_08_06_000005_add_deducts_stock_to_product_variants_table.php` | Thêm cột `deducts_stock` + **hai trigger** chặn thêm/sửa dòng `recipes` cho biến thể chưa bật cờ trừ kho |
| `database/migrations/2026_08_06_000006_create_stock_balances_table.php` | Bảng tồn hiện tại. Chốt `ck_stock_balances_zero` và `ck_stock_balances_cost` (K9) |
| `database/migrations/2026_08_07_000001_create_stock_movements_table.php` | **Sổ cái kho.** Chốt `uq_stock_movements_ref` (K5+K7), `ck_stock_movements_ref` (K11), `ck_stock_movements_adjust` (K12), `ck_stock_movements_waste_reason` (K15) và 5 CHECK phụ về dấu của `qty_delta` theo `type` |
| `database/migrations/2026_08_07_000002_add_movement_fk_to_stock_balances_table.php` | Bổ sung khoá ngoại `stock_balances.last_movement_id → stock_movements.id` (không tạo được ở Bước 1 vì bảng sổ cái ra đời sau) |
| `database/migrations/2026_08_07_000003_create_purchases_table.php` | Phiếu nhập hàng |
| `database/migrations/2026_08_07_000004_create_purchase_items_table.php` | Dòng phiếu nhập, có **generated column** `qty_base` và `line_cost` |
| `database/migrations/2026_08_10_000001_create_stock_takes_table.php` | Phiếu kiểm kê. Chốt `uq_stock_takes_only_one_open` (K13), `ck_stock_takes_closed` (K10) |
| `database/migrations/2026_08_10_000002_create_stock_take_items_table.php` | Dòng kiểm kê, có generated column `diff_qty` |
| `database/migrations/2026_08_10_000003_create_product_profit_daily_table.php` | Bảng chốt lãi gộp theo món theo ngày |
| `database/migrations/2026_08_10_000004_create_ingredient_waste_monthly_table.php` | Bảng chốt hao hụt theo nguyên liệu theo tháng |

### Tài liệu

| File | Nó làm gì |
|---|---|
| `docs/schema.md` (sửa, +85 dòng) | Thêm mục K.1–K.11: 9 bảng kho, DDL, 15 bất biến nhóm K, chiến lược khoá |
| `docs/thiet-ke-gia-von.md` (mới, 340 dòng) | Thiết kế thuật toán giá vốn bình quân gia quyền, luật một cửa, 7 trường hợp biên |
| `CLAUDE.md` (sửa, +4 dòng) | Bổ sung 07/08: luật một cửa `RecordStockMovement` và cấm `UPDATE stock_balances` đứng một mình |
| `docs/PHASE.md`, `docs/viec-ton.md` | Cập nhật tiến độ |

**Số test riêng cho Bước 1: 2** — hai test mới trong `tests/Feature/Database/GeneratedColumnsTest.php` (`purchase_items.qty_base` và `purchase_items.line_cost` do database tự tính, cố ghi tay bị chặn). File này có tổng 7 test, 5 test còn lại là của Phase 1/2.

---

## Bước 2 — Nguyên liệu, đơn vị, quy đổi nhiều cấp

| File | Nó làm gì |
|---|---|
| `app/Domain/Inventory/Models/Supplier.php` | Model nhà cung cấp |
| `app/Domain/Inventory/Models/Ingredient.php` | Model nguyên liệu |
| `app/Domain/Inventory/Models/IngredientUnit.php` | Model đơn vị quy đổi |
| `app/Domain/Inventory/Enums/IngredientBaseUnit.php` | Đơn vị gốc: gam, ml, cái/lon/chai |
| `app/Domain/Inventory/Actions/ToggleIngredientActive.php` | Bật/tắt nguyên liệu (không xoá — luật 14) |
| `app/Domain/Inventory/Actions/ToggleSupplierActive.php` | Bật/tắt nhà cung cấp |
| `app/Support/UnitConverter.php` | Quy đổi đơn vị nhập ra đơn vị gốc, **toàn số nguyên**, không có đường bắc cầu ngầm |
| `app/Filament/Resources/IngredientResource.php` + `Pages/ManageIngredients.php` | Màn hình quản lý nguyên liệu, không có nút Xoá |
| `app/Filament/Resources/SupplierResource.php` + `Pages/ManageSuppliers.php` | Màn hình nhà cung cấp, không có nút Xoá |
| `database/factories/SupplierFactory.php`, `IngredientFactory.php`, `IngredientUnitFactory.php` | Factory, dùng bộ đếm tăng dần cho cột UNIQUE (luật 23) |
| `database/seeders/InventorySeeder.php` | 60 nguyên liệu thật của quán nhậu + đơn vị quy đổi |
| `database/seeders/DatabaseSeeder.php` | Gọi thêm hai seeder mới |

**Số test: 16**
- `tests/Feature/Support/UnitConverterTest.php` — 7
- `tests/Feature/Inventory/InventorySeederTest.php` — 3
- `tests/Feature/Inventory/ToggleIngredientActiveTest.php` — 2
- `tests/Feature/Inventory/ToggleSupplierActiveTest.php` — 2
- `tests/Feature/Inventory/FilamentNoDeleteButtonTest.php` — 2

---

## Bước 3 — Định lượng món (BOM)

| File | Nó làm gì |
|---|---|
| `app/Domain/Inventory/Models/Recipe.php` | Model định lượng |
| `app/Domain/Inventory/Models/StockBalance.php` | Model tồn hiện tại + **khoá ghi ở tầng Model** (`choPhepGhi()`) |
| `app/Domain/Inventory/Queries/EstimateVariantCost.php` | Giá vốn ước tính của một biến thể = Σ(định lượng × giá TB hiện tại) |
| `app/Domain/Catalog/Models/ProductVariant.php` (sửa) | Thêm cast `deducts_stock` và quan hệ `recipes()` |
| `app/Filament/Resources/ProductVariantResource.php` | Màn hình sửa định lượng cho biến thể (Repeater nguyên liệu) |
| `database/factories/RecipeFactory.php`, `StockBalanceFactory.php` | Factory |
| `database/seeders/RecipeSeeder.php` | Định lượng mẫu: lẩu gà, bia Tiger lon/chai/thùng |

**Số test: 13**
- `tests/Feature/Inventory/RecipeTest.php` — 1
- `tests/Feature/Inventory/RecipeGuardTest.php` — 2
- `tests/Feature/Inventory/RecipeSeederTest.php` — 4
- `tests/Feature/Inventory/EstimateVariantCostTest.php` — 4
- `tests/Feature/Inventory/ProductVariantRecipeFormTest.php` — 2

---

## Bước 4 — Nhập hàng + giá vốn bình quân gia quyền

| File | Nó làm gì |
|---|---|
| **`app/Support/StockCost.php`** | Thuật toán giá vốn bình quân gia quyền, số nguyên thuần, có chống tràn 64 bit |
| **`app/Domain/Inventory/Actions/RecordStockMovement.php`** | **MỘT CỬA DUY NHẤT** ghi vào `stock_balances` + `stock_movements` |
| `app/Domain/Inventory/DTO/RecordStockMovementData.php` | DTO đầu vào của một cửa |
| `app/Domain/Inventory/Models/StockMovement.php` | Model sổ cái + chặn cứng `delete()`/`forceDelete()`/xoá hàng loạt |
| `app/Domain/Inventory/Models/Purchase.php`, `PurchaseItem.php` | Model phiếu nhập |
| `app/Domain/Inventory/Enums/StockMovementType.php`, `StockMovementRefType.php`, `PurchaseStatus.php` | Enum |
| `app/Domain/Inventory/Actions/CreatePurchase.php` | Tạo phiếu nhập draft (chưa đụng kho) |
| `app/Domain/Inventory/Actions/UpdatePurchase.php` | Sửa phiếu draft |
| `app/Domain/Inventory/Actions/CancelPurchase.php` | Huỷ phiếu draft (bắt buộc có lý do) |
| **`app/Domain/Inventory/Actions/ReceivePurchase.php`** | Nhận hàng vào kho qua một cửa |
| `app/Domain/Inventory/DTO/CreatePurchaseData.php`, `UpdatePurchaseData.php`, `CancelPurchaseData.php`, `ReceivePurchaseData.php`, `PurchaseLineData.php` | DTO |
| `app/Domain/Inventory/Policies/PurchasePolicy.php`, `StockMovementPolicy.php` | Phân quyền: chỉ owner/cashier |
| `app/Exceptions/StockCostOverflowException.php`, `StockBalanceWriteNotAllowedException.php`, `StockLedgerInvariantViolatedException.php`, `StockMovementImmutableException.php` | 4 exception riêng của kho |
| `app/Http/Controllers/Api/PurchaseController.php` | 6 endpoint phiếu nhập |
| `app/Http/Requests/StorePurchaseRequest.php`, `UpdatePurchaseRequest.php`, `ReceivePurchaseRequest.php`, `CancelPurchaseRequest.php` | FormRequest |
| `app/Http/Resources/PurchaseResource.php`, `PurchaseItemResource.php` | Resource |
| `app/Filament/Resources/PurchaseResource.php` + `Pages/ManagePurchases.php` | Màn hình nhập hàng |
| `app/Providers/AppServiceProvider.php` (sửa) | Đăng ký policy |
| `routes/api.php` (sửa) | Nhóm route `purchases` |
| `database/factories/PurchaseFactory.php`, `PurchaseItemFactory.php`, `StockMovementFactory.php` | Factory |

**Số test: 61**
- `tests/Unit/Support/StockCostTest.php` — 13 (7 trường hợp biên của tài liệu + tràn số + chặn tham số sai)
- `tests/Feature/Inventory/RecordStockMovementTest.php` — 11
- `tests/Feature/Inventory/OnlyOneStockWriterTest.php` — 8
- `tests/Feature/Inventory/CreatePurchaseTest.php` — 7
- `tests/Feature/Inventory/PurchaseFilamentTest.php` — 6
- `tests/Feature/Inventory/ReceivePurchaseTest.php` — 5
- `tests/Feature/Inventory/CancelPurchaseTest.php` — 5
- `tests/Feature/Inventory/UpdatePurchaseTest.php` — 3
- `tests/Feature/Inventory/StockCostNoMoneyLostTest.php` — 1 (1.000 thao tác ngẫu nhiên, hạt cố định, không mất một đồng)
- `tests/Feature/Database/GeneratedColumnsTest.php` — 2 (đã tính ở Bước 1)

---

## Bước 5 — Trừ kho tự động khi món được phục vụ

| File | Nó làm gì |
|---|---|
| **`app/Domain/Inventory/Actions/DeductStockForServedItem.php`** | Trừ kho theo định lượng cho một dòng món |
| `app/Domain/Ordering/Actions/UpdateOrderItemStatus.php` (sửa, +15 dòng) | Gọi trừ kho **trong cùng transaction** với việc đặt `served_at` |
| `app/Domain/Ordering/DTO/UpdateOrderItemStatusData.php` (sửa, +2 dòng) | Thêm `updatedByUserId` để ghi vào sổ cái |
| `app/Console/Commands/PosDemo.php` (sửa, +134 dòng) | Thêm mốc `--den=tru-kho` diễn tập trọn vòng trừ kho |

**Số test: 9** — `tests/Feature/Inventory/DeductStockForServedItemTest.php`

---

## Bước 6 — Hao hụt, huỷ hàng, điều chỉnh

| File | Nó làm gì |
|---|---|
| `app/Domain/Inventory/Actions/WriteOffStock.php` | Ghi hao hụt: vỡ/hỏng, hết hạn, hao hụt tự nhiên, dùng nội bộ |
| `app/Domain/Inventory/Actions/AdjustStock.php` | Điều chỉnh tay — chỉ chủ quán, bắt buộc PIN chủ quán, lý do ≥ 10 ký tự |
| `app/Domain/Inventory/DTO/WriteOffStockData.php`, `AdjustStockData.php` | DTO |
| `app/Domain/Inventory/Enums/WasteReasonCategory.php` | 4 loại hao hụt |
| `app/Filament/Resources/WasteRecordResource.php` + `Pages/ManageWasteRecords.php` | Màn hình ghi hao hụt, không có nút Xoá |
| `app/Domain/Inventory/Models/StockMovement.php` (sửa) | Chặn xoá sổ cái ở tầng Model |

**Số test: 19**
- `tests/Feature/Inventory/WriteOffStockTest.php` — 8
- `tests/Feature/Inventory/AdjustStockTest.php` — 7
- `tests/Feature/Inventory/StockMovementImmutableTest.php` — 4

---

## Bước 7 — Kiểm kê và xử lý chênh lệch

| File | Nó làm gì |
|---|---|
| `app/Domain/Inventory/Actions/OpenStockTake.php` | Mở phiếu, chụp tồn hệ thống tại thời điểm mở; chặn khi còn bàn mở |
| `app/Domain/Inventory/Actions/RecordStockTakeCount.php` | Ghi số đếm thực tế, sửa lại được khi phiếu còn mở |
| **`app/Domain/Inventory/Actions/CloseStockTake.php`** | Chốt phiếu, sinh dòng sổ cái điều chỉnh cho từng nguyên liệu lệch |
| `app/Domain/Inventory/DTO/OpenStockTakeData.php`, `RecordStockTakeCountData.php`, `CloseStockTakeData.php` | DTO |
| `app/Domain/Inventory/Models/StockTake.php`, `StockTakeItem.php` | Model |
| `app/Domain/Inventory/Enums/StockTakeStatus.php` | Enum |
| `app/Domain/Inventory/Policies/StockTakePolicy.php` | Phân quyền |
| `app/Filament/Resources/StockTakeResource.php` + `Pages/ManageStockTakes.php`, `ViewStockTake.php`, `RelationManagers/ItemsRelationManager.php` | Màn hình kiểm kê |
| `database/factories/StockTakeFactory.php`, `StockTakeItemFactory.php` | Factory |

**Số test: 14** — `tests/Feature/Inventory/StockTakeTest.php`

---

## Bước 8 — Báo cáo lãi gộp theo món, theo ngày

| File | Nó làm gì |
|---|---|
| **`app/Domain/Reporting/Actions/SummarizeProductProfit.php`** | Tổng hợp một ngày vào `product_profit_daily`, có phân bổ giảm giá về từng dòng món |
| `app/Domain/Reporting/Actions/SummarizeIngredientWasteMonthly.php` | Tổng hợp hao hụt một tháng vào `ingredient_waste_monthly` |
| `app/Domain/Reporting/Jobs/SummarizeProductProfitJob.php`, `SummarizeIngredientWasteMonthlyJob.php` | Job chạy nền lúc đóng ca |
| `app/Domain/Reporting/Models/ProductProfitDaily.php`, `IngredientWasteMonthly.php` | Model |
| `app/Domain/Reporting/Queries/GetOwnerProfitDashboard.php` | Số liệu cho màn hình chủ quán, **chỉ đọc bảng chốt** |
| `app/Domain/Staffing/Actions/CloseShift.php` (sửa, +27 dòng) | Đẩy hai job tổng hợp + gọi đối soát kho khi đóng ca |
| `app/Filament/Pages/BaoCaoChuQuan.php` (sửa) | Gắn thêm 4 widget |
| `app/Filament/Widgets/LaiGopTheoMonWidget.php`, `BanChayLaiThapWidget.php`, `HaoHutThangNayWidget.php`, `TonThapWidget.php` + 4 blade tương ứng | Widget màn hình chủ quán |
| `database/factories/ProductProfitDailyFactory.php`, `IngredientWasteMonthlyFactory.php` | Factory |

**Số test: 8**
- `tests/Feature/Reporting/SummarizeProductProfitTest.php` — 4
- `tests/Feature/Reporting/SummarizeIngredientWasteMonthlyTest.php` — 4

---

## Bước 9 — Job đối soát sổ cái và tồn kho

| File | Nó làm gì |
|---|---|
| **`app/Domain/Inventory/Actions/ReconcileStockLedger.php`** | Đối soát 3 bất biến: K2, K3, và served_at ↔ sổ cái |
| `app/Domain/Inventory/DTO/StockReconciliationResult.php` | Kết quả đối soát |
| `app/Console/Commands/ReconcileStockLedgerCommand.php` | Lệnh chạy tay `stock:doi-soat` |
| `app/Domain/Staffing/Actions/CloseShift.php` (sửa) | Gọi đối soát ngay sau đóng ca, ngoài transaction, lỗi chỉ ghi log |
| `app/Filament/Widgets/CanhBaoDoiSoatKhoWidget.php` + `canh-bao-doi-soat-kho.blade.php` | Cảnh báo lệch trên màn hình chủ quán, đọc từ `activity_log` |

**Số test: 10** — `tests/Feature/Inventory/ReconcileStockLedgerTest.php`

---

## Tổng kết số test Phase 3

| Bước | Số test mới |
|---|---|
| 0 | 0 |
| 1 | 2 |
| 2 | 16 |
| 3 | 13 |
| 4 | 61 |
| 5 | 9 |
| 6 | 19 |
| 7 | 14 |
| 8 | 8 |
| 9 | 10 |
| **Tổng Phase 3** | **152** |
| **Toàn bộ dự án** | **587 test / 3.876 khẳng định — xanh hết** |

---

# 2. NỘI DUNG ĐẦY ĐỦ PHẦN TIỀN VÀ SỔ CÁI

Đây là phần cần soát kỹ nhất. Dán nguyên văn, không lược.

---

## 2.1. `app/Support/StockCost.php` (116 dòng)

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\StockCostOverflowException;
use InvalidArgumentException;

/**
 * Thuật toán giá vốn bình quân gia quyền — xem docs/thiet-ke-gia-von.md.
 *
 * Lớp thuần, không đụng database. Toàn bộ số học là số nguyên — không float,
 * không decimal, không bcmath, cùng lý do với App\Support\Money.
 */
final class StockCost
{
    /**
     * Làm tròn nửa lên bằng số nguyên: intdiv(2a + b, 2b).
     *
     * @param  int  $a  tử số gốc, a >= 0
     * @param  int  $b  mẫu số gốc, b > 0
     */
    public static function lamTron(int $a, int $b): int
    {
        if ($a < 0) {
            throw new InvalidArgumentException("Tử số không được âm: {$a}");
        }

        if ($b <= 0) {
            throw new InvalidArgumentException("Mẫu số phải lớn hơn 0: {$b}");
        }

        $tuSo = self::congAnToan(self::nhanAnToan(2, $a), $b);
        $mauSo = self::nhanAnToan(2, $b);

        return intdiv($tuSo, $mauSo);
    }

    /**
     * Giá vốn khi xuất kho — bảng mục 4 của tài liệu, đủ bốn nhánh.
     *
     * @return array{cost: int, has_cost: bool}
     */
    public static function giaVonXuat(int $qty, int $totalCost, int $n): array
    {
        if ($qty > $n) {
            return [
                'cost' => self::lamTron(self::nhanAnToan($totalCost, $n), $qty),
                'has_cost' => true,
            ];
        }

        if ($qty === $n) {
            // Lấy hết sạch: dùng thẳng total_cost để về đúng 0, không sót đồng lẻ.
            return ['cost' => $totalCost, 'has_cost' => true];
        }

        if ($qty > 0) {
            // 0 < qty < n: lấy quá tồn, hết vốn ở đây.
            return ['cost' => $totalCost, 'has_cost' => false];
        }

        // qty <= 0: tồn đang âm, không còn gì để tính giá vốn.
        return ['cost' => 0, 'has_cost' => false];
    }

    /**
     * Giá vốn khi nhập vào theo giá trung bình hiện tại — mục 5.1
     * (kiểm kê thừa, điều chỉnh tăng không phải nhập hàng thật).
     *
     * @return array{cost: int, has_cost: bool}
     */
    public static function giaVonNhapTheoTrungBinh(int $qty, int $totalCost, int $n): array
    {
        if ($qty > 0) {
            return [
                'cost' => self::lamTron(self::nhanAnToan($totalCost, $n), $qty),
                'has_cost' => true,
            ];
        }

        // qty <= 0: không có giá trung bình để dùng.
        return ['cost' => 0, 'has_cost' => false];
    }

    /**
     * Nhân có kiểm tràn số 64 bit. PHP tự chuyển kết quả tràn thành float
     * thay vì báo lỗi — is_int() sau phép nhân là cách phát hiện chuẩn.
     */
    private static function nhanAnToan(int $x, int $y): int
    {
        $ketQua = $x * $y;

        if (! is_int($ketQua)) {
            throw new StockCostOverflowException(
                "Phép nhân {$x} × {$y} vượt giới hạn số nguyên 64 bit."
            );
        }

        return $ketQua;
    }

    private static function congAnToan(int $x, int $y): int
    {
        $ketQua = $x + $y;

        if (! is_int($ketQua)) {
            throw new StockCostOverflowException(
                "Phép cộng {$x} + {$y} vượt giới hạn số nguyên 64 bit."
            );
        }

        return $ketQua;
    }
}
```

**Điểm cần Opus soi:**

1. **Tên tham số của `giaVonXuat` gây hiểu nhầm.** Chữ ký là `giaVonXuat(int $qty, int $totalCost, int $n)`, trong đó `$qty` thực ra là **tồn hiện có** và `$n` là **số lượng đang xuất ra**. Docblock của lớp không nói rõ điều đó. Chỗ gọi ở `RecordStockMovement.php:121` truyền đúng (`$balance->qty, $balance->total_cost, $n`), nhưng tên `$qty` đứng đầu rất dễ khiến người sau truyền nhầm thành số lượng xuất. Đây là bẫy đặt tên, không phải lỗi chạy.
2. `giaVonNhapTheoTrungBinh($qty, $totalCost, $n)` cùng kiểu đặt tên: `$qty` là tồn hiện có, `$n` là số lượng nhập thêm.
3. `congAnToan` gần như không bao giờ nổ được: nếu `nhanAnToan(2,$a)` đã qua thì phép cộng thêm `$b` chỉ tràn ở sát biên. Không sai, chỉ là nhánh gần như chết.
4. Không có nhánh nào sinh `close_residual` — xem mục 8 Phần B.

---

## 2.2. `app/Domain/Inventory/Actions/RecordStockMovement.php` (125 dòng) — MỘT CỬA DUY NHẤT

```php
<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Exceptions\DomainException;
use App\Exceptions\StockLedgerInvariantViolatedException;
use App\Support\StockCost;
use Illuminate\Support\Facades\DB;

/**
 * MỘT CỬA DUY NHẤT ghi vào stock_balances — xem docs/thiet-ke-gia-von.md mục 1.
 *
 * Nhập hàng, bán món, hỏng vỡ, kiểm kê, điều chỉnh, trả hàng nhà cung cấp —
 * tất cả đi qua Action này. Không Action nào khác được UPDATE/INSERT vào
 * stock_balances hay StockMovement::create() — xem tests/Feature/Inventory/
 * OnlyOneStockWriterTest.php. Luật này được khoá cứng ở tầng Model (xem
 * StockBalance::choPhepGhi()), không chỉ dựa vào quy ước đọc trên giấy.
 *
 * Gọi cho nhiều nguyên liệu trong một giao dịch: người GỌI (Action cha) phải
 * tự khoá/gọi theo ingredient_id TĂNG DẦN, giống quy tắc khoá nhiều bàn ở
 * CLAUDE.md mục 4.18. Action này chỉ khoá đúng một dòng stock_balances mỗi
 * lần handle().
 */
final class RecordStockMovement
{
    public function handle(RecordStockMovementData $data): StockMovement
    {
        return DB::transaction(
            fn (): StockMovement => StockBalance::choPhepGhi(
                fn (): StockMovement => $this->ghiSoCaiVaCapNhatTon($data)
            )
        );
    }

    private function ghiSoCaiVaCapNhatTon(RecordStockMovementData $data): StockMovement
    {
        $balance = StockBalance::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['ingredient_id' => $data->ingredientId],
                ['qty' => 0, 'total_cost' => 0]
            );

        [$costDelta, $hasCost] = $this->tinhGiaVon($data, $balance);

        $qtyAfter = $balance->qty + $data->qtyDelta;
        $costAfter = $balance->total_cost + $costDelta;

        // Van an toàn K9 — nổ ra là lỗi lập trình trong tính giá vốn,
        // không phải lỗi dữ liệu người dùng nhập vào.
        if ($qtyAfter === 0 && $costAfter !== 0) {
            throw new StockLedgerInvariantViolatedException(
                "Lỗi lập trình (không phải lỗi dữ liệu): tồn nguyên liệu #{$data->ingredientId} về 0 mà trị giá còn {$costAfter} đồng."
            );
        }

        if ($qtyAfter > 0 && $costAfter < 0) {
            throw new StockLedgerInvariantViolatedException(
                "Lỗi lập trình (không phải lỗi dữ liệu): tồn nguyên liệu #{$data->ingredientId} dương ({$qtyAfter}) mà trị giá âm ({$costAfter} đồng)."
            );
        }

        // Ghi sổ cái TRƯỚC, cập nhật tồn SAU — sổ cái là sự thật, bảng
        // tồn là bản tóm tắt.
        $movement = StockMovement::query()->create([
            'ingredient_id' => $data->ingredientId,
            'type' => $data->type,
            'qty_delta' => $data->qtyDelta,
            'cost_delta' => $costDelta,
            'qty_after' => $qtyAfter,
            'cost_after' => $costAfter,
            'has_cost' => $hasCost,
            'ref_type' => $data->refType,
            'ref_id' => $data->refId,
            'reason' => $data->reason,
            'approved_by_user_id' => $data->approvedByUserId,
            'created_by_user_id' => $data->createdByUserId,
            'shift_id' => $data->shiftId,
            'occurred_at' => $data->occurredAt ?? now(),
        ]);

        $balance->update([
            'qty' => $qtyAfter,
            'total_cost' => $costAfter,
            'last_movement_id' => $movement->id,
            'updated_at' => now(),
        ]);

        return $movement;
    }

    /**
     * @return array{0: int, 1: bool} [costDelta, hasCost]
     */
    private function tinhGiaVon(RecordStockMovementData $data, StockBalance $balance): array
    {
        if ($data->qtyDelta > 0) {
            if ($data->type === StockMovementType::Purchase) {
                if ($data->knownCost === null) {
                    throw new DomainException('Nhập hàng phải ghi rõ số tiền thật trả nhà cung cấp.');
                }

                return [$data->knownCost, true];
            }

            // Kiểm kê thừa, điều chỉnh tăng: dùng giá trung bình hiện tại
            // (docs/thiet-ke-gia-von.md mục 5.1), không phải tiền thật.
            $ketQua = StockCost::giaVonNhapTheoTrungBinh($balance->qty, $balance->total_cost, $data->qtyDelta);

            return [$ketQua['cost'], $ketQua['has_cost']];
        }

        // Ra kho — bán, hỏng vỡ, kiểm kê thiếu, trả hàng, điều chỉnh giảm.
        $n = abs($data->qtyDelta);
        $ketQua = StockCost::giaVonXuat($balance->qty, $balance->total_cost, $n);

        return [-$ketQua['cost'], $ketQua['has_cost']];
    }
}
```

**Điểm cần Opus soi:**

1. **`qtyDelta === 0` rơi vào nhánh "ra kho".** Nếu ai đó gọi với `qtyDelta = 0` (ví dụ để ghi `close_residual`), `$n = 0`, `giaVonXuat($balance->qty, $balance->total_cost, 0)`. Với tồn dương thì `$qty > $n` đúng → `cost = lamTron(total_cost*0, qty) = 0`. Ra `cost_delta = 0` và `qty_delta = 0`, và `ck_stock_movements_delta` (`qty_delta <> 0 OR cost_delta <> 0`) sẽ chặn ở database. Nghĩa là **không có đường nào ghi được dòng `close_residual`** dù enum và CHECK ở database đã chừa chỗ. Xem mục 8 Phần B.
2. **Không có tham số ép `costDelta` cho `type = return`.** Trả hàng nhà cung cấp hiện đi nhánh "ra kho theo giá trung bình" — đúng như test `RecordStockMovementTest` dòng 146 mô tả, nhưng nghĩa là nếu trả lại đúng lô vừa nhập với giá khác giá trung bình, tiền chênh nằm lại trong tồn. Đây là quyết định đã ghi trong tài liệu, không phải lỗi, nhưng cần Opus xác nhận là ý muốn.
3. **`firstOrCreate` với `lockForUpdate`**: khi dòng chưa tồn tại, `lockForUpdate` không khoá được gì (không có dòng), hai giao dịch cùng lúc có thể cùng chạy `INSERT`. Khoá chính `ingredient_id` sẽ làm một bên nổ lỗi trùng khoá (không phải sai số liệu). Đây là cùng dạng đã được ghi nhận ở `OpenShift` Phase 1. Không có test cho tình huống này.
4. `has_cost = false` được ghi vào sổ cái nhưng **không có chỗ nào đọc lại** để cảnh báo chủ quán rằng có phần xuất kho không xác định được giá vốn. Lệnh đối soát Bước 9 không kiểm cột này.

---

## 2.3. Trừ kho khi món được phục vụ

### 2.3a. `app/Domain/Inventory/Actions/DeductStockForServedItem.php` (80 dòng)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Models\OrderItem;

/**
 * Trừ kho theo định lượng cho MỘT dòng món khi bếp báo đã phục vụ.
 *
 * Gọi từ App\Domain\Ordering\Actions\UpdateOrderItemStatus, TRONG CÙNG
 * transaction đặt served_at (docs/schema.md K.9) — trừ kho hỏng thì cả giao
 * dịch rollback, served_at không được đặt.
 *
 * Chỉ MỘT đường trừ kho: qua bảng recipes (docs/schema.md K.1 — kể cả bia lon
 * bán thẳng cũng là một dòng recipe với qty_base = số đơn vị kho tiêu thụ cho
 * một đơn vị bán ra, không có nhánh "trừ thẳng" riêng).
 *
 * Chống trừ hai lần — hai lớp (K5, K7):
 *   1. Lớp code: kiểm tra đã có dòng sổ cái cho order_item này chưa TRƯỚC khi
 *      chạm sổ cái. Gọi lại lần hai không tạo dòng nào, không ném lỗi.
 *   2. Lớp database: khoá uq_stock_movements_ref (ref_type, ref_id,
 *      ingredient_id) chặn đứng kể cả khi lớp code có lỗi.
 * Dòng tách ra khi huỷ một phần (split_from_item_id khác rỗng) KHÔNG BAO GIỜ
 * trừ kho — nó kế thừa served_at của dòng gốc nhưng nguyên liệu đã bị dòng
 * gốc trừ rồi.
 *
 * Nguyên liệu không đủ tồn vẫn cho trừ, để tồn về âm (quyết định đã chốt ở
 * K.9) — bếp không bao giờ bị chặn báo món xong vì lý do tồn kho.
 */
final class DeductStockForServedItem
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(OrderItem $item, int $performedByUserId, ?int $shiftId): void
    {
        if ($item->split_from_item_id !== null) {
            return;
        }

        $daTruRoi = StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->where('ref_id', $item->id)
            ->exists();

        if ($daTruRoi) {
            return;
        }

        $bienThe = $item->productVariant;

        if (! $bienThe->deducts_stock) {
            return;
        }

        $dinhLuong = $bienThe->recipes()->orderBy('ingredient_id')->get();

        foreach ($dinhLuong as $dong) {
            $this->recordStockMovement->handle(new RecordStockMovementData(
                ingredientId: $dong->ingredient_id,
                type: StockMovementType::Sale,
                qtyDelta: -($dong->qty_base * $item->quantity),
                knownCost: null,
                refType: StockMovementRefType::OrderItem,
                refId: $item->id,
                reason: null,
                approvedByUserId: null,
                createdByUserId: $performedByUserId,
                shiftId: $shiftId,
            ));
        }
    }
}
```

### 2.3b. Chỗ nó được gọi — `app/Domain/Ordering/Actions/UpdateOrderItemStatus.php` (86 dòng)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Inventory\Actions\DeductStockForServedItem;
use App\Domain\Ordering\DTO\UpdateOrderItemStatusData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Support\StatusTransition;
use Illuminate\Support\Facades\DB;

/**
 * Bếp/quầy đánh dấu một dòng món đã làm xong.
 *
 * order_items chỉ có đúng một bước tới hợp lệ: ordered → served (huỷ món đi
 * nhánh riêng ở Bước 6, không đụng ở đây).
 *
 * orders.status (sent → preparing → served) không có endpoint riêng — tự suy
 * ra từ tiến độ các dòng món: dòng đầu tiên xong thì phiếu sang "đang làm",
 * dòng cuối cùng xong thì phiếu sang "đã xong".
 *
 * Phase 3 Bước 5: món xong thì trừ kho theo định lượng, CÙNG transaction với
 * việc đặt served_at (docs/schema.md K.9) — trừ kho hỏng thì served_at không
 * được đặt, không dùng Event/Listener (CLAUDE.md mục 4.5 cấm nghiệp vụ ngầm).
 */
final class UpdateOrderItemStatus
{
    /** @var list<string> */
    private const CHUOI_MON = ['ordered', 'served'];

    /** @var list<string> */
    private const CHUOI_PHIEU = ['sent', 'preparing', 'served'];

    public function __construct(
        private readonly DeductStockForServedItem $deductStockForServedItem,
    ) {}

    public function handle(UpdateOrderItemStatusData $data): OrderItem
    {
        return DB::transaction(function () use ($data): OrderItem {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($data->orderItemId);

            StatusTransition::kiemTra(self::CHUOI_MON, $item->status->value, OrderItemStatus::Served->value);

            $item->update([
                'status' => OrderItemStatus::Served,
                'served_at' => now(),
            ]);

            $caDangMo = Shift::query()->where('status', ShiftStatus::Open)->value('id');

            $this->deductStockForServedItem->handle($item, $data->updatedByUserId, $caDangMo);

            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $this->capNhatTrangThaiPhieu($order);

            return $item->refresh();
        });
    }

    private function capNhatTrangThaiPhieu(Order $order): void
    {
        if ($order->status === OrderStatus::Sent) {
            StatusTransition::kiemTra(self::CHUOI_PHIEU, $order->status->value, OrderStatus::Preparing->value);
            $order->update(['status' => OrderStatus::Preparing]);
        }

        $conMonChuaXong = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', OrderItemStatus::Served)
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->exists();

        if (! $conMonChuaXong) {
            $order->refresh();
            StatusTransition::kiemTra(self::CHUOI_PHIEU, $order->status->value, OrderStatus::Served->value);
            $order->update(['status' => OrderStatus::Served, 'served_at' => now()]);
        }
    }
}
```

**Điểm cần Opus soi (quan trọng):**

1. 🔴 **Thứ tự khoá lệch với `docs/schema.md` K.9.** Tài liệu chốt luồng này là `OrderItem → Order → StockBalance`. Code thật là `OrderItem` (dòng 47) → **`StockBalance`** (dòng 58, bên trong `deductStockForServedItem`) → **`Order`** (dòng 60). Tức là `StockBalance` bị khoá **trước** `Order`, ngược hẳn với câu khẳng định ở K.9: *"Không luồng nào khoá StockBalance trước rồi mới khoá thứ khác. Đó là điều kiện đủ để chứng minh không có vòng kẹt."* Hiện tại chưa có Action nào khoá `Order` rồi mới khoá `StockBalance` nên **chưa có vòng kẹt thật**, nhưng chứng minh "không kẹt" trong tài liệu đã không còn đúng, và bất kỳ Action tương lai nào khoá `Order` trước rồi chạm kho sẽ tạo kẹt chéo. Cách sửa nhỏ: chuyển dòng 60 (`$order = Order::query()->lockForUpdate()...`) lên **trước** dòng 58.
2. `Shift::query()->where('status', Open)->value('id')` ở dòng 56 **không khoá** — cố ý (chỉ lấy id để ghi vào sổ cái), nhưng nghĩa là nếu ca đóng đúng lúc đó thì dòng sổ cái gắn vào ca vừa đóng. Ảnh hưởng nhỏ (chỉ sai quy kết ca), không ảnh hưởng số tồn.
3. Nếu **không có ca nào đang mở**, `$caDangMo` là `null` và sổ cái ghi `shift_id = null`. Bếp vẫn báo món xong được. Không có test cho nhánh này.

---

## 2.4. `app/Domain/Inventory/Actions/ReceivePurchase.php` (68 dòng) — nhận hàng

```php
<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\ReceivePurchaseData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Purchase;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nhận hàng vào kho — chuyển phiếu nhập từ draft sang received và đẩy từng
 * dòng vào sổ cái kho qua RecordStockMovement (một cửa duy nhất, xem
 * docs/thiet-ke-gia-von.md mục 1). KHÔNG BAO GIỜ tự ghi vào stock_balances.
 */
final class ReceivePurchase
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(ReceivePurchaseData $data): Purchase
    {
        return DB::transaction(function () use ($data): Purchase {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($data->purchaseId);

            // Kiểm status TRƯỚC khi chạm sổ cái — nhận hai lần phải bị chặn ở
            // đây với thông báo tiếng Việt, không để lộ lỗi khoá duy nhất
            // uq_stock_movements_ref thô từ database.
            if ($purchase->status !== PurchaseStatus::Draft) {
                throw new DomainException('Phiếu nhập này đã nhận hàng hoặc đã huỷ, không nhận lại được nữa.');
            }

            // Khoá theo ingredient_id TĂNG DẦN — chống kẹt chéo khi nhiều phiếu
            // nhận cùng lúc đụng chung nguyên liệu, giống quy tắc khoá nhiều
            // bàn ở CLAUDE.md mục 4.18.
            $dongPhieu = $purchase->items()->orderBy('ingredient_id')->get();

            foreach ($dongPhieu as $dong) {
                $this->recordStockMovement->handle(new RecordStockMovementData(
                    ingredientId: $dong->ingredient_id,
                    type: StockMovementType::Purchase,
                    qtyDelta: $dong->qty_base,
                    knownCost: $dong->line_cost,
                    refType: StockMovementRefType::PurchaseItem,
                    refId: $dong->id,
                    reason: null,
                    approvedByUserId: null,
                    createdByUserId: $data->receivedByUserId,
                    shiftId: null,
                ));
            }

            $purchase->update([
                'status' => PurchaseStatus::Received,
                'received_at' => now(),
                'received_by_user_id' => $data->receivedByUserId,
            ]);

            return $purchase;
        });
    }
}
```

**Điểm cần Opus soi:**

1. `qty_base` và `line_cost` là **generated column** của database (`purchase_items`), không phải giá trị PHP tính. Đã có test chặn ghi tay (`GeneratedColumnsTest`). Đây là chỗ đúng: tiền thật trả nhà cung cấp đi thẳng từ database vào sổ cái, không qua phép nhân trong PHP.
2. `shiftId: null` — nhập hàng không gắn ca. Hợp lý (nhập hàng buổi sáng, không ai mở ca), nhưng nghĩa là báo cáo theo ca không thấy được tiền nhập hàng.
3. Không kiểm `$dongPhieu` rỗng. Phiếu không dòng nào không tạo được (chặn ở `CreatePurchase`/`UpdatePurchase`), nên trên thực tế không xảy ra — nhưng nhận một phiếu rỗng sẽ lặng lẽ chuyển sang `received` mà không ghi gì.

---

## 2.5. `app/Domain/Inventory/Actions/CloseStockTake.php` (78 dòng) — chốt kiểm kê

```php
<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\CloseStockTakeData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\StockTake;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Chốt phiếu kiểm kê: sinh dòng sổ cái điều chỉnh (type=stocktake) cho từng
 * nguyên liệu LỆCH (diff_qty <> 0), rồi khoá phiếu lại — không sửa được nữa.
 *
 * Dòng chưa đếm (counted_qty NULL) bị bỏ qua, không sinh gì cả. Dòng khớp
 * (diff_qty = 0) cũng không sinh gì cả — sổ cái chỉ ghi khi có thật một
 * chênh lệch cần điều chỉnh.
 *
 * Khoá theo ingredient_id TĂNG DẦN trước khi gọi RecordStockMovement (mỗi
 * lần khoá đúng một dòng stock_balances) — chống kẹt chéo, giống quy tắc
 * khoá nhiều bàn ở CLAUDE.md mục 18 và chuỗi khoá StockTake → StockBalance
 * ở docs/schema.md K.9.
 */
final class CloseStockTake
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(CloseStockTakeData $data): StockTake
    {
        return DB::transaction(function () use ($data): StockTake {
            $phieu = StockTake::query()->lockForUpdate()->findOrFail($data->stockTakeId);

            if ($phieu->status !== StockTakeStatus::Open) {
                throw new DomainException('Phiếu kiểm kê này đã chốt hoặc đã huỷ rồi, không chốt lại được nữa.');
            }

            $dongLech = $phieu->items()
                ->whereNotNull('counted_qty')
                ->where('diff_qty', '<>', 0)
                ->orderBy('ingredient_id')
                ->get();

            $tongChenhLech = 0;
            foreach ($dongLech as $dong) {
                $movement = $this->recordStockMovement->handle(new RecordStockMovementData(
                    ingredientId: $dong->ingredient_id,
                    type: StockMovementType::Stocktake,
                    qtyDelta: $dong->diff_qty,
                    knownCost: null,
                    refType: StockMovementRefType::StockTakeItem,
                    refId: $dong->id,
                    reason: null,
                    approvedByUserId: null,
                    createdByUserId: $data->closedByUserId,
                    shiftId: null,
                ));

                $tongChenhLech += $movement->cost_delta;
            }

            $phieu->update([
                'status' => StockTakeStatus::Closed,
                'closed_at' => now(),
                'closed_by_user_id' => $data->closedByUserId,
                'total_diff_cost' => $tongChenhLech,
            ]);

            return $phieu->refresh();
        });
    }
}
```

**Điểm cần Opus soi:**

1. `$tongChenhLech += $movement->cost_delta` — phép cộng trực tiếp trên biến tiền, **không đi qua `Money`**. Đây là cố ý và **không thể tránh**: `cost_delta` có dấu (âm khi kiểm kê thiếu), mà `Money` chặn số âm. Cần Opus xác nhận đây là ngoại lệ hợp lệ của CLAUDE.md mục 8, và nếu có thì nên ghi ngoại lệ đó vào CLAUDE.md.
2. `diff_qty` là generated column (`counted_qty - system_qty`), nên không có đường nào ghi tay số lệch.
3. `shiftId: null` — kiểm kê không gắn ca. Nhất quán với nhập hàng.
4. Phiếu chốt xong **không sinh dòng sổ cái cho những dòng chưa đếm**. Nghĩa là nguyên liệu bỏ sót khi đếm giữ nguyên tồn hệ thống — đúng chủ ý, nhưng không có chỗ nào báo cho chủ quán "phiếu này bạn bỏ sót N nguyên liệu chưa đếm".

---

## 2.6. `app/Domain/Inventory/Actions/WriteOffStock.php` (69 dòng) — hao hụt

```php
<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\DTO\WriteOffStockData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

/**
 * Ghi hao hụt: vỡ/hỏng, hết hạn, hao hụt tự nhiên, dùng nội bộ.
 *
 * Chỉ chủ quán và thu ngân được ghi (staff/kitchen không quản lý kho, giống
 * quy tắc ở PurchasePolicy). Loại hao hụt (WasteReasonCategory) chỉ tồn tại
 * ở tầng code — schema.md chốt stock_movements.type không có chỗ riêng cho
 * từng loại, nên ghép thành tiền tố của reason (xem ghepLyDo()).
 *
 * Luôn ghi type = 'waste' (K5/K7 không áp dụng ở đây vì không có ref tới
 * order_items — dùng ref_type = manual).
 */
final class WriteOffStock
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(WriteOffStockData $data): StockMovement
    {
        $nguoiGhi = User::query()->findOrFail($data->createdByUserId);

        if (! in_array($nguoiGhi->role, [UserRole::Owner, UserRole::Cashier], true)) {
            throw new DomainException('Chỉ chủ quán hoặc thu ngân được ghi hao hụt.');
        }

        if ($data->qty < 1) {
            throw new DomainException('Số lượng hao hụt phải lớn hơn 0.');
        }

        $chiTiet = trim($data->detail);
        if ($chiTiet === '') {
            throw new DomainException('Phải ghi rõ lý do hao hụt.');
        }

        return $this->recordStockMovement->handle(new RecordStockMovementData(
            ingredientId: $data->ingredientId,
            type: StockMovementType::Waste,
            qtyDelta: -$data->qty,
            knownCost: null,
            refType: StockMovementRefType::Manual,
            refId: null,
            reason: $this->ghepLyDo($data->category, $chiTiet),
            approvedByUserId: null,
            createdByUserId: $data->createdByUserId,
            shiftId: $data->shiftId,
        ));
    }

    private function ghepLyDo(WasteReasonCategory $category, string $chiTiet): string
    {
        return "[{$category->label()}] {$chiTiet}";
    }
}
```

**Điểm cần Opus soi:**

1. Ràng buộc database `ck_stock_movements_waste_reason` đòi `reason` dài **≥ 5 ký tự**, nhưng code chỉ chặn chuỗi rỗng. Chuỗi "x" một ký tự vẫn qua được code — và vẫn qua được database **chỉ nhờ tiền tố `[Vỡ/hỏng] `** làm lý do dài lên. Đây là một sự may mắn về độ dài, không phải một chốt chặn có chủ đích. Nếu sau này bỏ tiền tố, ràng buộc sẽ nổ ra lỗi database thô trước mặt thu ngân.
2. Hao hụt **không cần PIN duyệt** — khác `AdjustStock`. Đây là quyết định đã ghi trong docblock (hao hụt có vật chứng, điều chỉnh tay thì không), cần chủ quán xác nhận là đúng ý: thu ngân tự ghi hao hụt bao nhiêu cũng được, không ai duyệt.
3. Không có ngưỡng cảnh báo: ghi hao hụt 10.000 lon bia trong một lần cũng qua.

---

## 2.7. `app/Domain/Inventory/Actions/AdjustStock.php` (82 dòng) — điều chỉnh tay

```php
<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\AdjustStockData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

/**
 * Điều chỉnh tay tồn kho khi biết chắc số liệu sai (ví dụ đếm lại thấy lệch
 * ngoài luồng kiểm kê chính thức — kiểm kê thật là Bước 7, chưa làm).
 *
 * CHỈ chủ quán được thực hiện VÀ chỉ chủ quán được duyệt bằng PIN — nhạy cảm
 * hơn hao hụt (WriteOffStock) vì đây là ghi đè số liệu bằng tay, không có
 * chứng từ gốc (hoá đơn nhập, dòng món bán) đi kèm.
 *
 * CLAUDE.md mục 12: PIN phải xác thực xong TRƯỚC khi mở DB::transaction —
 * VerifyApproverPin chạy trước, RecordStockMovement (tự mở transaction
 * riêng) chạy sau.
 *
 * Ghi type = 'adjust' — TÁCH RIÊNG khỏi 'waste' để Bước 8 báo cáo lọc được
 * chính xác qua cột type có sẵn (ck_stock_movements_adjust bắt buộc DB có
 * approved_by_user_id và reason ≥ 10 ký tự — K12).
 */
final class AdjustStock
{
    private const DO_DAI_LY_DO_TOI_THIEU = 10;

    public function __construct(
        private readonly VerifyApproverPin $verifyApproverPin,
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(AdjustStockData $data): StockMovement
    {
        $nguoiThucHien = User::query()->findOrFail($data->requestedByUserId);
        if ($nguoiThucHien->role !== UserRole::Owner) {
            throw new DomainException('Chỉ chủ quán được điều chỉnh tồn kho tay.');
        }

        if ($data->qtyDelta === 0) {
            throw new DomainException('Số lượng điều chỉnh không được bằng 0.');
        }

        $lyDo = trim($data->reason);
        if (mb_strlen($lyDo) < self::DO_DAI_LY_DO_TOI_THIEU) {
            throw new DomainException('Lý do điều chỉnh phải ghi rõ ràng, tối thiểu '.self::DO_DAI_LY_DO_TOI_THIEU.' ký tự — không được ghi qua loa.');
        }

        $nguoiDuyet = $this->verifyApproverPin->handle(new PinVerifyData(
            userId: $data->approverUserId,
            pin: $data->approverPin,
            requestedByUserId: $data->requestedByUserId,
        ));

        if ($nguoiDuyet->role !== UserRole::Owner) {
            throw new DomainException('Chỉ chủ quán được duyệt điều chỉnh tồn kho tay.');
        }

        return $this->recordStockMovement->handle(new RecordStockMovementData(
            ingredientId: $data->ingredientId,
            type: StockMovementType::Adjust,
            qtyDelta: $data->qtyDelta,
            knownCost: null,
            refType: StockMovementRefType::Manual,
            refId: null,
            reason: $lyDo,
            approvedByUserId: $nguoiDuyet->id,
            createdByUserId: $data->requestedByUserId,
            shiftId: $data->shiftId,
        ));
    }
}
```

**Điểm cần Opus soi:**

1. **PIN xác thực xong hoàn toàn trước khi mở giao dịch** — đúng CLAUDE.md mục 12. Không có `DB::transaction` nào bọc quanh `VerifyApproverPin`. ✅
2. `mb_strlen` đếm ký tự UTF-8, còn `CHAR_LENGTH` ở database cũng đếm ký tự. Hai bên khớp, không lệch với tiếng Việt có dấu. ✅
3. Chỉ chủ quán được làm **và** chỉ chủ quán được duyệt → chủ quán tự duyệt cho chính mình. `VerifyApproverPin` có nhận `requestedByUserId` nên có thể đang có luật tự duyệt ở đó; cần Opus xác nhận điều chỉnh tay là "một người bấm, chính người đó nhập PIN" có đủ chặt không.

---

## 2.8. Action trả hàng nhà cung cấp — **CHƯA CÓ**

🔴 **`StockMovementType::Return` tồn tại trong enum và trong ràng buộc `enum` của cột database, nhưng KHÔNG CÓ Action nào sinh ra nó.**

Kiểm chứng:

```
$ grep -rn "StockMovementType::Return" app/
(không có kết quả)
```

Đường duy nhất chạm tới loại này là **test gọi thẳng `RecordStockMovement`**:

`tests/Feature/Inventory/RecordStockMovementTest.php:146`
> *"trả hàng nhà cung cấp (return) trừ kho theo giá trung bình, không truy giá lô gốc"*

Nghĩa là: cơ chế tính giá vốn khi trả hàng đã đúng và đã có test, nhưng **không có nút bấm, không có Action, không có endpoint, không có màn hình** để thu ngân/chủ quán thật sự trả hàng cho nhà cung cấp. Trong `CancelPurchase` có comment nói *"muốn trả lại thì dùng nghiệp vụ trả hàng nhà cung cấp (Bước 6)"* — nhưng nghiệp vụ đó chưa được viết ở Bước 6.

**Cần chủ quán quyết:** đây là thiếu sót phải bù trước khi đóng Phase 3, hay là việc đẩy sang Phase 4?

## 2.9. Điều chỉnh — đã có (mục 2.7). Hao hụt — đã có (mục 2.6).

---

## 2.10. Lệnh đối soát sổ cái (Bước 9)

### 2.10a. `app/Domain/Inventory/Actions/ReconcileStockLedger.php` (161 dòng)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\StockReconciliationResult;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Đối soát sổ cái kho — Phase 3 Bước 9. Đúng ba bất biến theo docs/schema.md:
 *
 *  a. K2 — với MỌI nguyên liệu: cộng hết qty_delta trong sổ cái = qty trong
 *     bảng tồn. Luôn kiểm TOÀN BỘ lịch sử, không có khái niệm khoảng ngày.
 *  b. K3 — cộng hết cost_delta trong sổ cái = total_cost trong bảng tồn.
 *     Cũng luôn kiểm toàn bộ lịch sử.
 *  c. Mọi order_items có served_at đều có dòng sổ cái tương ứng, và ngược
 *     lại. Đây là chỗ DUY NHẤT nhận khoảng ngày (lọc theo served_at) — hai
 *     mục trên không nhận vì là số cộng dồn không thể cắt theo ngày.
 *     Lưu ý dòng 1894 docs/schema.md: dòng tách ra khi huỷ một phần
 *     (split_from_item_id khác rỗng) CŨNG có served_at kế thừa nhưng KHÔNG
 *     có dòng sổ cái riêng (K7) — phải loại trừ, không thì báo lệch giả.
 *
 * Luôn tự ghi kết quả vào activity_log (log_name = 'doi-soat-kho') — CHỖ
 * DUY NHẤT màn hình chủ quán đọc để hiện cảnh báo, không tạo bảng DB mới
 * (spatie/laravel-activitylog đã có sẵn, dùng lại đúng cách VerifyApproverPin
 * đã làm).
 */
final class ReconcileStockLedger
{
    public function handle(?Carbon $tuNgay = null, ?Carbon $denNgay = null): StockReconciliationResult
    {
        $lechQty = $this->doiSoatSoLuong();
        $lechCost = $this->doiSoatGiaTri();
        [$thieuSoCai, $soCaiMoCoi] = $this->doiSoatServedAt($tuNgay, $denNgay);

        $ketQua = new StockReconciliationResult($lechQty, $lechCost, $thieuSoCai, $soCaiMoCoi);

        activity('doi-soat-kho')
            ->withProperties([
                ...$ketQua->toArray(),
                'tu_ngay' => $tuNgay?->toDateString(),
                'den_ngay' => $denNgay?->toDateString(),
            ])
            ->log($ketQua->sach() ? 'Đối soát kho sạch — không lệch.' : 'Đối soát kho phát hiện lệch.');

        return $ketQua;
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}> */
    private function doiSoatSoLuong(): array
    {
        return $this->soSanh(
            soCai: StockMovement::query()->selectRaw('ingredient_id, SUM(qty_delta) as tong')->groupBy('ingredient_id')->pluck('tong', 'ingredient_id'),
            tonKho: StockBalance::query()->pluck('qty', 'ingredient_id'),
        );
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}> */
    private function doiSoatGiaTri(): array
    {
        return $this->soSanh(
            soCai: StockMovement::query()->selectRaw('ingredient_id, SUM(cost_delta) as tong')->groupBy('ingredient_id')->pluck('tong', 'ingredient_id'),
            tonKho: StockBalance::query()->pluck('total_cost', 'ingredient_id'),
        );
    }

    /**
     * @param  Collection<int, int>  $soCai
     * @param  Collection<int, int>  $tonKho
     * @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}>
     */
    private function soSanh(Collection $soCai, Collection $tonKho): array
    {
        $idTatCa = $soCai->keys()->merge($tonKho->keys())->unique();
        if ($idTatCa->isEmpty()) {
            return [];
        }

        $ten = Ingredient::query()->whereIn('id', $idTatCa)->pluck('name', 'id');

        $ketQua = [];
        foreach ($idTatCa as $id) {
            $giaTriSoCai = (int) ($soCai[$id] ?? 0);
            $giaTriTonKho = (int) ($tonKho[$id] ?? 0);

            if ($giaTriSoCai !== $giaTriTonKho) {
                $ketQua[] = [
                    'ingredient_id' => (int) $id,
                    'ingredient_name' => $ten[$id] ?? "#{$id}",
                    'so_cai' => $giaTriSoCai,
                    'ton_kho' => $giaTriTonKho,
                    'lech' => $giaTriTonKho - $giaTriSoCai,
                ];
            }
        }

        return $ketQua;
    }

    /**
     * @return array{0: list<array{order_item_id: int, product_name: string, variant_name: string, served_at: string}>, 1: list<array{stock_movement_id: int, ref_id: ?int}>}
     */
    private function doiSoatServedAt(?Carbon $tuNgay, ?Carbon $denNgay): array
    {
        $donMonServed = OrderItem::query()
            ->whereNotNull('served_at')
            ->whereNull('split_from_item_id')
            ->when($tuNgay !== null, fn ($q) => $q->whereDate('served_at', '>=', $tuNgay))
            ->when($denNgay !== null, fn ($q) => $q->whereDate('served_at', '<=', $denNgay))
            ->with('productVariant.recipes')
            ->get();

        $idDaTru = StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->pluck('ref_id')
            ->unique();

        $thieuSoCai = [];
        foreach ($donMonServed as $item) {
            if (! $item->productVariant->deducts_stock || $item->productVariant->recipes->isEmpty()) {
                continue;
            }

            if (! $idDaTru->contains($item->id)) {
                $thieuSoCai[] = [
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'served_at' => $item->served_at->toDateTimeString(),
                ];
            }
        }

        // Ngược lại: mọi dòng sổ cái ref_type=order_item phải trỏ về một
        // order_item CÓ served_at — không lọc theo khoảng ngày (bất biến dữ
        // liệu, không phải số theo kỳ).
        $soCaiOrderItem = StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->get(['id', 'ref_id']);

        $idDaServed = OrderItem::query()
            ->whereIn('id', $soCaiOrderItem->pluck('ref_id')->unique())
            ->whereNotNull('served_at')
            ->pluck('id');

        $soCaiMoCoi = $soCaiOrderItem
            ->reject(fn ($dong) => $idDaServed->contains($dong->ref_id))
            ->map(fn ($dong) => ['stock_movement_id' => $dong->id, 'ref_id' => $dong->ref_id])
            ->values()
            ->all();

        return [$thieuSoCai, $soCaiMoCoi];
    }
}
```

### 2.10b. `app/Console/Commands/ReconcileStockLedgerCommand.php` (114 dòng)

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\Actions\ReconcileStockLedger;
use App\Domain\Inventory\DTO\StockReconciliationResult;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Chạy tay đối soát sổ cái kho cho một khoảng ngày bất kỳ — Phase 3 Bước 9.
 *
 * Khoảng ngày (--tu/--den) CHỈ áp dụng cho mục 3 (đối chiếu served_at với sổ
 * cái) — mục 1 và 2 luôn kiểm TOÀN BỘ lịch sử vì đó là số cộng dồn từ đầu,
 * không cắt theo ngày được.
 */
final class ReconcileStockLedgerCommand extends Command
{
    protected $signature = 'stock:doi-soat {--tu= : Ngày bắt đầu đối chiếu served_at, mặc định hôm nay} {--den= : Ngày kết thúc, mặc định hôm nay}';

    protected $description = 'Đối soát sổ cái kho: số lượng, giá trị tồn, và dòng món đã phục vụ có đủ sổ cái tương ứng';

    public function handle(ReconcileStockLedger $action): int
    {
        $tuNgay = Carbon::parse($this->option('tu') ?? 'today');
        $denNgay = Carbon::parse($this->option('den') ?? 'today');

        $ketQua = $action->handle($tuNgay, $denNgay);

        $this->newLine();
        $this->line('<fg=cyan;options=bold>ĐỐI SOÁT SỔ CÁI KHO</>');
        $this->line("Đối chiếu dòng món phục vụ từ {$tuNgay->toDateString()} đến {$denNgay->toDateString()} (mục 1, 2 luôn kiểm toàn bộ lịch sử).");
        $this->newLine();

        $this->inMucSoLuong($ketQua);
        $this->inMucGiaTri($ketQua);
        $this->inMucServedAt($ketQua);

        $this->newLine();
        if ($ketQua->sach()) {
            $this->line('<fg=green;options=bold>✅ SỔ CÁI KHO SẠCH — KHÔNG LỆCH.</>');

            return self::SUCCESS;
        }

        $this->line('<fg=red;options=bold>❌ PHÁT HIỆN LỆCH — XEM CHI TIẾT Ở TRÊN.</>');

        return self::FAILURE;
    }

    private function inMucSoLuong(StockReconciliationResult $ketQua): void
    {
        $this->line('<options=bold>1. Số lượng: sổ cái so với bảng tồn</>');

        if ($ketQua->lechQty === []) {
            $this->line('   ✅ Khớp tuyệt đối với mọi nguyên liệu.');

            return;
        }

        $this->line('   ❌ Lệch ở '.count($ketQua->lechQty).' nguyên liệu:');
        foreach ($ketQua->lechQty as $dong) {
            $this->line("      - {$dong['ingredient_name']}: sổ cái {$dong['so_cai']}, bảng tồn {$dong['ton_kho']}, lệch {$dong['lech']}");
        }
    }

    private function inMucGiaTri(StockReconciliationResult $ketQua): void
    {
        $this->newLine();
        $this->line('<options=bold>2. Giá trị: sổ cái so với bảng tồn</>');

        if ($ketQua->lechCost === []) {
            $this->line('   ✅ Khớp tuyệt đối với mọi nguyên liệu.');

            return;
        }

        $this->line('   ❌ Lệch ở '.count($ketQua->lechCost).' nguyên liệu:');
        foreach ($ketQua->lechCost as $dong) {
            $lech = Money::fromInt(abs($dong['lech']))->format();
            $huong = $dong['lech'] < 0 ? 'thiếu' : 'thừa';
            $this->line("      - {$dong['ingredient_name']}: sổ cái ".number_format($dong['so_cai']).' đ, bảng tồn '.number_format($dong['ton_kho'])." đ, {$huong} {$lech}");
        }
    }

    private function inMucServedAt(StockReconciliationResult $ketQua): void
    {
        $this->newLine();
        $this->line('<options=bold>3. Dòng món đã phục vụ ↔ dòng sổ cái</>');

        if ($ketQua->thieuSoCai === [] && $ketQua->soCaiMoCoi === []) {
            $this->line('   ✅ Mọi dòng món đã phục vụ đều có sổ cái tương ứng, không có dòng sổ cái mồ côi.');

            return;
        }

        if ($ketQua->thieuSoCai !== []) {
            $this->line('   ❌ '.count($ketQua->thieuSoCai).' dòng món đã phục vụ nhưng THIẾU sổ cái:');
            foreach ($ketQua->thieuSoCai as $dong) {
                $this->line("      - Dòng #{$dong['order_item_id']} ({$dong['product_name']} — {$dong['variant_name']}), phục vụ lúc {$dong['served_at']}");
            }
        }

        if ($ketQua->soCaiMoCoi !== []) {
            $this->line('   ❌ '.count($ketQua->soCaiMoCoi).' dòng sổ cái MỒ CÔI (trỏ về dòng món chưa/không còn served_at):');
            foreach ($ketQua->soCaiMoCoi as $dong) {
                $this->line("      - Sổ cái #{$dong['stock_movement_id']} → order_item #{$dong['ref_id']}");
            }
        }
    }
}
```

### 2.10c. Chỗ gọi tự động — trích `app/Domain/Staffing/Actions/CloseShift.php`

```php
        // Phase 3 Bước 8: cùng khuôn, cùng lý do KHÔNG nằm trong transaction ở
        // trên — lỗi tổng hợp lãi gộp/hao hụt không được chặn việc đóng ca.
        SummarizeProductProfitJob::dispatch($shift->opened_at->toDateString());
        SummarizeIngredientWasteMonthlyJob::dispatch($shift->opened_at->toDateString());

        // Phase 3 Bước 9: đối soát sổ cái kho — GỌI NGAY (không đẩy hàng đợi,
        // không lịch chạy nền), NGOÀI transaction đóng ca ở trên. Lỗi đối soát
        // không bao giờ được chặn việc đóng ca — chỉ ghi log. ReconcileStockLedger
        // tự ghi kết quả vào activity_log để màn hình chủ quán đọc cảnh báo.
        //
        // Khoảng ngày đối chiếu chạy từ lúc MỞ ca tới lúc ĐÓNG ca, không phải
        // gói gọn trong ngày mở ca: quán mở 20 giờ và đóng ca 2 giờ sáng hôm
        // sau là chuyện thường, mọi món bưng ra sau nửa đêm vẫn phải được kiểm.
        try {
            app(ReconcileStockLedger::class)->handle($shift->opened_at, $shift->closed_at ?? now());
        } catch (Throwable $e) {
            Log::error('Đối soát sổ cái kho thất bại sau khi đóng ca: '.$e->getMessage(), [
                'shift_id' => $shift->id,
                'exception' => $e,
            ]);
        }
```

**Điểm cần Opus soi:**

1. ✅ **Loại trừ `split_from_item_id`** ở dòng 114 — đúng yêu cầu dòng 1894 của `docs/schema.md`. Có test riêng (`ReconcileStockLedgerTest` dòng 120).
2. `whereDate('served_at', ...)` so sánh theo **ngày**, không theo giờ, dù `CloseShift` truyền vào hai thời điểm có giờ (`opened_at`, `closed_at`). Nghĩa là ca mở 20 giờ ngày 10 và đóng 2 giờ ngày 11 sẽ đối soát **trọn hai ngày 10 và 11**, gồm cả món của ca trước ngày 10 lúc 11 giờ trưa. Chỉ làm phạm vi kiểm **rộng hơn**, không bỏ sót — nhưng có thể báo lệch về những món của ca khác. Có test cho ca vắt qua nửa đêm (`ReconcileStockLedgerTest` dòng 190).
3. `$idDaTru = ...->pluck('ref_id')->unique()` **nạp toàn bộ** `ref_id` của sổ cái vào bộ nhớ, không lọc theo khoảng ngày. Với 5–15 bàn thì không sao trong nhiều năm; đáng ghi chú chứ không đáng sửa.
4. Mục "sổ cái mồ côi" **cố ý không lọc theo ngày** — chạy sau mỗi ca sẽ quét lại toàn bộ lịch sử. Đúng chủ ý, nhưng nghĩa là một dòng mồ côi cũ sẽ báo đỏ mãi cho tới khi có người xử lý, và **không có công cụ nào để xử lý nó** (sổ cái không xoá được). Cần Opus xác nhận cách thoát khỏi trạng thái đỏ vĩnh viễn này.
5. `Money::fromInt(abs($dong['lech']))` ở lệnh in — dùng `abs()` để tránh `Money` nổ vì số âm. Đúng cách.

---

# 3. BÁO CÁO LÃI GỘP (BƯỚC 8) — NỘI DUNG ĐẦY ĐỦ

## 3.1. `app/Domain/Reporting/Actions/SummarizeProductProfit.php` (148 dòng)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Reporting\Models\ProductProfitDaily;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tổng hợp MỘT NGÀY vào `product_profit_daily` — Phase 3 Bước 8.
 *
 * Nguồn chân lý cho Action này (KHÔNG áp dụng cho màn hình đọc) là
 * `order_items`/`orders`/`table_sessions`/`stock_movements` — bảng
 * `product_profit_daily` chỉ là bản chốt lại để màn hình chủ quán đọc,
 * KHÔNG BAO GIỜ đọc ngược từ bảng đó (giống SummarizeDailyReport ở Phase 2).
 *
 * Luôn TÍNH LẠI TỪ ĐẦU rồi ghi đè (xoá-rồi-chèn lại) cho đúng ngày — gọi lại
 * nhiều lần cho CÙNG một ngày luôn ra đúng một kết quả.
 *
 * ── Doanh thu đã phân bổ giảm giá (docs/kiem-toan-kho.md mục 5) ──────────
 * table_sessions.discount_amount chỉ tồn tại ở CẤP TỔNG BILL, không có cột
 * nào lưu phần giảm giá riêng cho từng order_items. Action này tự chia lại
 * theo tỉ lệ line_amount/subtotal_amount của TOÀN BỘ dòng món (mọi ngày,
 * không chỉ ngày đang tổng hợp) thuộc lượt khách đó, để một dòng món không
 * đổi doanh thu khi báo cáo chạy lại cho ngày khác. Chia bằng intdiv (làm
 * tròn xuống) cho mọi dòng TRỪ dòng cuối cùng — dòng cuối nhận đúng phần dư
 * còn lại, đảm bảo tổng các dòng LUÔN khớp tuyệt đối với discount_amount,
 * không lệch một đồng nào vì làm tròn.
 *
 * ── Giá vốn tại thời điểm bán ─────────────────────────────────────────────
 * cost_amount cộng dồn |cost_delta| của các dòng stock_movements
 * (ref_type=order_item, ref_id=order_items.id) do DeductStockForServedItem
 * ghi lúc phục vụ — đây là giá vốn bình quân gia quyền TẠI THỜI ĐIỂM BÁN,
 * không tính lại theo giá vốn hiện tại (cùng tinh thần CLAUDE.md mục 10: số
 * trên hoá đơn được chốt, không tính lại về sau). Món không trừ kho
 * (deducts_stock=false) không có dòng sổ cái nào → cost_amount = 0.
 */
final class SummarizeProductProfit
{
    public function handle(string $date): Collection
    {
        $ngay = Carbon::parse($date)->startOfDay();

        return DB::transaction(function () use ($ngay): Collection {
            $dongMonHomNay = OrderItem::query()
                ->whereHas('order', fn ($q) => $q
                    ->whereDate('sent_at', $ngay)
                    ->where('status', '!=', OrderStatus::Cancelled))
                ->where('status', '!=', OrderItemStatus::Cancelled)
                ->with('order:id,table_session_id')
                ->get();

            ProductProfitDaily::query()->where('date', $ngay->toDateString())->delete();

            if ($dongMonHomNay->isEmpty()) {
                return collect();
            }

            $idPhienLienQuan = $dongMonHomNay->pluck('order.table_session_id')->unique()->values();
            $doanhThuTheoDongMon = $this->tinhDoanhThuDaPhanBoGiamGia($idPhienLienQuan);
            $giaVonTheoDongMon = $this->tinhGiaVonTheoDongMon($dongMonHomNay->pluck('id'));

            return $dongMonHomNay
                ->groupBy(fn (OrderItem $item): string => "{$item->product_id}:{$item->product_variant_id}")
                ->map(function (Collection $nhom) use ($ngay, $doanhThuTheoDongMon, $giaVonTheoDongMon): ProductProfitDaily {
                    $mauDau = $nhom->first();

                    return ProductProfitDaily::query()->create([
                        'date' => $ngay->toDateString(),
                        'product_id' => $mauDau->product_id,
                        'product_variant_id' => $mauDau->product_variant_id,
                        'quantity_sold' => $nhom->sum('quantity'),
                        'revenue_amount' => $nhom->sum(fn (OrderItem $i) => $doanhThuTheoDongMon[$i->id] ?? $i->line_amount),
                        'cost_amount' => $nhom->sum(fn (OrderItem $i) => $giaVonTheoDongMon[$i->id] ?? 0),
                    ]);
                })
                ->values();
        });
    }

    /**
     * @param  Collection<int, int>  $idPhien
     * @return array<int, int> [order_item_id => doanh_thu_da_phan_bo]
     */
    private function tinhDoanhThuDaPhanBoGiamGia(Collection $idPhien): array
    {
        $ketQua = [];

        $phiens = TableSession::query()->whereIn('id', $idPhien)->get(['id', 'subtotal_amount', 'discount_amount']);

        foreach ($phiens as $phien) {
            $dongCuaPhien = OrderItem::query()
                ->whereHas('order', fn ($q) => $q->where('table_session_id', $phien->id)->where('status', '!=', OrderStatus::Cancelled))
                ->where('status', '!=', OrderItemStatus::Cancelled)
                ->orderBy('id')
                ->get(['id', 'line_amount']);

            if ($phien->discount_amount === 0 || $phien->subtotal_amount === 0 || $dongCuaPhien->isEmpty()) {
                foreach ($dongCuaPhien as $dong) {
                    $ketQua[$dong->id] = $dong->line_amount;
                }

                continue;
            }

            $daPhanBo = 0;
            $soDong = $dongCuaPhien->count();

            foreach ($dongCuaPhien as $idx => $dong) {
                if ($idx === $soDong - 1) {
                    $phanBo = $phien->discount_amount - $daPhanBo;
                } else {
                    $phanBo = intdiv($dong->line_amount * $phien->discount_amount, $phien->subtotal_amount);
                    $daPhanBo += $phanBo;
                }

                $ketQua[$dong->id] = $dong->line_amount - $phanBo;
            }
        }

        return $ketQua;
    }

    /**
     * @param  Collection<int, int>  $idDongMon
     * @return array<int, int> [order_item_id => gia_von]
     */
    private function tinhGiaVonTheoDongMon(Collection $idDongMon): array
    {
        return StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->whereIn('ref_id', $idDongMon)
            ->selectRaw('ref_id, SUM(-cost_delta) as tong_gia_von')
            ->groupBy('ref_id')
            ->pluck('tong_gia_von', 'ref_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
```

## 3.2. Cách phân bổ giảm giá về từng dòng món — giải thích bằng ngôn ngữ quán

Bài toán: bill của một bàn ghi **giảm giá ở cấp cả bill** (ví dụ giảm 50.000 đ cho cả bàn), không ghi giảm cho từng món. Nhưng báo cáo lãi gộp cần biết **món nào lãi bao nhiêu**, nên phải chia 50.000 đó về từng dòng món.

Cách chia:

1. **Lấy TẤT CẢ dòng món của lượt khách đó**, không chỉ dòng của ngày đang tổng hợp (`tinhDoanhThuDaPhanBoGiamGia`, dòng 101–105). Lý do: một bàn ngồi từ 23 giờ đến 1 giờ sáng có món ở hai ngày; nếu chỉ lấy dòng của một ngày thì cùng một dòng món sẽ được chia phần giảm giá khác nhau tuỳ ngày báo cáo chạy — số sẽ nhảy.
2. **Sắp xếp theo `id` tăng dần** (dòng 104) — cùng một thứ tự mỗi lần chạy, nên kết quả lặp lại được.
3. **Tỉ lệ chia:** `phần giảm của dòng = intdiv(line_amount × discount_amount, subtotal_amount)` (dòng 122). Toàn bộ là **số nguyên**, dùng `intdiv` (chia lấy phần nguyên, làm tròn xuống), không có float ở bất kỳ chỗ nào.
4. **Dòng cuối cùng nhận phần dư:** `phần giảm = discount_amount − tổng đã chia cho các dòng trước` (dòng 120). Nhờ vậy tổng phần giảm của mọi dòng **luôn bằng đúng `discount_amount`**, không lệch một đồng vì làm tròn.
5. **Doanh thu của dòng** = `line_amount − phần giảm` (dòng 126).
6. Bill không giảm giá, hoặc `subtotal_amount = 0`: doanh thu dòng = `line_amount` nguyên vẹn (dòng 107–113).

**Giá vốn** thì không chia gì cả — đọc thẳng từ sổ cái: cộng `-cost_delta` của mọi dòng `stock_movements` có `ref_type=order_item, ref_id = id dòng món`. Đó là giá vốn bình quân gia quyền **tại đúng lúc bưng món ra**, không phải giá vốn hôm nay.

**Món huỷ** bị loại ra khỏi cả hai vế (dòng 58 và 103), phiếu huỷ cũng vậy (dòng 57 và 102).

**Test bảo vệ:** `tests/Feature/Reporting/SummarizeProductProfitTest.php` dòng 46 —
> *"một lượt khách có giảm giá: lãi gộp từng dòng cộng lại bằng đúng doanh thu thật trừ tổng giá vốn"*

## 3.3. Hao hụt theo tháng — `SummarizeIngredientWasteMonthly.php` (47 dòng)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Reporting\Models\IngredientWasteMonthly;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tổng hợp MỘT THÁNG vào `ingredient_waste_monthly` — Phase 3 Bước 8.
 *
 * Nguồn chân lý là `stock_movements` (type=waste) — bảng tổng hợp chỉ là
 * bản chốt lại để màn hình chủ quán đọc, không được đọc thẳng sổ cái (mục 4
 * đề bài). Luôn TÍNH LẠI TỪ ĐẦU rồi ghi đè đúng tháng đang chạy, giống
 * SummarizeDailyReport/SummarizeProductProfit.
 */
final class SummarizeIngredientWasteMonthly
{
    public function handle(string $anyDateInMonth): Collection
    {
        $dauThang = Carbon::parse($anyDateInMonth)->startOfMonth();
        $cuoiThang = $dauThang->clone()->endOfMonth();

        return DB::transaction(function () use ($dauThang, $cuoiThang): Collection {
            IngredientWasteMonthly::query()->where('month', $dauThang->toDateString())->delete();

            $tongTheoNguyenLieu = StockMovement::query()
                ->where('type', StockMovementType::Waste)
                ->whereBetween('occurred_at', [$dauThang, $cuoiThang])
                ->selectRaw('ingredient_id, SUM(-qty_delta) as tong_qty, SUM(-cost_delta) as tong_cost')
                ->groupBy('ingredient_id')
                ->get();

            return $tongTheoNguyenLieu->map(fn ($dong) => IngredientWasteMonthly::query()->create([
                'month' => $dauThang->toDateString(),
                'ingredient_id' => $dong->ingredient_id,
                'waste_qty' => (int) $dong->tong_qty,
                'waste_cost' => (int) $dong->tong_cost,
            ]));
        });
    }
}
```

## 3.4. Màn hình chủ quán — `GetOwnerProfitDashboard.php`

Chỉ đọc từ ba nguồn: `product_profit_daily`, `ingredient_waste_monthly`, `stock_balances`. **Không đọc** `order_items`/`orders`/`stock_movements` (đúng luật "màn hình không đọc thẳng sổ cái"). Trả về 6 khối: lãi gộp theo tổng, lãi gộp theo tỉ lệ, bán chạy nhưng lãi thấp (ngưỡng 15%), hao hụt tháng này, tồn thấp, và tháng đang xem. Bốn widget Filament hiển thị: `LaiGopTheoMonWidget`, `BanChayLaiThapWidget`, `HaoHutThangNayWidget`, `TonThapWidget`.

## 3.5. Điểm cần Opus soi ở Bước 8

1. 🟡 **`tinhDoanhThuDaPhanBoGiamGia` chạy N+1 truy vấn** — một truy vấn `order_items` cho mỗi lượt khách trong ngày. Với 15 bàn × 3 lượt = 45 truy vấn mỗi đêm, chấp nhận được ở quy mô này, nhưng đáng ghi nhận.
2. 🟡 **`whereDate('sent_at', $ngay)` cắt theo ngày dương lịch, không theo ca.** `CloseShift` truyền `$shift->opened_at->toDateString()`, nên ca mở 20 giờ ngày 10 và đóng 2 giờ ngày 11 sẽ tổng hợp cho ngày 10 — nhưng những món gọi **sau nửa đêm** có `sent_at` là ngày 11 và **không** được tính vào ngày 10. Chúng sẽ được tính khi ca hôm sau đóng và tổng hợp lại ngày 11. Không mất số, nhưng "doanh thu ngày 10" trong báo cáo lãi gộp **không khớp** với "doanh thu ngày 10" mà `SummarizeDailyReport` của Phase 2 báo (nếu Phase 2 cắt theo ca). Cần Opus kiểm tra hai bảng báo cáo có nhất quán không.
3. 🟡 **Giá vốn của món phục vụ sau nửa đêm.** Món có `sent_at` ngày 10 nhưng `served_at` ngày 11 vẫn được tính vào ngày 10 (nhóm theo `sent_at`), và giá vốn của nó cũng vào ngày 10 vì tra theo `ref_id`. Nhất quán về mặt logic; ghi để Opus xác nhận đó là ý muốn.
4. 🟡 **Món chưa phục vụ có doanh thu nhưng giá vốn 0.** Dòng món đã gọi, đã tính tiền, nhưng chưa `served_at` (chưa trừ kho) sẽ vào báo cáo với `cost_amount = 0` → lãi gộp trông cao giả. Với quán nhậu thì món luôn được phục vụ trước khi thu tiền nên hiếm, nhưng khi báo cáo chạy giữa chừng (không phải lúc đóng ca) thì có thể gặp.
5. ✅ Phần dư làm tròn được dồn hết vào dòng cuối — tổng luôn khớp tuyệt đối, đã có test.
