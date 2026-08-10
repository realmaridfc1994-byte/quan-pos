# THUẬT TOÁN GIÁ VỐN BÌNH QUÂN GIA QUYỀN

> Lưu tại `docs/thiet-ke-gia-von.md`
> Thiết kế: Opus 5 · Ngày 05/08/2026 · Đã duyệt năm quyết định gốc
> Dùng cho Phase 3 Bước 4. Bước 5, 6, 7 gọi lại cùng một Action.

---

## 1. Nguyên tắc gốc — một cửa duy nhất

Mọi thay đổi kho đi qua **đúng một Action**: `RecordStockMovement`.

Nhập hàng, bán món, hỏng vỡ, điều chỉnh kiểm kê, trả hàng nhà cung cấp — tất cả. Không Action nào khác được chạm vào `stock_balances`.

Một cửa nghĩa là **một chỗ có thể sai, một chỗ phải test**. Đây là ràng buộc quan trọng nhất của tài liệu này.

> **Luật cho người viết code:** thấy mình viết `stock_balances`, `->update()` hay `->increment()` ở bất kỳ file nào ngoài `RecordStockMovement`, dừng lại và báo.

---

## 2. Công thức

### 2.1. Nhập kho — không có làm tròn

```
qty        += n
total_cost += c
```

Chính xác tuyệt đối, không có phép chia nào.

`n` là số lượng theo đơn vị gốc, `c` là tổng tiền của lô nhập tính bằng đồng.

### 2.2. Xuất kho — chỗ duy nhất có làm tròn

```
giá vốn xuất = lamTron(total_cost × n, qty)
total_cost  -= giá vốn xuất
qty         -= n
```

**Điểm mấu chốt:** con số ghi vào sổ cái **chính là** con số trừ khỏi `total_cost`. Không có phép tính lại độc lập nào ở bất kỳ đâu.

Nhờ đó đẳng thức sau đúng **theo định nghĩa**, không phải theo may mắn:

> `total_cost` hiện tại = tổng mọi lần cộng vào − tổng mọi lần trừ ra

### 2.3. Hàm làm tròn — nửa lên, bằng số nguyên

```
lamTron(a, b) = intdiv(2 × a + b, 2 × b)     với a ≥ 0, b > 0
```

Không dùng phép chia thập phân ở bất kỳ đâu. Cùng lý do với tiền.

**Bắt buộc kiểm tràn số trước khi nhân:** nếu `total_cost × n` vượt giới hạn số nguyên 64 bit, ném lỗi rõ ràng thay vì âm thầm cho kết quả sai. Ở quy mô quán điều này gần như không xảy ra, nhưng tràn số nguyên không báo gì cả — phải chặn.

---

## 3. Ví dụ chạy tay

| Bước | qty | total_cost | Giá TB | Ghi sổ cái |
|---|---|---|---|---|
| Nhập 100 lon @ 20.000 | 100 | 2.000.000 | 20.000 | `+100`, `+2.000.000` |
| Nhập 50 lon @ 24.000 | 150 | 3.200.000 | 21.333,33 | `+50`, `+1.200.000` |
| **Bán 30 lon** | 120 | 2.560.000 | 21.333,33 | `−30`, `−640.000` |

Giá vốn 30 lon = `lamTron(3.200.000 × 30, 150)` = `lamTron(96.000.000, 150)` = **640.000 đồng**.

**Giá trung bình sau khi bán không đổi.** Đó là dấu hiệu thuật toán đúng — bán hàng không làm thay đổi giá vốn của số còn lại.

Kiểm chứng: `2.000.000 + 1.200.000 − 640.000 = 2.560.000` ✅

---

## 4. Bảng xử lý mọi tình huống xuất kho

