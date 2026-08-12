# REVIEW PHASE 3 — PHẦN B (mục 4, 5, 6, 7, 8 + output 6 lệnh)

> **Phần A (`docs/review-phase3-a.md`) chứa:** mục 1 (danh sách file theo bước), mục 2 (nội dung đầy đủ phần TIỀN VÀ SỔ CÁI), mục 3 (báo cáo lãi gộp).
> **Phần B (file này) chứa:** mục 4 (bảng đối chiếu bất biến K1–K15), mục 5 (mọi đường ghi vào kho), mục 6 (chỗ nối kho ↔ bán hàng), mục 7 (thứ tự khoá), mục 8 (chỗ tự quyết / chưa chắc), và nguyên văn output 6 lệnh.

---

# 4. BẢNG ĐỐI CHIẾU BẤT BIẾN NHÓM K (K1 → K15)

Nguồn: `docs/schema.md` mục K.7, dòng 1874–1888.

**Danh sách 6 đường ghi vào kho** dùng để trả lời cột cuối (chi tiết ở mục 5):

- **(A)** `ReceivePurchase` — nhận hàng
- **(B)** `DeductStockForServedItem` — trừ kho khi bếp báo món xong
- **(C)** `WriteOffStock` — hao hụt
- **(D)** `AdjustStock` — điều chỉnh tay
- **(E)** `CloseStockTake` — chốt kiểm kê
- **(F)** `PosDemo` (lệnh diễn tập, gọi thẳng `RecordStockMovement`, luôn rollback)

Không có đường (G) nào cho trả hàng nhà cung cấp — xem mục 2.8 Phần A.

---

| Mã | Nội dung ngắn | Ai giữ | File + dòng | Tên test | ĐƯỜNG NÀO CÓ THỂ PHÁ BẤT BIẾN NÀY? |
|---|---|---|---|---|---|
| **K1** | Sổ cái không bao giờ sửa hay xoá dòng cũ | **CẢ HAI** | Model: `StockMovement.php:96` (`delete()`), `:101` (`forceDelete()`), `:111-125` (Builder chặn xoá hàng loạt). Bảng không có `updated_at`: `2026_08_07_000001_create_stock_movements_table.php:33` (`const UPDATED_AT = null` ở Model), migration không tạo cột `updated_at` | `StockMovementImmutableTest` — "không ai xoá được dòng sổ cái nào" ×3 (instance, forceDelete, hàng loạt) | **A–F: không đường nào phá được.** Cả 6 đường chỉ INSERT qua `RecordStockMovement`, không đường nào UPDATE/DELETE sổ cái. **Lỗ còn lại:** (i) `StockMovement::query()->update([...])` **KHÔNG bị chặn** — chỉ `delete`/`forceDelete` bị chặn ở Builder tuỳ biến, `update` thì không. Không có Action nào làm, nhưng lớp bảo vệ không kín. (ii) SQL tay qua tinker/MySQL client bỏ qua toàn bộ tầng Model. (iii) Không có TRIGGER `BEFORE UPDATE`/`BEFORE DELETE` ở database — nếu Opus muốn kín tuyệt đối thì đây là chỗ thiếu |
| **K2** | Σ `qty_delta` sổ cái = `qty` bảng tồn | **CHỈ CODE + kiểm tra Bước 9** | Ghi cặp trong cùng lệnh: `RecordStockMovement.php:71-93` (`$qtyAfter` tính từ `$balance->qty + $data->qtyDelta`, ghi sổ cái rồi ghi tồn). Đối soát: `ReconcileStockLedger.php:57-63` | `ReconcileStockLedgerTest` — "cố tình sửa tay bảng tồn cho lệch số lượng → phát hiện đúng nguyên liệu và đúng số lệch"; "không lệch gì thì báo sạch" | **A–F: không đường nào phá được** — cả 6 đều đi qua `RecordStockMovement`, nơi hai lệnh ghi nằm trong cùng một `DB::transaction` (dòng 34). **Đường phá thật sự:** ghi thẳng vào `stock_balances` từ ngoài — bị chặn cứng bởi `StockBalance::choPhepGhi()` (`StockBalance.php:73, 92-164`), có 8 test trong `OnlyOneStockWriterTest` chứng minh mọi ngả (save, update instance, firstOrCreate, update qua quan hệ, increment/decrement, delete) đều bị chặn. **Còn lại đúng một lỗ: SQL tay** — và đó chính là lý do Bước 9 tồn tại |
| **K3** | Σ `cost_delta` sổ cái = `total_cost` bảng tồn | **CHỈ CODE + kiểm tra Bước 9** | `RecordStockMovement.php:53` (`$costAfter = $balance->total_cost + $costDelta`), `:71-93`. Đối soát: `ReconcileStockLedger.php:66-72` | `ReconcileStockLedgerTest` — "cố tình sửa tay giá trị tồn cho lệch → phát hiện đúng nguyên liệu và đúng số tiền lệch"; `StockCostNoMoneyLostTest` — "1000 thao tác ngẫu nhiên không mất một đồng nào, hạt ngẫu nhiên cố định" | Giống K2. **Thêm một rủi ro riêng:** `cost_delta` do `StockCost` tính, không do người nhập. Nếu `StockCost` sai thì K3 vẫn "khớp" (sổ cái và bảng tồn cùng sai một hướng) — Bước 9 **không bắt được** loại sai này. Chốt chặn duy nhất là `StockCostNoMoneyLostTest` (1.000 thao tác ngẫu nhiên) và 13 test biên trong `StockCostTest` |
| **K4** | Ghi sổ cái và cập nhật tồn luôn trong cùng một giao dịch | **CHỈ CODE** | `RecordStockMovement.php:34-39` (`DB::transaction` bọc cả cụm), sổ cái ghi ở `:71`, bảng tồn ghi ở `:88` | `OnlyOneStockWriterTest` — "gọi RecordStockMovement thì thành công, tồn kho đổi đúng"; gián tiếp mọi test kho | **A–F: không phá được** — không đường nào ghi hai vế riêng lẻ. ⚠️ **Điểm yếu:** không có test nào ép `StockMovement::create()` thành công rồi `$balance->update()` thất bại để chứng minh cả hai cùng rollback. Bất biến đúng theo cấu trúc code, chưa được chứng minh bằng test |
| **K5** | Mỗi dòng món trừ mỗi nguyên liệu đúng một lần, mãi mãi | **CẢ HAI** | **DB:** `2026_08_07_000001_create_stock_movements_table.php:46` — `unique(['ref_type','ref_id','ingredient_id'], 'uq_stock_movements_ref')`. **Code:** `DeductStockForServedItem.php:48-55` (kiểm đã có sổ cái chưa) | `DeductStockForServedItemTest` — "gọi trừ kho hai lần cho cùng dòng món chỉ trừ đúng một lần, không ném lỗi"; "món đến muộn qua đồng bộ rồi được phục vụ thì trừ kho đúng một lần" | **B:** hai lớp chặn (code + khoá duy nhất database), không phá được. **A, E:** cũng dùng `ref_id` thật (`purchase_item_id`, `stock_take_item_id`) nên cùng khoá duy nhất bảo vệ — nhận phiếu hai lần / chốt kiểm kê hai lần đều bị chặn (có test). **C, D, F:** dùng `ref_type = manual`, `ref_id = NULL`. **Trong MySQL/MariaDB, NULL trong khoá UNIQUE không trùng nhau**, nên khoá `uq_stock_movements_ref` **KHÔNG chặn gì** với ba đường này. Đúng chủ ý (ghi hao hụt hai lần cho hai lần vỡ thật là chuyện bình thường), nhưng cũng nghĩa là **bấm hai lần vì mạng lag khi ghi hao hụt sẽ trừ kho hai lần** — không có cơ chế chống bấm trùng kiểu `uuid` như `payments`. ⚠️ Đây là lỗ thật, nên báo cho chủ quán |
| **K6** | Món huỷ sau khi đã phục vụ không bao giờ hoàn kho | **CHỈ CODE** | Không có code nào hoàn kho — bất biến giữ bằng cách **không viết đường hoàn**. Xác minh: `grep -rn "StockMovementType::" app/` chỉ ra 5 loại được sinh (`Purchase`, `Sale`, `Waste`, `Adjust`, `Stocktake`), không có nhánh nào cộng lại kho khi huỷ món | `DeductStockForServedItemTest` — "huỷ món ĐÃ served thì không hoàn kho"; "huỷ MỘT PHẦN món đã served (tách dòng) không trừ thêm và không hoàn"; "huỷ món CHƯA served thì không có gì để hoàn, tồn không đổi" | **A–F: không đường nào hoàn kho.** `CancelOrderItem` (Phase 1/2) không gọi bất kỳ Action kho nào — kiểm chứng: `CancelOrderItem.php` không import gì từ `App\Domain\Inventory`. ⚠️ **Điểm yếu:** bất biến này chỉ được giữ bởi "không ai viết code hoàn kho". Không có chốt chặn nào ngăn Phase sau vô tình thêm. Cần một dòng luật rõ trong CLAUDE.md |
| **K7** | Dòng món tách ra khi huỷ một phần không bao giờ trừ kho | **CẢ HAI** | **Code:** `DeductStockForServedItem.php:44-46` (`if ($item->split_from_item_id !== null) return;`). **DB:** cùng khoá `uq_stock_movements_ref` (K5). **Đối soát:** `ReconcileStockLedger.php:114` (`whereNull('split_from_item_id')`) | `DeductStockForServedItemTest` — "huỷ MỘT PHẦN món đã served (tách dòng) không trừ thêm và không hoàn — dòng tách kế thừa served_at nhưng không tạo sổ cái"; `ReconcileStockLedgerTest` — "dòng tách ra khi huỷ một phần (split_from_item_id) không bị báo lệch giả dù không có sổ cái riêng" | **B: chặn ngay dòng đầu tiên của Action**, trước cả khi chạm database. **A, C, D, E, F:** không đụng tới `order_items`, không liên quan. ⚠️ **Điểm cần soi:** guard nằm ở dòng 44, TRƯỚC cả `$daTruRoi`. Nếu ai đó đảo thứ tự hai khối này thì K7 vẫn đúng (vì dòng tách không có sổ cái riêng nên `$daTruRoi` false → sẽ trừ nhầm). Nghĩa là **thứ tự hai khối này là quan trọng và không có comment cảnh báo tại chỗ** |
| **K8** | Hệ số quy đổi luôn là số nguyên ≥ 1 | **CẢ HAI** | **DB:** `2026_08_06_000003_create_ingredient_units_table.php:41` — `ck_ingredient_units_factor CHECK (factor >= 1)`, cột kiểu `unsignedInteger`. **Code:** `UnitConverter.php` (toàn số nguyên, ném lỗi khi không quy đổi được) | `UnitConverterTest` — 7 test, gồm "quy đổi ra số lẻ thì làm tròn đến số nguyên gần nhất, không cắt bỏ phần lẻ"; "không có đường quy đổi thì ném lỗi tiếng Việt, không trả về 0"; "số lượng âm thì ném lỗi ngay" | **Không đường nào trong A–F ghi vào `ingredient_units`.** Đường ghi duy nhất là màn hình Filament `IngredientResource`. Database chặn tuyệt đối bằng CHECK — kể cả SQL tay. ✅ Kín |
| **K9** | Tồn về 0 thì trị giá về 0; tồn dương thì trị giá không âm | **CẢ HAI** | **DB:** `2026_08_06_000006_create_stock_balances_table.php:36-37` — `ck_stock_balances_zero CHECK (qty <> 0 OR total_cost = 0)` và `ck_stock_balances_cost CHECK (qty <= 0 OR total_cost >= 0)`. **Code (van an toàn báo lỗi sớm, thông báo dễ đọc):** `RecordStockMovement.php:57-67`. **Thuật toán bảo đảm:** `StockCost.php:54-57` (nhánh `$qty === $n` trả thẳng `$totalCost` để về đúng 0, không sót đồng lẻ) | `StockCostTest` — "trường hợp biên 1: xuất đúng bằng tồn thì total_cost về đúng 0, không đồng lẻ"; "trường hợp biên 4: tồn về 0 rồi nhập lại thì giá trung bình mới hoàn toàn"; `StockCostNoMoneyLostTest` | **A–F: không đường nào phá được**, vì mọi đường đi qua `RecordStockMovement`, nơi có van an toàn ở dòng 57–67 **trước** khi ghi, và database chặn lần hai. Van code ném `StockLedgerInvariantViolatedException` với thông báo nói rõ "lỗi lập trình, không phải lỗi dữ liệu" — đúng tinh thần. ✅ Kín cả hai tầng |
| **K10** | Phiếu kiểm kê đã chốt không sửa được, phải đủ người chốt/giờ chốt/tổng chênh | **CẢ HAI** | **DB:** `2026_08_10_000001_create_stock_takes_table.php:53` — `ck_stock_takes_closed CHECK (status <> 'closed' OR (closed_at IS NOT NULL AND closed_by_user_id IS NOT NULL AND total_diff_cost IS NOT NULL))`. **Code:** `CloseStockTake.php:40-42` (chặn chốt lần hai), `RecordStockTakeCount.php:26-28` (chặn sửa số đếm sau khi chốt) | `StockTakeTest` — "chốt phiếu hai lần chỉ sinh điều chỉnh một lần"; "sửa số đếm sau khi phiếu đã chốt thì bị chặn" | **E:** hai lớp. Code chặn với thông báo tiếng Việt, database chặn phần "đủ ba thông tin". ⚠️ **Lỗ:** database **không** chặn việc đổi `status` từ `closed` về `open` — CHECK chỉ kiểm khi status là `closed`. Một `UPDATE stock_takes SET status='open'` bằng SQL tay sẽ mở lại phiếu đã chốt. Không có Action nào làm; đây là lỗ lý thuyết |
| **K11** | Mọi dòng sổ cái phải chỉ về nguồn gốc | **CẢ HAI** | **DB:** `2026_08_07_000001_create_stock_movements_table.php:67` — `ck_stock_movements_ref CHECK (ref_type = 'manual' OR ref_id IS NOT NULL)`, cộng 4 khoá ngoại `RESTRICT` ở dòng 51–60 (`ingredient_id`, `approved_by_user_id`, `created_by_user_id`, `shift_id`). **Code:** mỗi đường tự truyền `refType`/`refId` đúng cặp | `ReconcileStockLedgerTest` — "dòng sổ cái mồ côi (trỏ về order_item chưa served) bị phát hiện" | **A:** `ref_type=purchase_item`, `ref_id=purchase_items.id`. **B:** `ref_type=order_item`, `ref_id=order_items.id`. **E:** `ref_type=stock_take_item`. **C, D, F:** `ref_type=manual`, `ref_id=NULL` — hợp lệ theo CHECK. ⚠️ **Lỗ:** **KHÔNG có khoá ngoại nào từ `ref_id` về bảng đích** (không thể có, vì `ref_id` trỏ vào 3 bảng khác nhau tuỳ `ref_type`). Nghĩa là một `ref_id` trỏ vào dòng không tồn tại vẫn ghi được. Đối soát Bước 9 chỉ kiểm nhánh `order_item` (mục "sổ cái mồ côi"), **KHÔNG kiểm** nhánh `purchase_item` và `stock_take_item`. 🟡 Đây là chỗ hở thật của K11 |
| **K12** | Điều chỉnh tay bắt buộc có người duyệt và lý do ≥ 10 ký tự | **CẢ HAI** | **DB:** `2026_08_07_000001_create_stock_movements_table.php:69-75` — `ck_stock_movements_adjust CHECK (type <> 'adjust' OR (approved_by_user_id IS NOT NULL AND reason IS NOT NULL AND CHAR_LENGTH(reason) >= 10))`. **Code:** `AdjustStock.php:36` (hằng số 10), `:54-57` (kiểm `mb_strlen`), `:59-67` (bắt buộc PIN chủ quán) | `AdjustStockTest` — 7 test, gồm "lý do quá ngắn (ghi qua loa) bị chặn, không tạo dòng sổ cái nào"; "người duyệt PIN phải là chủ quán, thu ngân duyệt hộ cũng bị chặn"; `RecordStockMovementTest` — "điều chỉnh thiếu người duyệt hoặc lý do ngắn thì bị database chặn (ck_stock_movements_adjust)" | **D:** hai lớp, kín. `mb_strlen` (PHP, đếm ký tự) khớp `CHAR_LENGTH` (SQL, đếm ký tự) — không lệch với tiếng Việt có dấu. **F (`PosDemo`):** gọi thẳng `RecordStockMovement` nên **bỏ qua toàn bộ kiểm tra PIN của `AdjustStock`** — nếu diễn tập ghi `type = adjust` thì database vẫn chặn (thiếu approver/reason), nên vẫn kín. Hiện `PosDemo` chỉ ghi `type = purchase` |
| **K13** | Chỉ có đúng một phiếu kiểm kê đang mở | **CẢ HAI** | **DB:** `2026_08_10_000001_create_stock_takes_table.php:43` — `unique('open_guard', 'uq_stock_takes_only_one_open')` trên generated column. **Code:** `OpenStockTake.php:43-46` (kiểm trước để báo lỗi tiếng Việt) | `StockTakeTest` — "đã có phiếu kiểm kê đang mở thì không mở thêm phiếu mới" | **E:** hai lớp, generated column + khoá duy nhất chặn tuyệt đối kể cả SQL tay. ✅ Kín. Cùng khuôn với `uq_shifts_only_one_open` đã dùng ở Phase 1 |
| **K14** | Mỗi nguyên liệu chỉ có đúng một đơn vị nhập mặc định | **CHỈ DB** | `2026_08_06_000003_create_ingredient_units_table.php:38` — `unique('purchase_default_guard', 'uq_ingredient_units_default')` trên generated column | `InventorySeederTest` — "mỗi nguyên liệu chỉ có đúng một đơn vị nhập mặc định" | **A–F: không đường nào ghi vào `ingredient_units`.** Đường ghi là màn hình Filament `IngredientResource`. Generated column + khoá duy nhất chặn tuyệt đối. ⚠️ **Không có kiểm tra ở tầng code**, nên người dùng bấm sai trên Filament sẽ thấy **lỗi khoá duy nhất thô của database**, không phải thông báo tiếng Việt. So với K13 (có kiểm trước ở code để báo đẹp) thì đây là chỗ chưa nhất quán |
| **K15** | Hàng hỏng vỡ bắt buộc ghi lý do | **CẢ HAI (nhưng lệch ngưỡng)** | **DB:** `2026_08_07_000001_create_stock_movements_table.php:77-79` — `ck_stock_movements_waste_reason CHECK (type <> 'waste' OR (reason IS NOT NULL AND CHAR_LENGTH(reason) >= 5))`. **Code:** `WriteOffStock.php:46-49` — chỉ chặn chuỗi **rỗng**, không chặn chuỗi ngắn | `WriteOffStockTest` — "bắt buộc ghi rõ lý do, không cho ghi qua loa (chuỗi rỗng)"; `RecordStockMovementTest` — "waste không ghi lý do đủ dài thì bị database chặn (ck_stock_movements_waste_reason)" | **C:** 🟡 **Hai tầng KHÔNG khớp ngưỡng.** Code chấp nhận lý do 1 ký tự; database đòi ≥ 5. Lý do "x" chỉ qua được database **nhờ tiền tố `[Vỡ/hỏng] ` mà `ghepLyDo()` gắn thêm** (`WriteOffStock.php:65-68`) làm chuỗi dài lên. Đây là an toàn do may mắn, không do thiết kế: bỏ tiền tố đi là thu ngân gặp lỗi database thô. **Nên sửa:** thêm kiểm `mb_strlen($chiTiet) >= 5` trong code, giống cách `AdjustStock` làm với ngưỡng 10. **F (`PosDemo`):** không ghi `type = waste`, không liên quan |

## Tổng kết mục 4

| Trạng thái | Bất biến |
|---|---|
| ✅ Kín cả hai tầng | K8, K9, K13 |
| ✅ Kín, hai tầng, có test đủ | K5 (với ref thật), K7, K10, K12 |
| 🟡 Kín ở tầng code nhưng tầng DB có lỗ | K1 (`update` không bị chặn), K10 (đổi ngược status), K11 (ref_id không có FK) |
| 🟡 Hai tầng lệch ngưỡng | K15 |
| 🟡 Chỉ DB giữ, code không báo lỗi đẹp | K14 |
| 🟡 Chỉ code giữ, không có chốt DB | K2, K3, K4, K6 (đúng thiết kế — đó là lý do có Bước 9) |
| 🔴 Có lỗ nghiệp vụ thật | **K5 với `ref_type=manual`** — hao hụt/điều chỉnh bấm hai lần thì trừ hai lần, không có `uuid` chống bấm trùng |

**KHÔNG CÓ bất biến nào ở trạng thái "CHƯA AI GIỮ".** Cả 15 đều có ít nhất một tầng giữ.

---

# 5. BẢNG MỌI ĐƯỜNG GHI VÀO KHO

| Action | Loại movement | Gọi `RecordStockMovement` hay tự ghi | Khoá gì, thứ tự nào | Có nằm trong transaction nào không |
|---|---|---|---|---|
| **`ReceivePurchase`** (`app/Domain/Inventory/Actions/ReceivePurchase.php`) | `purchase` (qty_delta > 0, `knownCost` = `line_cost` thật) | **Gọi `RecordStockMovement`** (dòng 45). Docblock nói rõ "KHÔNG BAO GIỜ tự ghi vào stock_balances" | `Purchase` (dòng 30, `lockForUpdate`) → `StockBalance` (bên trong một cửa, theo `ingredient_id` **tăng dần** — sắp bằng `orderBy('ingredient_id')` ở dòng 42) | **CÓ.** `DB::transaction` mở ở dòng 29, bọc trọn cả việc ghi sổ cái và đổi `status` phiếu. `RecordStockMovement` mở transaction lồng bên trong (thành savepoint) |
| **`DeductStockForServedItem`** (`app/Domain/Inventory/Actions/DeductStockForServedItem.php`) | `sale` (qty_delta < 0, `knownCost = null` → giá vốn bình quân) | **Gọi `RecordStockMovement`** (dòng 66) | Không tự khoá gì. `StockBalance` khoá bên trong một cửa, theo `ingredient_id` **tăng dần** (`orderBy('ingredient_id')` dòng 63) | **CÓ, nhưng transaction là của Action GỌI nó** — `UpdateOrderItemStatus.php:46`. Cùng transaction với việc đặt `served_at` (dòng 51–54). Trừ kho hỏng ⇒ `served_at` cũng rollback |
| **`WriteOffStock`** (`app/Domain/Inventory/Actions/WriteOffStock.php`) | `waste` (qty_delta < 0), `ref_type = manual`, `ref_id = NULL` | **Gọi `RecordStockMovement`** (dòng 51) | Chỉ `StockBalance` (một dòng, bên trong một cửa). Không khoá gì khác | **KHÔNG có transaction riêng.** Kiểm quyền/số lượng/lý do chạy ngoài, rồi transaction duy nhất là của `RecordStockMovement` (dòng 34 của file đó). Hợp lý: chỉ có một nguyên liệu, một dòng sổ cái |
| **`AdjustStock`** (`app/Domain/Inventory/Actions/AdjustStock.php`) | `adjust` (qty_delta dương hoặc âm), `ref_type = manual`, `ref_id = NULL` | **Gọi `RecordStockMovement`** (dòng 69) | Chỉ `StockBalance` (một dòng). `VerifyApproverPin` chạy **trước**, ngoài mọi transaction (CLAUDE.md mục 12) ✅ | **KHÔNG có transaction riêng** — cố ý, để PIN không bao giờ được hỏi giữa một giao dịch đang mở. Transaction duy nhất là của `RecordStockMovement` |
| **`CloseStockTake`** (`app/Domain/Inventory/Actions/CloseStockTake.php`) | `stocktake` (qty_delta = `diff_qty`, dương hoặc âm) | **Gọi `RecordStockMovement`** (dòng 52) | `StockTake` (dòng 38, `lockForUpdate`) → `StockBalance` (theo `ingredient_id` **tăng dần**, `orderBy('ingredient_id')` dòng 47) | **CÓ.** `DB::transaction` ở dòng 37, bọc cả việc sinh sổ cái và chốt phiếu + ghi `total_diff_cost` |
| **`OpenStockTake`** | *(không ghi kho)* | Chỉ **đọc** `StockBalance` (dòng 63–65) để chụp `system_qty` | Không khoá `StockBalance` | CÓ transaction (dòng 42) nhưng không ghi kho |
| **`RecordStockTakeCount`** | *(không ghi kho)* | Chỉ ghi `stock_take_items.counted_qty` | `StockTakeItem` (dòng 23) → `StockTake` (dòng 24) | CÓ transaction (dòng 22) |
| **`CreatePurchase` / `UpdatePurchase` / `CancelPurchase`** | *(không ghi kho)* | Chỉ ghi `purchases` / `purchase_items`. Kho chỉ đổi khi `ReceivePurchase` chạy | `Purchase` (`UpdatePurchase:33`, `CancelPurchase:27`) | CÓ transaction, nhưng không chạm `stock_balances`/`stock_movements` |
| **`PosDemo`** (`app/Console/Commands/PosDemo.php`, mốc `--den=tru-kho`) | `purchase` — nạp tồn đầu cho diễn tập | **Gọi `RecordStockMovement`** qua `app(RecordStockMovement::class)` | Chỉ `StockBalance` | **CÓ** — toàn bộ lệnh chạy trong `DB::beginTransaction()` và **luôn `rollBack()`** ở cuối. Không ghi thật gì vào database |

## Có Action nào TỰ GHI vào `stock_balances` không?