| Tình huống | Giá vốn xuất | `has_cost` | Kết quả |
|---|---|---|---|
| `qty > n` — bình thường | `lamTron(total_cost × n, qty)` | 1 | Giá TB không đổi |
| `qty = n` — lấy hết sạch | `total_cost` (không làm tròn) | 1 | `total_cost` về **đúng 0** |
| `0 < qty < n` — lấy quá | `total_cost` (hết vốn) | **0** | `qty` xuống âm, `total_cost` = 0 |
| `qty ≤ 0` — đang âm | `0` | **0** | `qty` âm hơn, `total_cost` giữ 0 |

Ba dòng đầu đều giữ bất biến **K9** (`qty = 0` thì `total_cost = 0`).

Dòng `qty = n` phải xử riêng, **không** dùng công thức làm tròn — dùng thẳng `total_cost` để bảo đảm về đúng 0, không sót đồng lẻ.

### Vì sao `has_cost = 0` quan trọng

Những dòng này là món đã bán mà hệ thống **không biết giá vốn**. Báo cáo lãi gộp ở Bước 8 phải nêu rõ:

> *"Có 12 món chưa tính được giá vốn vì kho đang âm. Lãi gộp dưới đây tính thiếu phần đó."*

Không giấu đi. Một con số lãi gộp trông đẹp mà thiếu giá vốn là con số nói dối.

---

## 5. Từng loại nghiệp vụ

| Loại | Hướng | Giá vốn |
|---|---|---|
| `purchase` — nhập hàng | Vào | Tiền thật trả nhà cung cấp |
| `sale` — bán món | Ra | Theo bảng mục 4 |
| `waste` — hỏng vỡ | Ra | Theo bảng mục 4 |
| `stocktake` thiếu | Ra | Theo bảng mục 4 |
| `stocktake` thừa | **Vào** | `lamTron(total_cost × n, qty)` — xem 5.1 |
| `return` — trả nhà cung cấp | Ra | Theo bảng mục 4 |
| `adjust` — điều chỉnh tay | Cả hai | Theo hướng, cùng quy tắc |

### 5.1. Kiểm kê thừa — dùng giá trung bình hiện tại

Đếm được nhiều hơn hệ thống nghĩ. Hàng đó vốn đã có trong kho, chỉ bị ghi sót.

Nhập thêm với giá trung bình hiện tại → **giá trung bình không đổi** sau kiểm kê. Đúng ý nghĩa "chỉ sửa số lượng, không sửa giá".

Trường hợp biên: `qty ≤ 0` mà đếm được hàng. Không có giá trung bình để dùng → ghi giá vốn `0`, `has_cost = 0`, kèm cảnh báo cho chủ quán nhập giá tay nếu muốn.

### 5.2. Trả hàng nhà cung cấp — cũng theo giá trung bình

Không truy về giá lô gốc. Lý do: trả hàng ở quán nhậu là chuyện hiếm và thường xảy ra ngay sau khi nhập, nên giá trung bình gần bằng giá lô. Truy lô gốc cần chọn phiếu nhập, xử lý trả một phần, và tạo **đường xử lý thứ hai** — trái nguyên tắc một cửa.

Chênh lệch giữa giá vốn ghi giảm và tiền nhà cung cấp hoàn lại là lãi hoặc lỗ, ghi ở báo cáo. Không phải chuyện của kho.

---

## 6. Bảy trường hợp biên

| # | Tình huống | Xử lý | Bất biến liên quan |
|---|---|---|---|
| 1 | Xuất đúng bằng tồn | `total_cost` về **đúng 0**, không đồng lẻ | K9 |
| 2 | Xuất nhiều hơn tồn | Lấy hết vốn, `qty` âm, `total_cost` = 0, `has_cost` = 0 | K9 |
| 3 | Xuất khi tồn đang âm | Giá vốn 0, `has_cost` = 0 | — |
| 4 | Tồn về 0 rồi nhập lại | Giá TB mới hoàn toàn, **không nhớ giá cũ** | — |
| 5 | Nhập khi tồn đang âm | Cộng bình thường; giá TB lệch cao tạm thời, kiểm kê dọn | — |
| 6 | Kiểm kê thừa lúc `qty ≤ 0` | Giá vốn 0, `has_cost` = 0, cảnh báo | — |
| 7 | `total_cost × n` tràn số nguyên 64 bit | **Kiểm trước khi nhân**, ném lỗi rõ ràng | — |

Trường hợp 4 là câu trả lời cho câu hỏi *"tồn về 0 rồi nhập lại thì giá vốn tính thế nào"*: giá vốn mới hoàn toàn. Vì `total_cost` đã về 0, lô nhập mới quyết định giá trung bình một mình.

---

## 7. Mã giả `RecordStockMovement`

```
handle(RecordStockMovementData $data): StockMovement

  DB::transaction:

    # 1. Khoá dòng tồn. Nhiều nguyên liệu thì khoá theo ingredient_id TĂNG DẦN.
    balance = StockBalance::lockForUpdate()
                ->firstOrCreate(ingredient_id, [qty => 0, total_cost => 0])

    # 2. Tính giá vốn theo hướng
    if data.qtyDelta > 0:                       # VÀO KHO
        if data.type == purchase or return_in:
            costDelta = data.knownCost          # tiền thật, do người nhập gõ
            hasCost   = true
        else:                                   # kiểm kê thừa, điều chỉnh tăng
            if balance.qty > 0:
                costDelta = lamTron(balance.total_cost * data.qtyDelta, balance.qty)
                hasCost   = true
            else:
                costDelta = 0
                hasCost   = false

    else:                                       # RA KHO
        n = abs(data.qtyDelta)
        if balance.qty > n:
            costDelta = -lamTron(balance.total_cost * n, balance.qty)
            hasCost   = true
        elseif balance.qty == n:
            costDelta = -balance.total_cost     # KHÔNG làm tròn, về đúng 0
            hasCost   = true
        elseif balance.qty > 0:
            costDelta = -balance.total_cost     # hết vốn
            hasCost   = false
        else:
            costDelta = 0                       # tồn đang âm
            hasCost   = false

    # 3. Tính tồn mới
    qtyAfter  = balance.qty + data.qtyDelta
    costAfter = balance.total_cost + costDelta

    # 4. Van an toàn — K9. Nếu vi phạm là code sai, không phải dữ liệu sai.
    if qtyAfter == 0 and costAfter != 0:
        throw LỗiNộiBộ("Tồn về 0 mà trị giá còn " + costAfter + " đồng")
    if qtyAfter > 0 and costAfter < 0:
        throw LỗiNộiBộ("Tồn dương mà trị giá âm")

    # 5. Ghi sổ cái. Khoá duy nhất (ref_type, ref_id, ingredient_id) chặn
    #    ghi hai lần — đây là K5 và K7.
    movement = StockMovement::create([...qtyDelta, costDelta, qtyAfter,
                                      costAfter, hasCost, ref..., ...])

    # 6. Cập nhật tồn — LUÔN đi cặp với bước 5, không bao giờ đứng một mình
    balance.update([qty => qtyAfter, total_cost => costAfter,
                    last_movement_id => movement.id])

    return movement
```

**Bước 4 là van an toàn, không phải logic nghiệp vụ.** Nếu nó nổ, đó là lỗi lập trình — thông báo phải nói rõ điều đó để không ai đi tìm nguyên nhân ở dữ liệu.

**Bước 5 trước bước 6, không bao giờ ngược lại.** Sổ cái là sự thật, bảng tồn là bản tóm tắt.

---

## 8. Bộ test — ba tầng

### Tầng 1 — ví dụ chạy tay

Đúng ví dụ mục 3: nhập 100@20.000, nhập 50@24.000, bán 30 → giá vốn **640.000**, `total_cost` còn **2.560.000**, giá TB vẫn **21.333**.

### Tầng 2 — bảy trường hợp biên