**KHÔNG. Không có một Action nào tự ghi.** Đây là kết luận chắc chắn, không phải dựa vào việc đọc code, mà dựa vào ba lớp bằng chứng:

1. **Tìm cơ học:** `grep -rn "StockMovement::query()->create\|StockMovement::create"` chỉ có đúng một kết quả — `RecordStockMovement.php:71`. Tương tự, không file nào ngoài `RecordStockMovement` ghi vào `stock_balances`.
2. **Chặn cứng ở tầng Model:** `StockBalance.php` khoá mọi ngả ghi bằng cờ `$ghiDuocPhep` (dòng 39) mặc định TẮT. Chỉ `RecordStockMovement::handle()` gọi được `choPhepGhi()` (dòng 35). Bị chặn: `save()` (dòng 102), `delete()` (dòng 109), và qua Builder tuỳ biến — `update()` (dòng 126), `insert()` (dòng 134), `increment()` (dòng 142), `decrement()` (dòng 150), `delete()` (dòng 157). Cờ **luôn được tắt lại trong `finally`** (dòng 79–81) kể cả khi closure ném lỗi.
3. **Test chứng minh 8 ngả đều bị chặn:** `tests/Feature/Inventory/OnlyOneStockWriterTest.php` — ghi thẳng, update instance, `firstOrCreate`, update qua quan hệ (`$ingredient->stockBalance()->update()` — đường KHÔNG đi qua `save()`), `increment()/decrement()`, xoá, và "choPhepGhi() luôn tắt cờ lại kể cả khi closure ném lỗi — không kẹt cờ mở mãi".

**Luật một cửa được giữ đúng.** Đây là điểm mạnh nhất của Phase 3.

⚠️ **Ba lưu ý còn lại:**

- **Cờ `static` là biến toàn cục của tiến trình.** Nếu chạy song song trong cùng một tiến trình (queue worker `--max-jobs` cao, Octane), cờ bật cho luồng A về lý thuyết mở cửa cho luồng B. PHP-FPM/queue mặc định là một tiến trình một việc nên không xảy ra ở dự án này, nhưng đáng ghi nếu sau này dùng Octane.
- **SQL tay bỏ qua toàn bộ tầng Model.** Đó chính là lý do Bước 9 tồn tại, và đúng là hai test "cố tình sửa tay bảng tồn cho lệch" dùng `DB::table(...)->update(...)` để mô phỏng — chứng minh Bước 9 bắt được.
- **`StockMovement` không có cơ chế `choPhepGhi()` tương ứng.** Ghi vào `stock_movements` từ ngoài `RecordStockMovement` **không bị chặn ở tầng Model** (chỉ xoá bị chặn). Hiện không ai làm, nhưng lớp bảo vệ của bảng sổ cái yếu hơn lớp bảo vệ của bảng tồn — hơi ngược, vì sổ cái mới là sự thật.

---

# 6. CHỖ NỐI KHO ↔ BÁN HÀNG

## a. Trừ kho được gọi ở đâu trong luồng bếp báo món xong? Nằm trong cùng transaction với việc đặt `served_at` không?

**Gọi ở `app/Domain/Ordering/Actions/UpdateOrderItemStatus.php:58`:**

```php
$this->deductStockForServedItem->handle($item, $data->updatedByUserId, $caDangMo);
```

**CÓ, nằm trong cùng transaction.** Chuỗi cụ thể:

| Dòng | Việc |
|---|---|
| `UpdateOrderItemStatus.php:46` | `DB::transaction(function () ... {` — mở giao dịch |
| `:47` | Khoá dòng món (`OrderItem::query()->lockForUpdate()->findOrFail(...)`) |
| `:49` | Kiểm bước chuyển trạng thái hợp lệ (`ordered → served`) |
| `:51-54` | **Đặt `status = served` và `served_at = now()`** |
| `:56` | Lấy id ca đang mở (không khoá) |
| **`:58`** | **Gọi trừ kho** |
| `:60-61` | Khoá phiếu và cập nhật trạng thái phiếu |
| `:63` | Trả về dòng món |

Không có Event, không có Listener, không có Job — trừ kho là một lời gọi thẳng nhìn thấy được, đúng CLAUDE.md mục 4.5.

## b. Trừ kho hỏng thì `served_at` có bị chặn không? Bếp thấy gì?

**CÓ, bị chặn.** Vì `$item->update([... 'served_at' => now()])` ở dòng 51 và lời gọi trừ kho ở dòng 58 nằm trong **cùng một `DB::transaction`**, bất kỳ ngoại lệ nào ném ra từ trừ kho sẽ rollback toàn bộ — `served_at` quay về `NULL`, `status` quay về `ordered`.

**Bếp thấy gì:** ngoại lệ `App\Exceptions\DomainException` được đổi thành **HTTP 422** ở `bootstrap/app.php` (một chỗ duy nhất), kèm thông báo tiếng Việt của ngoại lệ đó. Màn hình KDS sẽ báo lỗi và món **vẫn ở trạng thái "đang làm"** — bếp bấm lại được.

**Nhưng trên thực tế, gần như không có gì làm trừ kho hỏng.** Đây là chủ ý đã ghi ở `docs/schema.md` K.9: *"Với quyết định 'tồn âm được phép', gần như không còn lý do gì làm trừ kho thất bại — nên rủi ro chặn bếp là rất thấp."* Kiểm chứng bằng test: `DeductStockForServedItemTest` — *"nguyên liệu không đủ tồn vẫn cho phục vụ, tồn cho phép về âm"*.

Ba trường hợp còn lại làm trừ kho hỏng:
1. `StockLedgerInvariantViolatedException` — van K9 nổ, tức là lỗi lập trình trong tính giá vốn.
2. `StockCostOverflowException` — số tiền vượt 64 bit (không thể xảy ra ở quy mô quán nhậu).
3. Lỗi database (mất kết nối, kẹt khoá quá lâu).

⚠️ **Đánh giá:** cả ba đều là lỗi hệ thống, không phải lỗi vận hành. Nhưng hệ quả là **bếp không báo được món xong** khi hệ thống kho hỏng. Cần chủ quán xác nhận: có chấp nhận rằng lỗi kho thì bếp đứng, hay muốn "ghi log rồi cho qua"? Thiết kế hiện tại chọn phương án chặt (đúng K.9), và đó là đúng cho việc giữ sổ sách khớp.

## c. Một dòng món có thể trừ kho HAI LẦN qua đường nào không?

**Không.** Dưới đây là mọi đường tôi đã thử nghĩ tới và lý do mỗi đường bị chặn:

| # | Đường có thể trừ hai lần | Bị chặn bởi | Bằng chứng |
|---|---|---|---|
| 1 | **Bếp bấm "xong" hai lần trên KDS** | `StatusTransition::kiemTra(['ordered','served'], ...)` ở `UpdateOrderItemStatus.php:49` — lần hai thấy trạng thái đã là `served`, không có bước chuyển `served → served`, ném lỗi trước khi tới trừ kho | Cơ chế Phase 1, đã có test bước chuyển trạng thái |
| 2 | **Gọi thẳng `DeductStockForServedItem` hai lần** (bỏ qua tầng trạng thái) | `DeductStockForServedItem.php:48-55` — truy vấn `stock_movements` xem đã có dòng nào `ref_type=order_item, ref_id=$item->id` chưa. Có rồi thì `return` lặng lẽ, không ném lỗi | ✅ Test: `DeductStockForServedItemTest` — *"gọi trừ kho hai lần cho cùng dòng món chỉ trừ đúng một lần, không ném lỗi"* |
| 3 | **Hai máy POS cùng bấm cùng lúc** (lớp code ở #2 cùng đọc "chưa trừ") | Hai lớp: (i) `OrderItem::query()->lockForUpdate()` ở `UpdateOrderItemStatus.php:47` — máy thứ hai chờ máy thứ nhất commit, rồi thấy trạng thái đã `served` và bị #1 chặn. (ii) Kể cả khi lớp (i) hỏng: **khoá duy nhất database `uq_stock_movements_ref (ref_type, ref_id, ingredient_id)`** làm INSERT thứ hai nổ lỗi trùng khoá, cả giao dịch rollback | Khoá ở `2026_08_07_000001_create_stock_movements_table.php:46` |
| 4 | **Dòng tách ra khi huỷ một phần** (kế thừa `served_at` của dòng gốc) | `DeductStockForServedItem.php:44-46` — `if ($item->split_from_item_id !== null) return;` ngay dòng đầu | ✅ Test — xem câu d bên dưới |
| 5 | **Món đến muộn qua đồng bộ offline, rồi được phục vụ** | Cùng cơ chế #2 + #3. Thao tác đồng bộ đi qua đúng `UpdateOrderItemStatus`, không có đường tắt riêng | ✅ Test — xem câu f bên dưới |
| 6 | **Huỷ món đã phục vụ rồi phục vụ lại** | Không có bước chuyển `cancelled → served` trong `CHUOI_MON` (`UpdateOrderItemStatus.php:35` chỉ có `['ordered','served']`). Và kể cả nếu có, lớp #2 vẫn chặn | ✅ Test: *"huỷ món ĐÃ served thì không hoàn kho"* |
| 7 | **Đổi `quantity` của dòng món sau khi đã phục vụ rồi bấm xong lại** | Lớp #2 chặn — đã có sổ cái cho `ref_id` này thì không trừ nữa, bất kể `quantity` đổi thành bao nhiêu. ⚠️ **Hệ quả phụ:** nếu ai đó sửa được `quantity` sau khi đã trừ kho thì **kho sẽ trừ theo số cũ** và không có gì báo. `UpdateOrderItem` (Phase 1) có chặn sửa dòng đã `served` hay không là chỗ Opus nên kiểm chéo |
| 8 | **Thêm một dòng `recipes` mới cho biến thể SAU khi món đã phục vụ, rồi gọi lại trừ kho** | Lớp #2 chặn (đã có ít nhất một dòng sổ cái cho `ref_id` này ⇒ `return` sớm). Nguyên liệu mới thêm **sẽ không bao giờ được trừ** cho dòng món cũ — đúng, vì hoá đơn cũ không đổi. Khoá duy nhất `uq_stock_movements_ref` chỉ chặn theo bộ ba `(ref_type, ref_id, ingredient_id)`, nên nếu lớp code #2 bị bỏ đi thì nguyên liệu MỚI sẽ trừ thêm được. **Lớp code là chốt duy nhất cho tình huống này** |
| 9 | **Chạy lại `SummarizeProductProfit` nhiều lần** | Không ghi kho, chỉ đọc `stock_movements`. Không phải đường trừ kho |
| 10 | **Xoá dòng sổ cái rồi phục vụ lại** | `StockMovement` chặn cứng mọi đường xoá (`:96`, `:101`, `:115`, `:120`) | ✅ Test: `StockMovementImmutableTest` ×3 |

## d. Dòng món tách ra khi huỷ một phần: có bị trừ kho không? Chứng minh bằng tên test.

**KHÔNG bị trừ kho.** Chặn ở `DeductStockForServedItem.php:44-46`, dòng đầu tiên của `handle()`, trước cả khi chạm database.

**Hai test chứng minh:**

1. `tests/Feature/Inventory/DeductStockForServedItemTest.php:171` —
   > *"huỷ MỘT PHẦN món đã served (tách dòng) không trừ thêm và không hoàn — dòng tách kế thừa served_at nhưng không tạo sổ cái"*

2. `tests/Feature/Inventory/ReconcileStockLedgerTest.php:120` —
   > *"dòng tách ra khi huỷ một phần (split_from_item_id) không bị báo lệch giả dù không có sổ cái riêng"*

Test thứ hai quan trọng ngang test thứ nhất: nó chứng minh **lệnh đối soát cũng biết luật này**, nên không báo động giả.

## e. Lệnh đối soát Bước 9 có loại trừ dòng có `split_from_item_id` không?

**CÓ.** `app/Domain/Inventory/Actions/ReconcileStockLedger.php:114`:

```php
$donMonServed = OrderItem::query()
    ->whereNotNull('served_at')
    ->whereNull('split_from_item_id')      // ← dòng 114
    ->when($tuNgay !== null, ...)
    ...
```

Docblock của Action còn trích thẳng dòng 1894 của `docs/schema.md` để giải thích vì sao (dòng 25–28).

Test bảo vệ: `ReconcileStockLedgerTest` — *"dòng tách ra khi huỷ một phần (split_from_item_id) không bị báo lệch giả dù không có sổ cái riêng"*.

⚠️ **Một chi tiết Opus nên soi:** vế **ngược lại** (tìm "sổ cái mồ côi", dòng 144–157) **không** loại trừ dòng tách — nhưng không cần, vì dòng tách không bao giờ có sổ cái, nên nó không thể xuất hiện trong danh sách sổ cái. Đúng.

## f. Món đến muộn qua đồng bộ rồi được phục vụ: trừ kho đúng một lần không?

**ĐÚNG MỘT LẦN.** Test trực tiếp:

`tests/Feature/Inventory/DeductStockForServedItemTest.php:227` —
> *"món đến muộn qua đồng bộ rồi được phục vụ thì trừ kho đúng một lần"*

Lý do cấu trúc: thao tác đồng bộ (`SyncBatch`) tạo ra dòng món **qua đúng những Action thường** (`PlaceOrder`), không có đường tắt ghi thẳng vào `order_items`. Khi món đó được phục vụ, nó đi qua đúng `UpdateOrderItemStatus` như mọi món khác, nên hưởng đủ cả ba lớp chặn (#1 trạng thái, #2 kiểm sổ cái, #3 khoá duy nhất).

⚠️ **Một chỗ chưa có test:** nếu **thao tác "báo món xong" cũng đến muộn qua đồng bộ** (bếp offline đánh dấu xong, đồng bộ lên sau), món sẽ có `served_at` là giờ **đồng bộ**, không phải giờ bếp bấm — và dòng sổ cái cũng vậy (`occurred_at` = `now()` ở `RecordStockMovement.php:85`). Với ca vắt qua nửa đêm, một món bưng ra lúc 23:50 có thể được ghi sổ cái lúc 00:10 hôm sau, rơi vào tháng khác nếu là đêm cuối tháng. Đây là chỗ Opus nên xác nhận — `RecordStockMovementData` **có** trường `occurredAt` nhưng **không đường nào truyền vào** (mọi lời gọi để `null` ⇒ dùng `now()`).

---

# 7. THỨ TỰ KHOÁ — MỌI ACTION CÓ `DB::transaction`

**Luật hiện hành (CLAUDE.md mục 11 + `docs/schema.md` K.9):**

```
SyncConflict → Promotion → Payment → TableSession → Shift → DiningTable → Purchase → StockTake → StockBalance
```

⚠️ **Ghi chú đầu tiên:** chuỗi này ở `docs/schema.md` K.9 đã có, nhưng **`CLAUDE.md` mục 11 CHƯA được cập nhật** — nó vẫn dừng ở `DiningTable`, chưa có `Purchase → StockTake → StockBalance`. Đây là vi phạm chính câu luật cuối của mục 11: *"Phase sau thêm loại đối tượng mới cần khoá thì phải bổ sung vào chuỗi này TRƯỚC khi viết Action đầu tiên khoá nó."* Ba loại kho đã được viết vào `schema.md` nhưng **chưa chép sang `CLAUDE.md`**. Cần bổ sung trước khi đóng Phase 3.

Ngoài ra, hai loại **không nằm trong chuỗi luật** nhưng có bị khoá trong code: **`Order`**, **`OrderItem`**, **`StockTakeItem`**.

---

| Action | Khoá gì, theo thứ tự nào | Khớp luật CLAUDE.md mục 11 chưa |
|---|---|---|
| `Sync\ResolveSyncConflict` | `SyncConflict`(:132) → `Shift`(:441) / `TableSession`(:566, :684) → `Shift`(:686) | ✅ Khớp. PIN duyệt xong trước transaction (`:77`, `:171`) |
| `Sync\SyncBatch` | Không tự khoá; gọi các Action con | ✅ |
| `Billing\ApplyPromotion` | `Promotion`(:48-50) → `TableSession`(:59) | ✅ Khớp |
| `Billing\CalculateBill` | `TableSession`(:55) | ✅ |
| `Billing\RecordPayment` | `TableSession`(:49) → `Shift`(:55) | ✅ Khớp |
| `Billing\VoidPayment` | `Payment`(:63) → `TableSession`(:75) → `Shift`(:92-95, :132) | ✅ Khớp |
| `Ordering\OpenTableSession` | `Shift`(:55) → `DiningTable`(:62-65, theo `id` tăng dần) | ✅ Khớp |
| `Ordering\AttachTable` | `TableSession`(:21) → `DiningTable`(:27) | ✅ Khớp |
| `Ordering\DetachTable` | `TableSession`(:40) | ✅ |
| `Ordering\TransferTable` | `TableSession`(:29) → `DiningTable`(:37-40) | ✅ Khớp |
| `Ordering\SplitTableSession` | `TableSession`(:73) → `Shift`(:83) → `DiningTable`(:91-94) | ✅ Khớp |
| `Ordering\CloseTableSession` | `TableSession`(:27) | ✅ |
| `Ordering\VoidTableSession` | `TableSession`(:28) | ✅ |
| `Ordering\PlaceOrder` | `TableSession`(:52) | ✅ |
| `Ordering\UpdateOrderItem` | `Order`(:21) | ⚠️ `Order` không có trong chuỗi luật |
| `Ordering\RemoveOrderItem` | `Order`(:27) | ⚠️ như trên |
| `Ordering\CancelOrderItem` | `Order`(:44) → `OrderItem`(:45) | ⚠️ Cha trước con. Không đụng kho |
| `Ordering\MoveOrderItem` | `TableSession`(:51-54, nhiều dòng theo `id`) → `OrderItem`(:79-82) | ⚠️ `OrderItem` không có trong chuỗi luật |
| **`Ordering\UpdateOrderItemStatus`** | `OrderItem`(:47) → **`StockBalance`**(:58, trong `DeductStockForServedItem`) → **`Order`**(:60) | 🔴 **KHÔNG KHỚP** — xem phân tích bên dưới |
| `Staffing\OpenShift` | `Shift`(:31) | ✅ |
| `Staffing\CloseShift` | `Shift`(:41). Đối soát kho chạy **ngoài** transaction (chỉ đọc) | ✅ |
| `Staffing\RecordCashMovement` | `Shift`(:22) | ✅ |
| `Staffing\VerifyApproverPin` | `User`(:68) — trong transaction riêng, chạy trước mọi transaction nghiệp vụ | ✅ Đúng CLAUDE.md mục 12 |
| `Catalog\SetDefaultProductVariant` | Không khoá dòng nào | ✅ Không đụng tiền/kho |
| **`Inventory\CreatePurchase`** | Không khoá gì (tạo mới) | ✅ |
| **`Inventory\UpdatePurchase`** | `Purchase`(:33) | ✅ Khớp |
| **`Inventory\CancelPurchase`** | `Purchase`(:27) | ✅ Khớp |
| **`Inventory\ReceivePurchase`** | `Purchase`(:30) → `StockBalance`(theo `ingredient_id` tăng dần) | ✅ Khớp đúng `Purchase → StockBalance` |
| **`Inventory\OpenStockTake`** | Không khoá `TableSession` (chỉ `exists()` không khoá, dòng 34–36, **ngoài** transaction), không khoá `StockBalance` (chỉ đọc, dòng 63) | ⚠️ Xem ghi chú 3 bên dưới |
| **`Inventory\RecordStockTakeCount`** | `StockTakeItem`(:23) → `StockTake`(:24) | ⚠️ **Con trước cha.** Xem ghi chú 4 |
| **`Inventory\CloseStockTake`** | `StockTake`(:38) → `StockBalance`(theo `ingredient_id` tăng dần) | ✅ Khớp đúng `StockTake → StockBalance` |
| **`Inventory\RecordStockMovement`** | `StockBalance`(:43-48) — **đúng một dòng mỗi lần gọi** | ✅ Luôn ở cuối chuỗi |
| `Inventory\WriteOffStock` | `StockBalance` (qua một cửa) | ✅ |
| `Inventory\AdjustStock` | `StockBalance` (qua một cửa). PIN xong trước | ✅ |
| `Reporting\SummarizeProductProfit` | Không khoá dòng nào | ✅ Chỉ đọc + ghi bảng chốt |
| `Reporting\SummarizeIngredientWasteMonthly` | Không khoá dòng nào | ✅ |
| `Reporting\SummarizeDailyReport` | Không khoá dòng nào | ✅ |

## Cặp nào khoá NGƯỢC THỨ TỰ NHAU?

### 🔴 Cặp 1 — `UpdateOrderItemStatus` khoá `StockBalance` TRƯỚC `Order`

Đây là **vi phạm duy nhất, và là vi phạm thật**.

`docs/schema.md` K.9 chốt luồng này là:

```
OrderItem → Order → StockBalance (theo ingredient_id tăng dần)
```

Code thật (`UpdateOrderItemStatus.php`):

```
dòng 47  OrderItem      lockForUpdate
dòng 58  StockBalance   lockForUpdate  ← trong DeductStockForServedItem
dòng 60  Order          lockForUpdate  ← SAU StockBalance
```

`StockBalance` bị khoá **trước** `Order`, ngược hẳn với tài liệu. Câu khẳng định trong `schema.md` K.9 — *"Không luồng nào khoá StockBalance trước rồi mới khoá thứ khác. Đó là điều kiện đủ để chứng minh không có vòng kẹt"* — **đã không còn đúng**.

**Có kẹt chéo thật chưa?** Chưa. Muốn kẹt thì cần một Action khác khoá `Order` **rồi mới** khoá `StockBalance`. Hiện không có: `CancelOrderItem` và `UpdateOrderItem` khoá `Order` nhưng không đụng kho. Nên hôm nay chỉ có **hai luồng `UpdateOrderItemStatus` chạy song song**, cả hai cùng thứ tự, không kẹt.

**Nhưng chứng minh "không kẹt" đã mất.** Bất kỳ Action tương lai nào (ví dụ "huỷ món đã phục vụ có hoàn kho" ở Phase 4, hoặc một Action gộp phiếu chạm cả `Order` lẫn kho) khoá theo thứ tự tài liệu (`Order → StockBalance`) sẽ tạo vòng kẹt ngay lập tức.

**Sửa rất nhỏ** — đổi chỗ hai khối, chuyển dòng 60 lên trước dòng 58:

```php
$item->update([...]);
$caDangMo = Shift::query()->where('status', ShiftStatus::Open)->value('id');
$order = Order::query()->lockForUpdate()->findOrFail($item->order_id);   // ← lên trước
$this->deductStockForServedItem->handle($item, $data->updatedByUserId, $caDangMo);
$this->capNhatTrangThaiPhieu($order);
```

Tôi **không sửa** vì khoá phạm vi lần này là chỉ báo cáo.

### ⚠️ Ghi chú 2 — `Order`/`OrderItem` không nằm trong chuỗi luật

Chuỗi ở CLAUDE.md mục 11 không có `Order` và `OrderItem`, dù 6 Action khoá chúng. Vì `UpdateOrderItemStatus` giờ chạm cả `OrderItem`, `Order` và `StockBalance` trong một giao dịch, **chuỗi luật cần được nối lại cho đầy đủ**, ví dụ:

```
SyncConflict → Promotion → Payment → TableSession → Shift → DiningTable
             → Order → OrderItem → Purchase → StockTake → StockTakeItem → StockBalance
```

(Vị trí chính xác của `Order`/`OrderItem` trong chuỗi cần Opus quyết — tôi chỉ nêu là chỗ thiếu.)

### ⚠️ Ghi chú 3 — `OpenStockTake` kiểm bàn mở NGOÀI transaction, không khoá

`OpenStockTake.php:34-40` kiểm "còn bàn nào đang mở không" **trước** khi mở `DB::transaction` (dòng 42), và dùng `exists()` không khoá. Giữa lúc kiểm và lúc phiếu kiểm kê được tạo, một bàn mới có thể mở ra — và phiếu kiểm kê vẫn được lập trong khi có bàn đang bán. Số `system_qty` chụp lại sẽ lệch với thực tế đang chạy.

Xác suất thực tế rất thấp (kiểm kê làm lúc đóng cửa). Sửa đúng cách khá tốn: phải khoá mọi `TableSession` đang mở, mà `TableSession` đứng **trước** `StockTake` trong chuỗi khoá nên về thứ tự thì hợp lệ. Cần Opus quyết có đáng sửa không.

### ⚠️ Ghi chú 4 — `RecordStockTakeCount` khoá con trước cha

`RecordStockTakeCount.php:23-24` khoá `StockTakeItem` **rồi mới** khoá `StockTake`. Còn `CloseStockTake.php:38` khoá `StockTake` trước (và không khoá `StockTakeItem` chút nào, chỉ `->get()` thường).

Vì `CloseStockTake` **không khoá** `StockTakeItem`, hai Action này không tạo vòng kẹt hoàn chỉnh. Nhưng nếu Phase sau thêm `lockForUpdate()` vào truy vấn dòng của `CloseStockTake` (dòng 44–48), vòng kẹt hình thành ngay: A giữ `StockTakeItem` chờ `StockTake`, B giữ `StockTake` chờ `StockTakeItem`. **Nên đảo `RecordStockTakeCount` thành `StockTake` → `StockTakeItem` cho nhất quán** (cha trước con, giống `CancelOrderItem` đã làm với `Order → OrderItem`).

## Riêng `StockBalance`: có Action nào khoá nhiều dòng mà KHÔNG theo `ingredient_id` tăng dần không?

**KHÔNG. Cả ba Action khoá nhiều dòng `stock_balances` đều sắp theo `ingredient_id` tăng dần**, và mỗi lần `RecordStockMovement::handle()` chỉ khoá **đúng một dòng**:

| Action | Chỗ sắp thứ tự | Test |
|---|---|---|
| `ReceivePurchase` | `ReceivePurchase.php:42` — `$purchase->items()->orderBy('ingredient_id')->get()` | ✅ `ReceivePurchaseTest` — *"phiếu có 3 nguyên liệu thì sổ cái được ghi theo thứ tự ingredient_id tăng dần"* |
| `DeductStockForServedItem` | `DeductStockForServedItem.php:63` — `$bienThe->recipes()->orderBy('ingredient_id')->get()` | Gián tiếp: `DeductStockForServedItemTest` — *"phục vụ 1 lẩu gà trừ đúng từng nguyên liệu theo định lượng"* (nhiều nguyên liệu). **Không có test riêng về thứ tự** |
| `CloseStockTake` | `CloseStockTake.php:47` — `->orderBy('ingredient_id')` | **Không có test riêng về thứ tự** |
| `WriteOffStock`, `AdjustStock`, `PosDemo` | Chỉ một nguyên liệu mỗi lần — không có vấn đề thứ tự | — |

⚠️ **Điểm yếu:** chỉ **một trong ba** đường khoá nhiều dòng có test bảo vệ thứ tự (`ReceivePurchase`). Hai đường còn lại giữ đúng thứ tự **chỉ nhờ một dòng `orderBy` không được test bảo vệ** — xoá nhầm nó đi thì mọi test vẫn xanh, và kẹt chéo chỉ lộ ra ở quán vào giờ cao điểm. Đề nghị thêm hai test cùng khuôn với test đã có cho `ReceivePurchase`.

---

# 8. NHỮNG CHỖ TỰ QUYẾT VÀ NHỮNG CHỖ KHÔNG CHẮC

## 8.1. Những chỗ đã tự quyết vì tài liệu không nói rõ

| # | Chỗ | Đã quyết thế nào | Vì sao |
|---|---|---|---|
| 1 | **Loại hao hụt (vỡ/hỏng, hết hạn, hao hụt tự nhiên, dùng nội bộ)** | Không thêm cột vào `stock_movements`; ghép thành **tiền tố của `reason`**: `[Vỡ/hỏng] làm rơi khay` | `docs/schema.md` chốt `stock_movements.type` chỉ có 7 giá trị, không có chỗ riêng cho từng loại hao hụt. Thêm cột = đổi schema = phải hỏi (CLAUDE.md mục 7.2). **Hệ quả xấu:** muốn thống kê "tháng này vỡ bao nhiêu, hết hạn bao nhiêu" thì phải tìm chuỗi trong `reason` — không có chỉ mục, không chắc chắn |
| 2 | **Ai được ghi hao hụt** | Chủ quán **và** thu ngân. Phục vụ/bếp không được | Không có tài liệu nào nói. Chọn theo khuôn `PurchasePolicy` đã có |
| 3 | **Hao hụt không cần PIN duyệt; điều chỉnh tay thì cần** | Hao hụt: chỉ cần đúng vai trò. Điều chỉnh tay: bắt buộc PIN **chủ quán** | Hao hụt có vật chứng (lon vỡ nằm đó); điều chỉnh tay là ghi đè số liệu không có chứng từ gốc |
| 4 | **Độ dài tối thiểu của lý do điều chỉnh** | 10 ký tự (`AdjustStock.php:36`), khớp CHECK ở database | `docs/schema.md` K12 ghi "≥ 10 ký tự", đã theo đúng |
| 5 | **Kiểm kê chặn khi còn bàn mở** | Chặn khi còn `TableSession` ở trạng thái `open` hoặc `billing` | Tài liệu không nói. Kiểm kê chỉ có nghĩa khi kho đứng yên |
| 6 | **Dòng kiểm kê chưa đếm (`counted_qty` NULL)** | Bỏ qua hoàn toàn, không sinh sổ cái, không báo gì | Tài liệu không nói. Chọn phương án "không đếm thì không điều chỉnh" thay vì "coi như đếm = 0" (phương án kia sẽ xoá sạch tồn của nguyên liệu bỏ sót) |
| 7 | **`shift_id` của nhập hàng và kiểm kê** | Để `null` — hai việc này không gắn ca | Nhập hàng buổi sáng khi chưa ai mở ca. **Hệ quả:** báo cáo theo ca không thấy tiền nhập hàng |
| 8 | **Khoảng ngày đối soát lúc đóng ca** | Từ `opened_at` tới `closed_at`, không phải "ngày mở ca" | Ca vắt qua nửa đêm là bình thường ở quán nhậu. Có test riêng |
| 9 | **Đối soát chạy NGAY, không đẩy hàng đợi** | `app(ReconcileStockLedger::class)->handle(...)` gọi thẳng trong `CloseShift`, bọc `try/catch`, lỗi chỉ ghi log | Chủ quán cần thấy cảnh báo ngay lúc đóng ca, không chờ worker. Lỗi đối soát không bao giờ được chặn đóng ca |
| 10 | **Nơi lưu kết quả đối soát** | `activity_log` (`log_name = 'doi-soat-kho'`), không tạo bảng mới | `spatie/laravel-activitylog` đã có sẵn. Tạo bảng mới = đổi schema = phải hỏi |
| 11 | **Phân bổ giảm giá về từng dòng món** | Chia theo tỉ lệ `line_amount/subtotal_amount`, `intdiv` cho mọi dòng trừ dòng cuối; dòng cuối nhận phần dư | Không có cột nào lưu giảm giá theo dòng. Đã ghi trong `docs/kiem-toan-kho.md` mục 5 và được duyệt ở Bước 0 |
| 12 | **Lấy dòng món của TOÀN BỘ lượt khách khi phân bổ giảm giá**, không chỉ dòng của ngày đang tổng hợp | Đã chọn "toàn bộ" | Bàn ngồi vắt qua nửa đêm có món ở hai ngày; nếu chỉ lấy một ngày thì cùng dòng món sẽ ra doanh thu khác nhau tuỳ ngày báo cáo chạy |
| 13 | **Ngưỡng "lãi thấp"** trên màn hình chủ quán | 15% (`GetOwnerProfitDashboard::NGUONG_TI_LE_LAI_THAP`) | Con số tự chọn, không có căn cứ nghiệp vụ. **Cần chủ quán chốt lại** |
| 14 | **Top N trên bảng xếp hạng** | 20 dòng | Con số tự chọn |
| 15 | **Giá vốn ước tính khi nguyên liệu chưa từng nhập** | Trả về 0, không ném lỗi | Bước 3 chạy trước Bước 4, lúc đó chưa có phiếu nhập nào |
| 16 | **Mã phiếu nhập và mã phiếu kiểm kê** | `NH-YYYYMMDD-0001`, `KK-YYYYMMDD-0001`, sinh bằng cách ghi mã tạm (uuid) rồi cập nhật từ id thật trong cùng transaction | Cùng khuôn `OpenTableSession` của Phase 1 |
| 17 | **Không cho hai dòng cùng một nguyên liệu trên một phiếu nhập** | Chặn, báo "gộp lại thành một dòng" | Hai dòng cùng nguyên liệu sẽ đụng khoá `uq_stock_movements_ref` lúc nhận hàng (cùng `ref_type=purchase_item` nhưng khác `ref_id`... thực ra không đụng) — chặn ở đây là để **số liệu dễ đọc**, không phải để tránh lỗi kỹ thuật. Đây là quyết định thẩm mỹ, có thể sai ý chủ quán |
| 18 | **`WriteOffStock` và `AdjustStock` không mở transaction riêng** | Để `RecordStockMovement` tự mở | Chỉ có một nguyên liệu, một dòng sổ cái. Với `AdjustStock` còn có lý do bắt buộc: PIN không được hỏi trong transaction đang mở |

## 8.2. Những chỗ tôi KHÔNG CHẮC đã đúng

Xếp theo mức độ nghiêm trọng.

### 🔴 Mức cao

| # | Chỗ | Vấn đề |
|---|---|---|
| A | **`config('database.default')` trả về `mariadb`, và server thật là MariaDB 10.4.32** | `CLAUDE.md` mục 2 chốt **MySQL 8.4** (đã kiểm chứng trên 8.4.11), `.env.example` ghi `DB_CONNECTION=mysql` cổng 3307. Nhưng `.env` trên máy dev đang là `DB_CONNECTION=mariadb`, cổng 3306, và `select version()` trả về **`10.4.32-MariaDB`**. Nghĩa là **toàn bộ 587 test xanh được xác nhận trên một engine KHÁC với engine mà schema.md thiết kế cho**. Cụ thể đáng lo: (i) MariaDB 10.4 không có collation `utf8mb4_0900_ai_ci` của MySQL 8 — nó im lặng dùng collation khác; (ii) cách MariaDB xử lý CHECK constraint, generated column và trigger khác MySQL ở chi tiết; (iii) mọi bất biến "DB giữ" trong bảng mục 4 mới chỉ được chứng minh trên MariaDB. **Đây là chỗ tôi không chắc nhất trong toàn Phase 3, và nó ảnh hưởng tới độ tin cậy của mọi kết luận về tầng database.** Cần chủ quán/Opus quyết: đổi máy dev về MySQL 8.4 rồi chạy lại, hay đổi tài liệu sang MariaDB? |
| B | **`StockMovementType::Return` có enum, có CHECK ở database, có test — nhưng KHÔNG có Action** | Không có nút bấm, không có endpoint, không có màn hình để trả hàng nhà cung cấp. `CancelPurchase` còn ghi comment chỉ sang "nghiệp vụ trả hàng nhà cung cấp (Bước 6)" — nghiệp vụ đó chưa tồn tại. Đây là **tính năng thiếu**, không phải lỗi. Cần quyết: bù trước khi đóng Phase 3, hay đẩy sang Phase 4? |
| C | **`UpdateOrderItemStatus` khoá `StockBalance` trước `Order`** | Ngược với `docs/schema.md` K.9. Chưa gây kẹt hôm nay, nhưng phá vỡ chứng minh "không có vòng kẹt". Xem mục 7 |
| D | **`CLAUDE.md` mục 11 chưa được cập nhật chuỗi khoá kho** | `schema.md` K.9 đã có `Purchase → StockTake → StockBalance`, `CLAUDE.md` chưa. Chính mục 11 nói phải cập nhật TRƯỚC khi viết Action đầu tiên khoá loại mới. Đã viết 6 Action rồi mới thấy |
| E | **Hao hụt/điều chỉnh không có cơ chế chống bấm trùng** | Cả hai dùng `ref_type = manual, ref_id = NULL`, mà NULL không trùng nhau trong khoá UNIQUE ⇒ `uq_stock_movements_ref` không chặn gì. Bấm hai lần vì mạng lag ⇒ trừ kho hai lần. `payments` có `uuid` để chống chuyện này, kho thì không. Cần Opus quyết có cần thêm `uuid` cho hai đường này không |

### 🟡 Mức trung bình

| # | Chỗ | Vấn đề |
|---|---|---|
| F | **`EstimateVariantCost.php:55` dùng phép chia FLOAT trên tiền** | `Money::fromInt((int) round($balance->total_cost / $balance->qty))`. CLAUDE.md mục 7 và 16 cấm dùng `float` cho tiền — mà `StockCost::lamTron()` tồn tại đúng để làm việc này bằng số nguyên. Đây là **ước tính hiển thị**, không ghi vào sổ, nên hậu quả nhỏ; nhưng nó là vi phạm luật rõ ràng và nên sửa thành `StockCost::lamTron($balance->total_cost, $balance->qty)` |
| G | **Không có đường nào sinh dòng `close_residual`** | `schema.md` K.4 (dòng 1393) nói: *"`qty` về 0 mà `total_cost` còn dư vài đồng → ghi một dòng sổ cái loại `close_residual`... Bất biến K9 bắt buộc điều này."* Nhưng `StockCost::giaVonXuat` nhánh `$qty === $n` đã trả thẳng `$totalCost` nên `total_cost` **luôn về đúng 0**, không bao giờ có phần dư. Nghĩa là `close_residual` **không cần thiết trên thực tế**. Tuy vậy, nếu nó thật sự cần trong một trường hợp tôi chưa nghĩ ra, thì **không có đường nào ghi được nó**: `qty_delta = 0` sẽ bị `ck_stock_movements_delta` chặn khi `cost_delta` cũng bằng 0, và `RecordStockMovement` không có nhánh nào ép `cost_delta` cho loại này. Cần Opus xác nhận `close_residual` là code chết an toàn |
| H | **`StockMovement::query()->update()` không bị chặn** | Model chặn `delete`/`forceDelete` ở cả instance lẫn Builder, nhưng **không chặn `update`**. K1 nói "không bao giờ sửa". Không ai đang sửa, nhưng lớp bảo vệ hở |
| I | **`RecordStockMovementData::occurredAt` không có đường nào truyền vào** | Mọi lời gọi để `null` ⇒ dùng `now()`. Với thao tác đến muộn qua đồng bộ, thời điểm ghi sổ cái là giờ đồng bộ, không phải giờ việc xảy ra thật. Ảnh hưởng tới báo cáo hao hụt theo tháng nếu việc xảy ra đêm cuối tháng |
| J | **`SummarizeProductProfit` cắt theo `sent_at` ngày dương lịch, không theo ca** | Món gọi sau nửa đêm rơi vào ngày hôm sau, dù thuộc cùng một ca. Có thể lệch với cách `SummarizeDailyReport` của Phase 2 cắt. Tôi **chưa kiểm chéo** hai bảng báo cáo có nhất quán không |
| K | **Bảng "sổ cái mồ côi" báo đỏ vĩnh viễn** | Đối soát quét toàn bộ lịch sử, không lọc ngày. Một dòng mồ côi (ví dụ dòng món bị huỷ `served_at` sau khi đã trừ kho) sẽ báo đỏ mãi — và **không có công cụ nào xử lý nó**, vì sổ cái không xoá được. Chưa nghĩ ra cách thoát |
| L | **`ck_stock_movements_waste_reason` đòi ≥ 5 ký tự, code chỉ chặn chuỗi rỗng** | Qua được chỉ nhờ tiền tố `[Vỡ/hỏng] `. An toàn do may mắn, không do thiết kế |
| M | **`ref_id` không có khoá ngoại nào** (không thể có — trỏ 3 bảng). Đối soát Bước 9 **chỉ kiểm nhánh `order_item`**, không kiểm `purchase_item`/`stock_take_item` | K11 hở ở hai nhánh |
| N | **Không có test cho: hai luồng cùng lúc tạo dòng `stock_balances` chưa tồn tại** | `firstOrCreate` với `lockForUpdate` không khoá được dòng chưa có. Khoá chính sẽ chặn (nổ lỗi trùng khoá, không sai số liệu), nhưng chưa được chứng minh bằng test |
| O | **Không có test cho K4** (sổ cái ghi xong nhưng bảng tồn hỏng thì cả hai rollback) | Bất biến đúng theo cấu trúc, chưa được test ép |
| P | **Chỉ 1/3 đường khoá nhiều dòng `StockBalance` có test bảo vệ thứ tự `ingredient_id`** | `DeductStockForServedItem` và `CloseStockTake` giữ đúng thứ tự chỉ nhờ một dòng `orderBy` không được test bảo vệ |
| Q | **`has_cost = false` được ghi nhưng không ai đọc** | Khi xuất kho lúc tồn âm, hệ thống đánh dấu "không xác định được giá vốn" — nhưng không màn hình nào, không lệnh đối soát nào cảnh báo chủ quán về những dòng đó. Lãi gộp của những món ấy sẽ trông đẹp giả |
| R | **`OpenStockTake` kiểm bàn mở ngoài transaction, không khoá** | Xem mục 7 ghi chú 3 |
| S | **`RecordStockTakeCount` khoá con trước cha** | Xem mục 7 ghi chú 4 |
| T | **Chủ quán tự duyệt PIN cho chính mình khi điều chỉnh tay** | `AdjustStock` đòi cả người thực hiện lẫn người duyệt đều là `owner`. Với quán 5–15 bàn thì thường chỉ có một chủ ⇒ tự bấm, tự nhập PIN. Không chắc đó là mức kiểm soát chủ quán muốn |
| U | **`CloseStockTake` cộng tiền bằng `+=` chứ không qua `Money`** | Bắt buộc, vì `cost_delta` có dấu và `Money` chặn số âm. Nhưng CLAUDE.md mục 8 nói "mọi phép tính tiền đi qua Money, không có ngoại lệ". Cần Opus xác nhận ngoại lệ này và ghi vào CLAUDE.md |
| V | **Món đã tính tiền nhưng chưa `served_at` vào báo cáo với giá vốn 0** | Lãi gộp trông cao giả nếu báo cáo chạy giữa chừng |

### 🟢 Mức thấp

| # | Chỗ |
|---|---|
| W | Tên tham số `StockCost::giaVonXuat(int $qty, ...)` — `$qty` là **tồn hiện có**, không phải số lượng xuất. Dễ truyền nhầm |
| X | `StockCost::congAnToan` gần như là nhánh chết |
| Y | `K14` chặn ở database nhưng không có kiểm ở code ⇒ người dùng thấy lỗi khoá duy nhất thô, không phải tiếng Việt |
| Z | `ReconcileStockLedger` nạp toàn bộ `ref_id` vào bộ nhớ, không phân trang. Không sao ở quy mô này |
| AA | `SummarizeProductProfit` chạy N+1 truy vấn (một truy vấn mỗi lượt khách). Không sao ở quy mô này |
| AB | `ReceivePurchase` không kiểm phiếu rỗng — không xảy ra được, nhưng sẽ lặng lẽ chuyển `received` mà không ghi gì |
| AC | Nhận hàng bằng `whereDate` trong đối soát so theo **ngày**, làm phạm vi kiểm rộng hơn khoảng ca thật. Chỉ rộng hơn, không bỏ sót |

---

# NGUYÊN VĂN OUTPUT 6 LỆNH

Chạy trên máy dev, 10/08/2026.

---

## Lệnh 1 — `php artisan test` (lần một)

Tổng cộng 777 dòng output. Dán đầy đủ **toàn bộ khối test của Phase 3** (Inventory, Reporting, StockCost, GeneratedColumns, UnitConverter) và dòng tổng kết. Các khối test của Phase 0/1/2 (Auth, Billing, Catalog, Ordering, Printing, Staffing, Sync) đều `PASS` và được lược trong bản dán này để hồ sơ đọc được — số tổng ở cuối là số thật, không lược.

```
   PASS  Tests\Unit\Support\StockCostTest
  ✓ it ví dụ chạy tay: nhập 100@20.000, nhập 50@24.000, bán 30 ra đúng 640.000                                   0.05s
  ✓ it lamTron làm tròn nửa lên đúng công thức intdiv(2a+b, 2b)                                                  0.03s
  ✓ it trường hợp biên 1: xuất đúng bằng tồn thì total_cost về đúng 0, không đồng lẻ                             0.03s
  ✓ it trường hợp biên 2: xuất nhiều hơn tồn thì lấy hết vốn, has_cost = false                                   0.03s
  ✓ it trường hợp biên 3: xuất khi tồn đang âm thì giá vốn 0, has_cost = false                                   0.03s
  ✓ it trường hợp biên 4: tồn về 0 rồi nhập lại thì giá trung bình mới hoàn toàn, không nhớ giá cũ               0.03s
  ✓ it trường hợp biên 5: nhập khi tồn đang âm thì cộng bình thường, giá TB lệch cao tạm thời                    0.03s
  ✓ it trường hợp biên 6: kiểm kê thừa lúc qty <= 0 thì giá vốn 0, has_cost = false                              0.03s
  ✓ it trường hợp biên 6b: kiểm kê thừa lúc qty > 0 thì giữ nguyên giá trung bình                                0.03s
  ✓ it trường hợp biên 7: total_cost × n tràn số nguyên 64 bit thì ném lỗi rõ ràng                               0.03s
  ✓ it trường hợp biên 7b: giaVonXuat tràn số khi nhân total_cost với n                                          0.03s
  ✓ it lamTron chặn tử số âm                                                                                     0.03s
  ✓ it lamTron chặn mẫu số không dương                                                                           0.03s

   PASS  Tests\Feature\Database\GeneratedColumnsTest
  ✓ it order_items.line_amount luôn do database tự tính, cố ghi tay bị chặn                                      0.07s
  ✓ it table_session_tables.occupied_table_id luôn do database tự tính, cố ghi tay bị chặn                       0.05s
  ✓ it table_session_tables.occupied_table_id là NULL khi bàn đã nhả (detached)                                  0.05s
  ✓ it shifts.open_guard luôn do database tự tính, cố ghi tay bị chặn                                            0.04s
  ✓ it shifts.open_guard là NULL khi ca đã đóng                                                                  0.05s
  ✓ it purchase_items.qty_base luôn do database tự tính, cố ghi tay bị chặn                                      0.16s
  ✓ it purchase_items.line_cost luôn do database tự tính, cố ghi tay bị chặn                                     0.04s

   PASS  Tests\Feature\Inventory\AdjustStockTest
  ✓ it chủ quán điều chỉnh giảm tồn, đúng PIN, ghi sổ cái loại adjust riêng                                      0.12s
  ✓ it chủ quán điều chỉnh tăng tồn, đúng PIN                                                                    0.05s
  ✓ it lý do quá ngắn (ghi qua loa) bị chặn, không tạo dòng sổ cái nào                                           0.04s
  ✓ it PIN sai thì không điều chỉnh được                                                                         0.07s
  ✓ it thu ngân không được điều chỉnh tồn kho tay dù có PIN đúng                                                 0.04s
  ✓ it người duyệt PIN phải là chủ quán, thu ngân duyệt hộ cũng bị chặn                                          0.05s
  ✓ it số lượng điều chỉnh bằng 0 bị chặn                                                                        0.04s

   PASS  Tests\Feature\Inventory\CancelPurchaseTest
  ✓ it huỷ phiếu draft có lý do thì thành công                                                                   0.14s
  ✓ it huỷ phiếu draft không lý do thì bị chặn                                                                   0.05s
  ✓ it huỷ phiếu draft mà lý do chỉ toàn khoảng trắng thì bị chặn                                                0.05s
  ✓ it huỷ phiếu đã received thì bị chặn — hàng đã vào kho rồi                                                   0.09s
  ✓ it huỷ phiếu đã cancelled thì báo đã huỷ rồi                                                                 0.04s

   PASS  Tests\Feature\Inventory\CreatePurchaseTest
  ✓ it tạo phiếu 2 dòng thì total_cost bằng tổng line_cost, qty_base tính đúng                                   0.05s
  ✓ it phiếu có 3 nguyên liệu vẫn tạo đúng, mỗi dòng một nguyên liệu khác nhau                                   0.05s
  ✓ it đơn vị nhập không tồn tại trong ingredient_units thì bị chặn với thông báo tiếng Việt                     0.04s
  ✓ it phiếu có hai dòng cùng một nguyên liệu thì bị chặn                                                        0.05s
  ✓ it đổi factor trong ingredient_units SAU KHI tạo phiếu không làm đổi qty_base của phiếu cũ                   0.04s
  ✓ it staff tạo phiếu nhập bị chặn ở tầng quyền (policy)                                                        0.06s
  ✓ it phiếu không có dòng nào thì bị chặn                                                                       0.05s

   PASS  Tests\Feature\Inventory\DeductStockForServedItemTest
  ✓ it phục vụ 1 lẩu gà trừ đúng từng nguyên liệu theo định lượng                                                0.17s
  ✓ it phục vụ 3 lon Tiger trừ đúng 3 lon (định lượng 1:1 vẫn đi qua recipes)                                    0.07s
  ✓ it phục vụ món không trừ kho thì không tạo dòng sổ cái nào                                                   0.09s
  ✓ it gọi trừ kho hai lần cho cùng dòng món chỉ trừ đúng một lần, không ném lỗi                                 0.08s
  ✓ it huỷ món ĐÃ served thì không hoàn kho                                                                      0.11s
  ✓ it huỷ MỘT PHẦN món đã served (tách dòng) không trừ thêm và không hoàn — dòng tách kế thừa served_at nhưng…  0.09s
  ✓ it huỷ món CHƯA served thì không có gì để hoàn, tồn không đổi                                                0.07s
  ✓ it món đến muộn qua đồng bộ rồi được phục vụ thì trừ kho đúng một lần                                        0.16s
  ✓ it nguyên liệu không đủ tồn vẫn cho phục vụ, tồn cho phép về âm                                              0.07s

   PASS  Tests\Feature\Inventory\EstimateVariantCostTest
  ✓ it tính giá vốn ước tính bằng tổng số lượng nhân giá vốn nguyên liệu                                         0.07s
  ✓ it đổi giá vốn một nguyên liệu thì giá vốn ước tính của món dùng nó đổi theo ngay                            0.05s
  ✓ it món chưa có định lượng thì giá vốn ước tính là 0                                                          0.04s
  ✓ it nguyên liệu chưa từng nhập hàng thì tính giá vốn là 0, không lỗi                                          0.05s

   PASS  Tests\Feature\Inventory\FilamentNoDeleteButtonTest
  ✓ it trang Nhà cung cấp không có nút Xoá                                                                       0.12s
  ✓ it trang Nguyên liệu không có nút Xoá                                                                        0.12s

   PASS  Tests\Feature\Inventory\InventorySeederTest
  ✓ it nạp đúng 60 nguyên liệu kèm ít nhất một đơn vị quy đổi mỗi nguyên liệu                                    0.16s
  ✓ it chạy seeder hai lần không tạo trùng, không lỗi ràng buộc UNIQUE                                           0.21s
  ✓ it mỗi nguyên liệu chỉ có đúng một đơn vị nhập mặc định                                                      0.17s

   PASS  Tests\Feature\Inventory\OnlyOneStockWriterTest
  ✓ it ghi thẳng vào StockBalance từ bên ngoài RecordStockMovement bị chặn                                       0.04s
  ✓ it gọi update() trên một instance StockBalance có sẵn cũng bị chặn                                           0.03s
  ✓ it firstOrCreate từ bên ngoài cũng bị chặn                                                                   0.03s
  ✓ it ghi qua quan hệ $ingredient->stockBalance()->update() cũng bị chặn — đường KHÔNG đi qua save()            0.08s
  ✓ it increment()/decrement() qua quan hệ cũng bị chặn                                                          0.06s
  ✓ it xoá một dòng StockBalance từ bên ngoài cũng bị chặn                                                       0.03s
  ✓ it gọi RecordStockMovement thì thành công, tồn kho đổi đúng                                                  0.03s
  ✓ it choPhepGhi() luôn tắt cờ lại kể cả khi closure ném lỗi — không kẹt cờ mở mãi                              0.04s

   PASS  Tests\Feature\Inventory\ProductVariantRecipeFormTest
  ✓ it trang Biến thể món tải được, kèm cột trừ kho và giá vốn ước tính                                          0.15s
  ✓ it mở form sửa biến thể có định lượng thì hiện được form Repeater nguyên liệu, không lỗi                     0.85s

   PASS  Tests\Feature\Inventory\PurchaseFilamentTest
  ✓ it trang Nhập hàng tải được, không có nút Xoá                                                                1.07s
  ✓ it phiếu draft có nút Sửa, Nhận hàng, Huỷ                                                                    0.15s
  ✓ it phiếu đã received không có nút Sửa và nút Huỷ, chỉ còn Xem                                                0.14s
  ✓ it bấm Nhận hàng vào kho trên trang Filament gọi đúng ReceivePurchase, đổi trạng thái                        0.32s
  ✓ it bấm Huỷ phiếu trên trang Filament có form nhập lý do, không lỗi                                           0.50s
  ✓ it mở form Sửa phiếu draft có sẵn dòng thì hiện được Repeater, không lỗi                                     0.30s

   PASS  Tests\Feature\Inventory\ReceivePurchaseTest
  ✓ it nhận phiếu tăng đúng qty và total_cost trong stock_balances, sinh một dòng sổ cái mỗi dòng phiếu          0.09s
  ✓ it đúng ví dụ tài liệu: nhận 100 lon giá 2.000.000 rồi 50 lon giá 1.200.000 → qty=150, total_cost=3.200.000  0.08s
  ✓ it nhận cùng một phiếu hai lần thì lần hai bị chặn, không sinh thêm sổ cái, tồn không đổi                    0.07s
  ✓ it phiếu có 3 nguyên liệu thì sổ cái được ghi theo thứ tự ingredient_id tăng dần                             0.06s
  ✓ it nhận phiếu đã cancelled thì bị chặn                                                                       0.04s

   PASS  Tests\Feature\Inventory\RecipeGuardTest
  ✓ it món không đánh dấu trừ kho mà thêm định lượng thì bị chặn ở tầng dữ liệu                                  0.04s
  ✓ it bật lại đánh dấu trừ kho rồi thêm định lượng thì thành công                                               0.04s

   PASS  Tests\Feature\Inventory\RecipeSeederTest
  ✓ it lẩu gà có định lượng đúng như ví dụ trong docs/schema.md: 800g gà, 150g nấm kim châm                      0.48s
  ✓ it bia Tiger có định lượng đúng cho từng biến thể — lon/chai 1, thùng 24                                     0.49s
  ✓ it chạy seeder định lượng hai lần không tạo trùng, không lỗi ràng buộc UNIQUE                                0.55s
  ✓ it món không tìm được nguyên liệu khớp thì không có định lượng và không đánh dấu trừ kho                     0.47s

   PASS  Tests\Feature\Inventory\RecipeTest
  ✓ it món có định lượng đọc ra đúng danh sách nguyên liệu và số lượng                                           0.06s

   PASS  Tests\Feature\Inventory\ReconcileStockLedgerTest
  ✓ it cố tình sửa tay bảng tồn cho lệch số lượng → phát hiện đúng nguyên liệu và đúng số lệch                   0.06s
  ✓ it không lệch gì thì báo sạch                                                                                0.05s
  ✓ it cố tình sửa tay giá trị tồn cho lệch → phát hiện đúng nguyên liệu và đúng số tiền lệch                    0.04s
  ✓ it dòng món đã phục vụ nhưng thiếu sổ cái thì bị phát hiện                                                   0.08s
  ✓ it dòng tách ra khi huỷ một phần (split_from_item_id) không bị báo lệch giả dù không có sổ cái riêng         0.07s
  ✓ it dòng sổ cái mồ côi (trỏ về order_item chưa served) bị phát hiện                                           0.05s
  ✓ it ghi kết quả vào activity_log, log_name doi-soat-kho                                                       0.05s
  ✓ it ca vắt qua nửa đêm: món bưng ra sau 0 giờ vẫn được đối soát lúc đóng ca                                   0.10s
  ✓ it lệnh chạy tay stock:doi-soat báo sạch khi không lệch, báo đúng nguyên liệu khi lệch                       0.31s
  ✓ it job đối soát lỗi thì đóng ca vẫn thành công                                                               0.07s

   PASS  Tests\Feature\Inventory\RecordStockMovementTest
  ✓ it nhập hàng (purchase) cộng thẳng qty và total_cost theo tiền thật, không làm tròn                          0.05s
  ✓ it bán món (sale) trừ kho theo giá vốn bình quân gia quyền, đúng ví dụ tài liệu                              0.05s
  ✓ it hỏng vỡ (waste) trừ kho và giữ lại lý do                                                                  0.05s
  ✓ it waste không ghi lý do đủ dài thì bị database chặn (ck_stock_movements_waste_reason)                       0.04s
  ✓ it điều chỉnh tăng (adjust) dùng giá trung bình hiện tại, giá TB không đổi                                   0.04s
  ✓ it điều chỉnh thiếu người duyệt hoặc lý do ngắn thì bị database chặn (ck_stock_movements_adjust)             0.05s
  ✓ it kiểm kê thừa (stocktake) lúc tồn dương giữ nguyên giá trung bình                                          0.05s
  ✓ it kiểm kê thiếu (stocktake) trừ kho theo giá vốn bình quân                                                  0.05s
  ✓ it trả hàng nhà cung cấp (return) trừ kho theo giá trung bình, không truy giá lô gốc                         0.05s
  ✓ it purchase không ghi knownCost thì bị chặn với thông báo tiếng Việt                                         0.06s
  ✓ it ghi sổ cái xong thì bảng tồn last_movement_id trỏ đúng dòng vừa tạo                                       0.05s

   PASS  Tests\Feature\Inventory\StockCostNoMoneyLostTest
  ✓ it 1000 thao tác ngẫu nhiên không mất một đồng nào, hạt ngẫu nhiên cố định                                   2.99s

   PASS  Tests\Feature\Inventory\StockMovementImmutableTest
  ✓ it không ai xoá được dòng sổ cái nào — gọi delete() trên instance bị chặn                                    0.07s
  ✓ it không ai xoá được dòng sổ cái nào — gọi forceDelete() trên instance bị chặn                               0.05s
  ✓ it không ai xoá được dòng sổ cái nào — xoá hàng loạt qua query builder cũng bị chặn                          0.05s
  ✓ it màn hình Hao hụt không có nút Xoá                                                                         0.22s

   PASS  Tests\Feature\Inventory\StockTakeTest
  ✓ it kiểm kê thiếu 5 lon sinh đúng một dòng sổ cái điều chỉnh -5                                               0.18s
  ✓ it kiểm kê thừa sinh dòng sổ cái điều chỉnh dương                                                            0.06s
  ✓ it kiểm kê khớp không sinh dòng sổ cái nào                                                                   0.06s
  ✓ it dòng chưa đếm khi chốt phiếu thì không sinh dòng sổ cái nào                                               0.04s
  ✓ it chốt phiếu hai lần chỉ sinh điều chỉnh một lần                                                            0.06s
  ✓ it sửa số đếm sau khi phiếu đã chốt thì bị chặn                                                              0.06s
  ✓ it còn bàn đang mở thì không mở được phiếu kiểm kê                                                           0.05s
  ✓ it còn bàn đang billing (chưa thu xong tiền) cũng không mở được phiếu kiểm kê                                0.04s
  ✓ it bàn đã đóng hoặc đã huỷ thì không chặn mở phiếu kiểm kê                                                   0.05s
  ✓ it đã có phiếu kiểm kê đang mở thì không mở thêm phiếu mới                                                   0.06s
  ✓ it mở phiếu chụp đúng tồn hệ thống tại thời điểm mở, không đổi dù kho biến động sau đó                       0.04s
  ✓ it màn hình danh sách kiểm kê mở được và mở phiếu qua nút, không lỗi                                         0.39s
  ✓ it màn hình đếm hiện đúng dòng nguyên liệu và chốt phiếu qua nút không lỗi                                   1.33s
  ✓ it số đếm âm bị chặn                                                                                         0.05s

   PASS  Tests\Feature\Inventory\ToggleIngredientActiveTest
  ✓ it bật lại một nguyên liệu đã ngừng dùng                                                                     0.07s
  ✓ it ngừng dùng một nguyên liệu đang hoạt động                                                                 0.05s

   PASS  Tests\Feature\Inventory\ToggleSupplierActiveTest
  ✓ it bật lại một nhà cung cấp đã ngừng dùng                                                                    0.06s
  ✓ it ngừng dùng một nhà cung cấp đang hoạt động                                                                0.05s

   PASS  Tests\Feature\Inventory\UpdatePurchaseTest
  ✓ it sửa phiếu draft cập nhật lại dòng và tổng tiền                                                            0.09s
  ✓ it sửa phiếu đã received thì bị chặn                                                                         0.04s
  ✓ it sửa phiếu đã cancelled thì bị chặn                                                                        0.04s

   PASS  Tests\Feature\Inventory\WriteOffStockTest
  ✓ it ghi hao hụt loại VỠ/HỎNG trừ đúng số lượng, ghép loại vào lý do                                           0.06s
  ✓ it ghi hao hụt loại HẾT HẠN trừ đúng số lượng                                                                0.04s
  ✓ it ghi hao hụt loại HAO HỤT TỰ NHIÊN trừ đúng số lượng                                                       0.04s
  ✓ it ghi hao hụt loại DÙNG NỘI BỘ trừ đúng số lượng                                                            0.06s
  ✓ it bắt buộc ghi rõ lý do, không cho ghi qua loa (chuỗi rỗng)                                                 0.04s
  ✓ it số lượng hao hụt phải lớn hơn 0                                                                           0.04s
  ✓ it nhân viên phục vụ không có quyền ghi hao hụt                                                              0.04s
  ✓ it bếp không có quyền ghi hao hụt                                                                            0.04s

   PASS  Tests\Feature\Reporting\SummarizeIngredientWasteMonthlyTest
  ✓ it tổng hợp đúng tổng số lượng và giá trị hao hụt trong tháng
  ✓ it hao hụt tháng khác không lẫn vào tháng đang tổng hợp
  ✓ it chạy lại cho cùng tháng thì ghi đè, không cộng dồn
  ✓ it nguyên liệu không hao hụt gì trong tháng thì không xuất hiện trong bảng

   PASS  Tests\Feature\Reporting\SummarizeProductProfitTest
  ✓ it một lượt khách có giảm giá: lãi gộp từng dòng cộng lại bằng đúng doanh thu thật trừ tổng giá vốn
  ✓ it món không trừ kho (không có recipe) thì giá vốn bằng 0, lãi gộp bằng doanh thu
  ✓ it món đã huỷ không được tính vào lãi gộp theo ngày
  ✓ it chạy lại cho cùng một ngày thì ghi đè, không cộng dồn

   PASS  Tests\Feature\Support\UnitConverterTest
  ✓ it quy đổi 5 thùng bia (24 lon/thùng) ra đúng 120 lon
  ✓ it quy đổi 2,5 kg gà ra đúng 2500 gam
  ✓ it quy đổi thùng thẳng sang ml khớp với quy đổi lon rồi nhân lại — không bắc cầu nhưng dữ liệu nhất quán
  ✓ it không có đường quy đổi thì ném lỗi tiếng Việt, không trả về 0
  ✓ it quy đổi ra số lẻ thì làm tròn đến số nguyên gần nhất, không cắt bỏ phần lẻ
  ✓ it quy đổi thẳng bằng đơn vị gốc thì factor là 1, không cần dòng ingredient_units
  ✓ it số lượng âm thì ném lỗi ngay, không quy đổi

  [... các khối PASS của Auth, Billing, Catalog, Ordering, Printing, Reporting (BaoCaoChuQuan,
   GetOwnerDashboard, SummarizeDailyReport), Staffing, Sync — tất cả đều xanh ...]

  Tests:    587 passed (3876 assertions)
  Duration: 72.46s
```

---

## Lệnh 2 — `php artisan test` (lần hai, để so số test có ổn định không)

```
  ✓ it gửi lại gói mà lần đầu có thao tác conflict — vẫn ra conflict với ĐÚNG conflict_id cũ, không tạo bản ghi… 0.10s

  Tests:    587 passed (3876 assertions)
  Duration: 117.81s
```

**Kết luận: SỐ TEST ỔN ĐỊNH.** Hai lần chạy đều **587 test / 3.876 khẳng định**, không có test đỏ ngẫu nhiên, không có test bị bỏ qua. Thời gian khác nhau (72s và 118s) chỉ vì tải máy, không phải khác số test.

---

## Lệnh 3 — `php artisan pos:demo`

```
MỞ CA
   Ca CA-20260810-05 mở bởi Thu ngân diễn tập, tiền lẻ đầu ca 500.000 đ

MỞ BÀN
   Lượt khách PH-20260810-0008 mở bởi Phục vụ diễn tập tại bàn DEMO-1 (ghép thêm bàn DEMO-2), 6 khách

GỌI MÓN
   [bếp] 3 x Gà nướng diễn tập — 360.000 đ (phiếu 17105e5d-58bc-481e-abd0-4ed7386410e2)
   [quầy] 4 x Bia diễn tập — 100.000 đ (phiếu c6e37d1f-e6d1-4cad-9b96-348e85ee37f3)
   Tạm tính: 460.000 đ

GỬI BẾP
   Đã gửi phiếu 17105e5d-58bc-481e-abd0-4ed7386410e2 (kitchen) xuống nơi làm, trạng thái: sent
   Đã gửi phiếu c6e37d1f-e6d1-4cad-9b96-348e85ee37f3 (bar) xuống nơi làm, trạng thái: sent

BẾP BÁO XONG
   Phiếu 17105e5d-58bc-481e-abd0-4ed7386410e2 (kitchen) — Gà nướng diễn tập đã xong, trạng thái phiếu: served
   Phiếu c6e37d1f-e6d1-4cad-9b96-348e85ee37f3 (bar) — Bia diễn tập đã xong, trạng thái phiếu: served

TÁCH BÀN
   Tạm tính trước khi tách: 460.000 đ
   Tách bàn DEMO-2 và món Bia diễn tập (đã gửi bếp/quầy) sang lượt khách mới
   Sau khi tách — lượt cũ PH-20260810-0008: 360.000 đ, lượt mới PH-20260810-0009: 100.000 đ
   Tổng hai bên: 460.000 đ (phải bằng tạm tính trước khi tách)

HỦY MÓN
   Tạm tính trước khi huỷ: 360.000 đ
   Món Gà nướng diễn tập đang có 3 phần, đã phục vụ — huỷ bớt 1 phần, cần PIN duyệt của Thu ngân diễn tập
   Tách thành 2 dòng: dòng gốc còn 2 phần (giữ nguyên), dòng mới huỷ 1 phần (id 13, tách từ id 11)
   Tạm tính sau khi huỷ: 240.000 đ

THU TIỀN
   Tổng phải thu: 240.000 đ
   Khách đưa: 290.000 đ
   Thối lại: 50.000 đ
   Trạng thái lượt khách sau khi thu: closed

ĐÓNG CA
   Tiền mặt lẽ ra phải có: 690.000 đ
   Đếm thực tế trong két: 670.000 đ
   Chênh lệch: Thiếu 20.000 đ
   Trạng thái ca: closed

✅ TOÀN BỘ LƯỢT BÁN CHẠY ĐÚNG
Đã dọn sạch toàn bộ dữ liệu diễn tập (rollback, không có gì được ghi thật vào database).
```

⚠️ **Lưu ý:** `pos:demo` chạy **không có tham số** thì diễn tập **luồng bán hàng của Phase 1/2** và **không đi qua bước trừ kho**. Vòng trừ kho là một mốc riêng, phải gọi `pos:demo --den=tru-kho` (đúng như nghiệm thu Bước 5 trong `docs/PHASE.md` ghi). Dán thêm output mốc đó dưới đây vì nó là bằng chứng nghiệm thu của Phase 3:

```
$ php artisan pos:demo --den=tru-kho

MỞ CA
   Ca CA-20260810-06 mở bởi Thu ngân diễn tập, tiền lẻ đầu ca 500.000 đ

TRỪ KHO
   Gọi 2 phần Lẩu gà diễn tập
   Tồn TRƯỚC khi phục vụ — gà: 5000 g, sả: 1000 g
   Bếp báo xong — trừ kho tự động trong cùng giao dịch với served_at
   Tồn SAU khi phục vụ — gà: 4400 g (giảm 600 g), sả: 940 g (giảm 60 g)
   Gọi lại trừ kho lần hai cho cùng dòng món — tồn gà vẫn 4400 g (không trừ thêm)

✅ TRỪ KHO CHẠY ĐÚNG
Đã dọn sạch toàn bộ dữ liệu diễn tập (rollback, không có gì được ghi thật vào database).
```

Kiểm chứng bằng tay: định lượng 1 phần lẩu gà = 300 g gà + 30 g sả. Gọi 2 phần ⇒ trừ 600 g gà và 60 g sả. Đúng.

---

## Lệnh 4 — `php artisan stock:doi-soat`

```
ĐỐI SOÁT SỔ CÁI KHO
Đối chiếu dòng món phục vụ từ 2026-08-10 đến 2026-08-10 (mục 1, 2 luôn kiểm toàn bộ lịch sử).

1. Số lượng: sổ cái so với bảng tồn
   ✅ Khớp tuyệt đối với mọi nguyên liệu.

2. Giá trị: sổ cái so với bảng tồn
   ✅ Khớp tuyệt đối với mọi nguyên liệu.

3. Dòng món đã phục vụ ↔ dòng sổ cái
   ✅ Mọi dòng món đã phục vụ đều có sổ cái tương ứng, không có dòng sổ cái mồ côi.

✅ SỔ CÁI KHO SẠCH — KHÔNG LỆCH.
```

Mã thoát: `0` (SUCCESS). Khi có lệch, lệnh trả `1` (FAILURE) để CI/cron bắt được.

---

## Lệnh 5 — `php artisan tinker --execute="echo config('database.default');"`

```
mariadb
```

🔴 **Đây là kết quả đáng chú ý nhất trong 6 lệnh.** `CLAUDE.md` mục 2 chốt **MySQL 8.4**, `.env.example` ghi `DB_CONNECTION=mysql` cổng `3307`. Nhưng `.env` thật trên máy dev đang là `DB_CONNECTION=mariadb`, cổng `3306`. Kiểm tra thêm phiên bản server:

```
$ php artisan tinker --execute="echo DB::selectOne('select version() v')->v;"
10.4.32-MariaDB
```

Nghĩa là **toàn bộ 587 test xanh, và mọi kết luận về "database giữ bất biến" trong mục 4, đều được xác nhận trên MariaDB 10.4.32 chứ không phải MySQL 8.4.11 như tài liệu chốt.** Xem mục 8.2 điểm A.

---

## Lệnh 6 — `php artisan tinker --execute="$c=new App\Support\StockCost; $r=$c->giaVonXuat(150,3200000,30); echo 'Gia von 30 lon: '.number_format($r['cost']);"`

```
Gia von 30 lon: 640,000
```

*(Chạy trong shell POSIX phải dùng nháy đơn cho tham số `--execute` để `$c`, `$r` không bị shell nuốt mất; nội dung câu lệnh giữ nguyên.)*

**Kiểm chứng bằng tay:** tồn 150 lon trị giá 3.200.000 đ (100 lon @ 20.000 + 50 lon @ 24.000). Xuất 30 lon.
`giaVonXuat(qty=150 tồn, totalCost=3.200.000, n=30 xuất)` → nhánh `$qty > $n` → `lamTron(3.200.000 × 30, 150)` = `lamTron(96.000.000, 150)` = `intdiv(2×96.000.000 + 150, 300)` = `intdiv(192.000.150, 300)` = **640.000**.

Đúng bằng `30 × (3.200.000 ÷ 150)` = `30 × 21.333,33` = 640.000 đ. Khớp đúng ví dụ trong `docs/thiet-ke-gia-von.md` và test `StockCostTest` — *"ví dụ chạy tay: nhập 100@20.000, nhập 50@24.000, bán 30 ra đúng 640.000"*.

---

# ĐÍNH CHÍNH — 11/08

> Mục này thêm vào sau khi báo cáo đã nộp. **Phần trên giữ nguyên, không sửa một chữ** — báo cáo là hồ sơ đóng băng, xoá dấu vết của việc đã quan sát thấy gì là mất luôn khả năng đối chiếu về sau. Ba chỗ dưới đây nêu nội dung đúng.

## Dòng 356 — mục 8.2 điểm A (xếp 🔴 Mức cao)

**Đã ghi:** việc `config('database.default')` trả về `mariadb` và server là MariaDB 10.4.32 là một phát hiện đáng lo, "chỗ tôi không chắc nhất trong toàn Phase 3", cần quyết đổi máy dev về MySQL 8.4 hay đổi tài liệu.

**Nội dung đúng:** MariaDB **là môi trường đã chốt của dự án**, không phải sai lệch. Schema được thiết kế trên MySQL 8.4.11 ngày 30/07 (đó là lịch sử thiết kế), sau đó chuyển sang MariaDB và **đã kiểm chứng lại ngày 31/07 bằng 4 phép thử, cả 4 đạt** — phụ lục kiểm chứng nằm ngay trong `docs/schema.md`, mà báo cáo không đối chiếu tới. `docs/schema.md` dòng 7-11 cũng đã ghi rõ việc đổi bảng mã sang `utf8mb4_unicode_ci` và khác biệt #1906 về cách báo lỗi. Nghĩa là:

- Cả ba lo ngại (i), (ii), (iii) trong điểm A **đã được kiểm chứng và ghi lại từ 31/07**, không phải vùng chưa biết.
- Riêng ý "(i) MariaDB im lặng dùng collation khác" là **sai**: `.env` và `phpunit.xml` đều ghi rõ `DB_COLLATION=utf8mb4_unicode_ci`, đây là lựa chọn có khai báo, không phải rơi về mặc định.
- Mức 🔴 đặt cho điểm này là **quá nặng**.

**Cái thật sự sai** — và báo cáo đã đúng khi chỉ ra, chỉ là quy sai địa chỉ: `CLAUDE.md`, `.env.example` và `.github/workflows/ci.yml` vẫn ghi `mysql` / cổng 3307 / user `quanpos`, tức **tài liệu lệch khỏi thực tế**, chứ không phải thực tế lệch khỏi tài liệu. Chuyện này đã tái phát bốn lần qua ba phase. Đã sửa dứt điểm ngày 11/08, và từ nay có `tests/Feature/Support/DatabaseDriverTest.php` gác — ai đổi driver, đổi bảng mã hay đổi engine là test đỏ ngay.

## Dòng 743 — Lệnh 5, "kết quả đáng chú ý nhất trong 6 lệnh"

**Đã ghi:** `config('database.default')` ra `mariadb` trong khi tài liệu chốt MySQL 8.4 → coi là bất ngờ.

**Nội dung đúng:** kết quả `mariadb` là **đúng như mong đợi**, không phải bất ngờ. Chỗ đáng chú ý là `.env.example` còn ghi `mysql` cổng 3307 — một file mẫu mà không ai chép được ra thành `.env` chạy được.

## Dòng 750 — kết luận về 587 test

**Đã ghi:** *"toàn bộ 587 test xanh... đều được xác nhận trên MariaDB 10.4.32 chứ không phải MySQL 8.4.11 như tài liệu chốt"* — hàm ý các test chưa được xác nhận trên nền tảng đúng.

**Nội dung đúng:** câu này **đảo ngược quan hệ**. Test chạy trên MariaDB là **đúng nền tảng**, vì máy dev và máy quán đều chạy MariaDB. Thứ chạy sai nền tảng là **CI**: `.github/workflows/ci.yml` dùng service container `mysql:8.4`, tức máy chủ kiểm thử tự động chạy trên engine mà không nơi nào dùng thật. Đó mới là chỗ "màu xanh không chứng minh được gì". Đã đổi CI sang `mariadb:10.4` ngày 11/08.

*(Bổ sung không thuộc ba dòng trên, ghi ở đây để không mất: `ci.yml` trước 11/08 tạo database `quan_pos` trong khi `phpunit.xml` trỏ `quan_pos_test`, nên nhiều khả năng workflow chưa từng chạy xanh lần nào — con số "587 test xanh trên MySQL 8" có thể chưa bao giờ tồn tại trên CI. Đã sửa tên database trong cùng lượt.)*