Mỗi dòng trong bảng mục 6 một test riêng.

### Tầng 3 — một nghìn thao tác ngẫu nhiên

Đây là test quan trọng nhất, và là bằng chứng duy nhất cho ràng buộc "không mất đồng nào".

- Bắt đầu từ kho rỗng
- 1000 thao tác ngẫu nhiên: nhập, xuất, kiểm kê thừa, kiểm kê thiếu, trả hàng
- Số lượng và giá ngẫu nhiên; **cố tình** đẩy tồn về đúng 0 và xuống âm nhiều lần
- **Sau mỗi thao tác**, khẳng định bốn điều:
  1. `qty` trong bảng tồn = cộng hết `qty_delta` trong sổ cái
  2. `total_cost` trong bảng tồn = cộng hết `cost_delta` trong sổ cái
  3. `qty = 0` thì `total_cost = 0`
  4. `qty > 0` thì `total_cost ≥ 0`
- **Cuối cùng**: tổng tiền đã nhập − tổng giá vốn đã xuất = `total_cost` hiện tại, **đúng đến từng đồng**

**Hạt ngẫu nhiên cố định** để test không đỏ ngẫu nhiên — đúng luật đã chốt ở Phase 2.

---

## 9. Prompt dán cho Sonnet

```
=== KHOÁ PHẠM VI ===
Đọc docs/PHASE.md trước. Chỉ làm việc thuộc bước đang mở.
Trước khi gõ code: in "PHẠM VI TÔI HIỂU" gồm danh sách file sẽ tạo, file sẽ
sửa, lệnh sẽ chạy — rồi DỪNG chờ tôi gõ DUYỆT.
Việc nào thấy cần nhưng thuộc bước sau: ghi một dòng vào docs/viec-ton.md,
KHÔNG làm, KHÔNG hỏi xin làm luôn.
Xong việc: in "BÁO CÁO PHẠM VI" liệt kê đúng file đã đụng vào.
Làm thừa bị tính là hỏng việc, kể cả khi code đúng.
====================

Đọc docs/thiet-ke-gia-von.md và làm ĐÚNG theo tài liệu đó. Không tự sáng tạo
thêm. Thấy tài liệu thiếu chỗ nào thì DỪNG và hỏi, đừng tự quyết.

VIỆC CẦN LÀM — đúng 6 mục:

1. app/Support/StockCost.php — lớp tính toán thuần, KHÔNG đụng database:
   - lamTron(int $a, int $b): int — làm tròn nửa lên bằng số nguyên,
     công thức intdiv(2*a + b, 2*b). Kiểm tràn số 64 bit TRƯỚC khi nhân,
     tràn thì ném exception riêng với thông báo tiếng Việt rõ ràng.
   - giaVonXuat(int $qty, int $totalCost, int $n): array{cost, hasCost}
     theo đúng bảng mục 4 của tài liệu, đủ cả bốn nhánh.
   - giaVonNhapTheoTrungBinh(int $qty, int $totalCost, int $n): array
     cho kiểm kê thừa, theo mục 5.1.
   Lớp này không có phụ thuộc nào, test được bằng unit test thuần.

2. app/Domain/Inventory/Actions/RecordStockMovement.php — theo đúng mã giả
   mục 7 của tài liệu:
   - Khoá dòng tồn bằng lockForUpdate, firstOrCreate nếu chưa có
   - Nhiều nguyên liệu thì người GỌI phải khoá theo ingredient_id tăng dần;
     ghi comment nhắc rõ điều này
   - Van an toàn bước 4: ném exception nội bộ, thông báo phải nói rõ "đây là
     lỗi lập trình, không phải lỗi dữ liệu"
   - Ghi sổ cái TRƯỚC, cập nhật tồn SAU
   - Trả về StockMovement vừa tạo

3. DTO RecordStockMovementData với đủ trường theo schema stock_movements.

4. QUAN TRỌNG — đây là Action DUY NHẤT được chạm vào stock_balances.
   Thêm một test tự chặn tái phát:
   tests/Feature/Inventory/OnlyOneStockWriterTest.php
   - Quét toàn bộ thư mục app/ tìm mọi file có chuỗi 'stock_balances' hoặc
     'StockBalance'
   - Khẳng định CHỈ RecordStockMovement.php và Model StockBalance.php được
     phép ghi (update/create/increment/decrement)
   - File khác chỉ được ĐỌC
   Test này đỏ nếu sau này ai đó thêm đường ghi thứ hai.

5. TEST BA TẦNG theo mục 8 của tài liệu:
   a. tests/Unit/Support/StockCostTest.php — ví dụ chạy tay mục 3 và bảy
      trường hợp biên mục 6, mỗi cái một test
   b. tests/Feature/Inventory/RecordStockMovementTest.php — từng loại
      nghiệp vụ ở mục 5, khẳng định sổ cái và bảng tồn khớp nhau
   c. tests/Feature/Inventory/StockCostNoMoneyLostTest.php — 1000 thao tác
      ngẫu nhiên, HẠT NGẪU NHIÊN CỐ ĐỊNH, kiểm 4 điều sau MỖI thao tác và
      đẳng thức cuối cùng đúng đến từng đồng

6. Cập nhật CLAUDE.md thêm hai luật vào mục quy ước về tiền:
   - "Chỉ RecordStockMovement được ghi vào stock_balances. Mọi Action khác
     gọi nó, không tự ghi."
   - "Không bao giờ UPDATE stock_balances SET qty = qty - n đứng một mình.
     Mọi thay đổi tồn phải đi cặp — ghi sổ cái và cập nhật tồn, cùng một
     giao dịch, sau khi đã khoá dòng tồn."

KHÔNG làm nhập hàng, trừ kho khi bán, kiểm kê ở lượt này — đó là Bước 4 phần
sau, Bước 5, Bước 7. Lượt này CHỈ làm lớp tính toán và Action một cửa.
KHÔNG dùng float, KHÔNG dùng DECIMAL, KHÔNG dùng bcmath ở bất kỳ đâu.
```

---

## 10. Điều bạn nên biết về con số này

Thuật toán **luôn khớp đến từng đồng**, nhưng nó không phải "giá vốn chính xác cho từng lon bia". Bình quân gia quyền là một cách **phân bổ**, không phải một sự thật.

Bán 30 lon sau khi nhập hai lô giá khác nhau — hệ thống báo 640.000. Trong đời thật, có thể 30 lon đó lấy toàn từ lô cũ giá 20.000, tức 600.000. Chênh 40.000.

Điều đó **không sai**. Phần chênh nằm lại trong kho và hiện ra ở những lần bán sau. Tính trên cả tháng thì tổng giá vốn đúng tuyệt đối. Chỉ tính trên một bill thì đó là con số phân bổ.

Muốn chính xác từng lon phải theo lô hàng, mà ta đã cắt khỏi phạm vi. Đó là đánh đổi đúng ở quy mô này — nhưng bạn nên biết để không ngạc nhiên khi nhìn báo cáo lãi gộp của một bàn cụ thể.

---

## 11. Nghiệm thu

Sau khi Sonnet xong:

```bash
php artisan test --filter=StockCost
php artisan test
```

Rồi kiểm bằng mắt đúng ví dụ của bạn:

```bash
php artisan tinker --execute="$c = new App\Support\StockCost; $r = $c->giaVonXuat(150, 3200000, 30); echo 'Gia von 30 lon: '.number_format($r['cost']).' d | Con lai: '.number_format(3200000 - $r['cost']).' d';"
```

Phải ra: `Gia von 30 lon: 640.000 d | Con lai: 2.560.000 d`

Ra khác là thuật toán chưa đúng — dừng lại báo tôi.
