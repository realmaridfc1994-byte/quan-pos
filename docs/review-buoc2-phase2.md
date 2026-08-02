# REVIEW BƯỚC 2 PHASE 2 — ĐỊNH DANH DO MÁY POS SINH

> Chỉ báo cáo, không sửa file nào ngoài file này. Nội dung dán từ code thật
> tại thời điểm review (02/08/2026), sau khi đã chạy `migrate:fresh --seed`.

---

## 1. TOÀN BỘ MIGRATION MỚI (up + down)

### `database/migrations/2026_08_02_000001_add_uuid_to_table_sessions_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 2 Phase 2 — định danh do máy POS sinh cho table_sessions.
 *
 * Nullable tạm thời để dữ liệu cũ không vỡ. Dữ liệu cũ backfill bằng lệnh
 * `pos:backfill-uuid`, không backfill trong migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->after('id');
            $table->unique('uuid', 'uq_table_sessions_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropUnique('uq_table_sessions_uuid');
            $table->dropColumn('uuid');
        });
    }
};
```

### `database/migrations/2026_08_02_000002_add_uuid_to_order_items_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 2 Phase 2 — định danh do máy POS sinh cho order_items.
 * Nullable tạm thời; backfill bằng lệnh `pos:backfill-uuid`, không backfill trong migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->after('id');
            $table->unique('uuid', 'uq_order_items_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique('uq_order_items_uuid');
            $table->dropColumn('uuid');
        });
    }
};
```

### `database/migrations/2026_08_02_000003_add_uuid_to_order_item_options_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 2 Phase 2 — định danh do máy POS sinh cho order_item_options.
 * Nullable tạm thời; backfill bằng lệnh `pos:backfill-uuid`, không backfill trong migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_options', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->after('id');
            $table->unique('uuid', 'uq_order_item_options_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_options', function (Blueprint $table) {
            $table->dropUnique('uq_order_item_options_uuid');
            $table->dropColumn('uuid');
        });
    }
};
```

---

## 2. LỆNH ARTISAN BACKFILL UUID (nội dung đầy đủ)

### `app/Console/Commands/BackfillClientUuids.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use App\Domain\Ordering\Models\TableSession;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gán uuid cho dữ liệu CŨ đã tạo trước khi Phase 2 Bước 2 thêm cột `uuid` vào
 * table_sessions, order_items, order_item_options.
 *
 * Chỉ chạy MỘT LẦN sau khi migrate — không nằm trong migration vì backfill
 * dữ liệu không phải việc của migration (migration chỉ đổi cấu trúc bảng).
 *
 * Dữ liệu cũ không có uuid "thật" do máy POS sinh — uuid gán ở đây chỉ để
 * thoả ràng buộc UNIQUE cho các dòng lịch sử, không mang ý nghĩa chống trùng
 * với máy POS (dữ liệu cũ vốn đã ghi xong, không còn gửi lại được nữa).
 */
final class BackfillClientUuids extends Command
{
    protected $signature = 'pos:backfill-uuid';

    protected $description = 'Gán uuid cho các dòng cũ chưa có ở table_sessions, order_items, order_item_options';

    public function handle(): int
    {
        $this->backfillMotBang(TableSession::query()->whereNull('uuid'), 'table_sessions');
        $this->backfillMotBang(OrderItem::query()->whereNull('uuid'), 'order_items');
        $this->backfillMotBang(OrderItemOption::query()->whereNull('uuid'), 'order_item_options');

        return self::SUCCESS;
    }

    /** @param Builder<Model> $query */
    private function backfillMotBang(Builder $query, string $tenBang): void
    {
        $soDong = 0;

        $query->chunkById(500, function ($dongDuLieu) use (&$soDong): void {
            foreach ($dongDuLieu as $mot) {
                $mot->update(['uuid' => (string) Str::uuid()]);
                $soDong++;
            }
        });

        $this->line("{$tenBang}: đã gán uuid cho {$soDong} dòng.");
    }
}
```

**Chưa chạy lệnh này trên `quan_pos` (dev thật)** — chỉ kiểm chứng bằng test (`tests/Feature/Console/BackfillClientUuidsTest.php`, mục 5). Vì vừa `migrate:fresh --seed` nên DB dev hiện không có dòng nào thiếu uuid để backfill thật.

---

## 3. HAI HÀM SINH MÃ SAU KHI SỬA

### `OpenTableSession::sinhMaLuotKhach()` — trong `app/Domain/Ordering/Actions/OpenTableSession.php`

```php
    /**
     * Dùng `id` tự tăng của chính lượt khách vừa tạo — không dùng `count() + 1`.
     * Hai người mở bàn cùng một giây, mỗi người vẫn nhận đúng một `id` khác
     * nhau do MySQL tự đảm bảo, không bao giờ ra cùng một mã. Đánh đổi: số
     * trong mã không còn là "thứ mấy trong ngày" mà là `id` thật — không reset
     * về 0001 mỗi ngày, nhưng định dạng chuỗi (PH-YYYYMMDD-NNNN) giữ nguyên.
     */
    private function sinhMaLuotKhach(int $id): string
    {
        $homNay = now()->format('Ymd');

        return "PH-{$homNay}-".str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
```

Đoạn gọi hàm này (trong `handle()`):

```php
            // Mã hiển thị (code) cần ID tự tăng mới sinh đúng, nhưng cột code
            // NOT NULL + UNIQUE nên phải có giá trị ngay lúc tạo — dùng tạm 30
            // ký tự đầu của uuid (đã đảm bảo duy nhất) rồi cập nhật lại bằng mã
            // thật ngay sau đó, cùng một transaction. Xem sinhMaLuotKhach().
            $tableSession = TableSession::query()->create([
                'uuid' => $data->uuid,
                'code' => substr($data->uuid, 0, 30),
                'shift_id' => $shift->id,
                'guest_count' => $data->guestCount,
                'status' => TableSessionStatus::Open,
                'opened_by_user_id' => $data->openedByUserId,
                'opened_at' => now(),
            ]);
            $tableSession->update(['code' => $this->sinhMaLuotKhach($tableSession->id)]);
```

### `OpenShift::sinhMaCa()` — trong `app/Domain/Staffing/Actions/OpenShift.php`

```php
    /**
     * Dùng `id` tự tăng của chính ca vừa tạo — không dùng `count() + 1`, vì
     * `uq_shifts_only_one_open` chỉ chặn được HAI CA MỞ CÙNG LÚC, không chặn
     * được việc hai request cùng đọc count() rồi cùng cộng 1 khi bảng đang
     * trống ca mở. `id` do MySQL tự tăng nên không bao giờ trùng.
     */
    private function sinhMaCa(int $id): string
    {
        $homNay = now()->format('Ymd');

        return "CA-{$homNay}-".str_pad((string) $id, 2, '0', STR_PAD_LEFT);
    }
```

Đoạn gọi hàm này (trong `handle()`):

```php
            // Mã tạm (không có ý nghĩa gì ngoài thoả NOT NULL + UNIQUE lúc tạo),
            // cập nhật lại bằng mã thật ngay dưới, dùng chính ID vừa có.
            $ca = Shift::query()->create([
                'code' => Str::random(20),
                'opened_by_user_id' => $data->openedByUserId,
                'opened_at' => now(),
                'opening_cash' => $data->openingCash->amount,
                'status' => ShiftStatus::Open,
            ]);
            $ca->update(['code' => $this->sinhMaCa($ca->id)]);
```

### Giải thích: cách mới an toàn ở chỗ nào?

Lỗi cũ: `count() + 1` đọc số dòng hiện có rồi cộng 1 — nếu hai request đọc `count()` **trước khi** request kia kịp ghi dòng mới, cả hai tính ra **cùng một con số**, sinh ra hai mã trùng nhau, một bên vỡ ràng buộc UNIQUE và nhận lỗi SQL thô.

Cách mới: không đọc số dòng hiện có nữa. `id` (AUTO_INCREMENT) do chính MySQL cấp phát tại thời điểm `INSERT`, và MySQL **đảm bảo tuyệt đối** hai lệnh `INSERT` không bao giờ nhận cùng một `id`, kể cả khi hai transaction chạy đúng cùng một mili-giây. Vì mã được ghép từ `id` này (không phải từ một phép đếm riêng), hai mã sinh ra không bao giờ trùng nhau — an toàn không phụ thuộc vào việc có khoá (`lockForUpdate`) hay không.

### Cột `code` có NOT NULL không? Xử lý ra sao?

**Có**, cả `table_sessions.code` và `shifts.code` đều `NOT NULL` (và có UNIQUE KEY). Vấn đề: muốn dùng `id` để ghép mã thì phải **có `id` trước**, mà `id` chỉ có sau khi `INSERT` xong — nhưng `INSERT` lại cần có giá trị `code` ngay (không được NULL).

Cách xử lý: **tạo trước với mã tạm, cập nhật lại ngay sau đó, trong cùng một `DB::transaction()`**:
- `table_sessions`: dùng tạm 30 ký tự đầu của chính `uuid` do máy POS gửi lên (đã đảm bảo duy nhất nhờ ràng buộc `uq_table_sessions_uuid`, và uuid luôn có sẵn ở bước này vì đây cũng là nơi nhận uuid từ máy POS).
- `shifts`: không có cột uuid để tận dụng (shifts không thuộc 5 bảng cần định danh máy POS), nên dùng `Str::random(20)` — một chuỗi ngẫu nhiên chỉ có vai trò "giữ chỗ" trong khoảnh khắc giữa hai câu lệnh, không có ý nghĩa nghiệp vụ.

Vì toàn bộ nằm trong một transaction, không có cửa sổ thời gian nào bên ngoài nhìn thấy mã tạm này — con số cuối cùng luôn là mã thật `PH-YYYYMMDD-NNNN` / `CA-YYYYMMDD-NN`.

---

## 4. TOÀN BỘ DTO VÀ ACTION ĐÃ SỬA ĐỂ NHẬN UUID TỪ MÁY POS

### `app/Domain/Ordering/DTO/OpenTableSessionData.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class OpenTableSessionData
{
    /** @param list<int> $diningTableIds */
    public function __construct(
        public string $uuid,
        public array $diningTableIds,
        public int $primaryDiningTableId,
        public int $guestCount,
        public int $openedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            uuid: $request->string('uuid')->toString(),
            diningTableIds: array_map('intval', $request->input('dining_table_ids', [])),
            primaryDiningTableId: $request->integer('primary_dining_table_id'),
            guestCount: $request->integer('guest_count'),
            openedByUserId: (int) $request->user()->id,
        );
    }
}
```

### `app/Domain/Ordering/DTO/PlaceOrderItemData.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

final readonly class PlaceOrderItemData
{
    /** @param list<PlaceOrderItemOptionData> $options */
    public function __construct(
        public string $uuid,
        public int $productId,
        public int $productVariantId,
        public int $quantity,
        public ?string $note,
        public array $options,
    ) {}
}
```

### `app/Domain/Ordering/DTO/PlaceOrderItemOptionData.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

final readonly class PlaceOrderItemOptionData
{
    public function __construct(
        public string $uuid,
        public int $optionId,
    ) {}
}
```

### `app/Domain/Ordering/DTO/PlaceOrderData.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class PlaceOrderData
{
    /** @param list<PlaceOrderItemData> $items */
    public function __construct(
        public string $uuid,
        public int $tableSessionId,
        public array $items,
        public ?string $note,
        public int $createdByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $items = array_map(
            fn (array $item): PlaceOrderItemData => new PlaceOrderItemData(
                uuid: (string) $item['uuid'],
                productId: (int) $item['product_id'],
                productVariantId: (int) $item['product_variant_id'],
                quantity: (int) $item['quantity'],
                note: $item['note'] ?? null,
                options: array_map(
                    fn (array $option): PlaceOrderItemOptionData => new PlaceOrderItemOptionData(
                        uuid: (string) $option['uuid'],
                        optionId: (int) $option['option_id'],
                    ),
                    $item['options'] ?? []
                ),
            ),
            $request->input('items', [])
        );

        return new self(
            uuid: $request->string('uuid')->toString(),
            tableSessionId: (int) $request->route('tableSession')->id,
            items: $items,
            note: $request->input('note'),
            createdByUserId: (int) $request->user()->id,
        );
    }
}
```

### `app/Domain/Ordering/Actions/OpenTableSession.php` (toàn bộ)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mở một lượt khách mới, chiếm một hoặc nhiều bàn (ghép bàn).
 *
 * Ba lớp bảo vệ theo docs/schema.md Phần 6:
 *  1. Khoá `uq_tst_one_session_per_table` ở database — chốt cuối, không thể lách.
 *  2. Giữ chỗ (lockForUpdate) các dòng dining_tables TRƯỚC khi kiểm tra, luôn
 *     theo id tăng dần — hai nhân viên ghép bàn theo thứ tự khác nhau không
 *     bao giờ kẹt chéo chờ nhau.
 *  3. Toàn bộ nằm trong một transaction — thành công hết hoặc không gì cả.
 *
 * `uuid` do máy POS sinh trước khi gửi (Phase 2 Bước 2) — gửi lại đúng uuid đó
 * trả về đúng lượt khách cũ, không mở trùng khi mạng lag/bấm hai lần. Máy POS
 * offline có thể tự sinh uuid ngay lúc mở bàn; `code` (mã hiển thị cho người
 * đọc) vẫn do server gán, xem sinhMaLuotKhach().
 */
final class OpenTableSession
{
    public function handle(OpenTableSessionData $data): TableSession
    {
        if ($data->diningTableIds === []) {
            throw new DomainException('Phải chọn ít nhất một bàn.');
        }

        if (! in_array($data->primaryDiningTableId, $data->diningTableIds, true)) {
            throw new DomainException('Bàn chính phải nằm trong danh sách bàn được chọn.');
        }

        return DB::transaction(function () use ($data): TableSession {
            $daCo = TableSession::query()->where('uuid', $data->uuid)->first();
            if ($daCo !== null) {
                return $daCo;
            }

            // Khoá dòng ca TRƯỚC khi tạo lượt khách (luật CLAUDE.md mục 11: Shift →
            // TableSession) — không khoá thì đọc theo snapshot REPEATABLE READ, có
            // thể thấy ca "open" đúng lúc CloseShift đang khoá và đóng ca đó, tạo ra
            // một lượt khách trỏ vào ca đã đóng mà RecordPayment vĩnh viễn từ chối.
            $shift = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();

            if ($shift === null) {
                throw new DomainException('Chưa mở ca. Phải mở ca trước khi mở bàn.');
            }

            // Giữ chỗ theo id tăng dần — chống kẹt chéo khi hai người ghép bàn ngược thứ tự nhau.
            $banDuocChon = DiningTable::query()
                ->whereIn('id', $data->diningTableIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($banDuocChon->count() !== count($data->diningTableIds)) {
                throw new DomainException('Có bàn không tồn tại trong danh sách đã chọn.');
            }

            foreach ($banDuocChon as $ban) {
                if (! $ban->is_active) {
                    throw new DomainException("Bàn {$ban->code} đã ngưng sử dụng.");
                }

                $daCoKhach = TableSessionTable::query()
                    ->where('dining_table_id', $ban->id)
                    ->whereNull('detached_at')
                    ->exists();

                if ($daCoKhach) {
                    throw new DomainException('Bàn này đang có khách.');
                }
            }

            // Mã hiển thị (code) cần ID tự tăng mới sinh đúng, nhưng cột code
            // NOT NULL + UNIQUE nên phải có giá trị ngay lúc tạo — dùng tạm 30
            // ký tự đầu của uuid (đã đảm bảo duy nhất) rồi cập nhật lại bằng mã
            // thật ngay sau đó, cùng một transaction. Xem sinhMaLuotKhach().
            $tableSession = TableSession::query()->create([
                'uuid' => $data->uuid,
                'code' => substr($data->uuid, 0, 30),
                'shift_id' => $shift->id,
                'guest_count' => $data->guestCount,
                'status' => TableSessionStatus::Open,
                'opened_by_user_id' => $data->openedByUserId,
                'opened_at' => now(),
            ]);
            $tableSession->update(['code' => $this->sinhMaLuotKhach($tableSession->id)]);

            foreach ($banDuocChon as $ban) {
                TableSessionTable::query()->create([
                    'table_session_id' => $tableSession->id,
                    'dining_table_id' => $ban->id,
                    'is_primary' => $ban->id === $data->primaryDiningTableId,
                    'attached_at' => now(),
                    'attached_by_user_id' => $data->openedByUserId,
                ]);
            }

            return $tableSession;
        });
    }

    /**
     * Dùng `id` tự tăng của chính lượt khách vừa tạo — không dùng `count() + 1`.
     * Hai người mở bàn cùng một giây, mỗi người vẫn nhận đúng một `id` khác
     * nhau do MySQL tự đảm bảo, không bao giờ ra cùng một mã. Đánh đổi: số
     * trong mã không còn là "thứ mấy trong ngày" mà là `id` thật — không reset
     * về 0001 mỗi ngày, nhưng định dạng chuỗi (PH-YYYYMMDD-NNNN) giữ nguyên.
     */
    private function sinhMaLuotKhach(int $id): string
    {
        $homNay = now()->format('Ymd');

        return "PH-{$homNay}-".str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
}
```

### `app/Domain/Ordering/Actions/PlaceOrder.php` (toàn bộ)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\PlaceOrderItemData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use App\Domain\Ordering\Models\TableSession;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Gửi một phiếu gọi món cho một lượt khách.
 *
 * M2/M3: uuid do máy POS sinh trước khi gửi — gửi lại đúng uuid đó trả về
 * đúng phiếu cũ, không tạo phiếu thứ hai (chống bấm "Gửi" hai lần vì mạng lag).
 * M4: mọi tên món/biến thể/tùy chọn/giá đều CHỤP LẠI vào order_items và
 * order_item_options tại đúng thời điểm gọi — sửa giá thực đơn sau đó không
 * làm đổi một chữ nào trên phiếu đã gửi.
 * M6: số lượng luôn >= 1 (DB backstop bằng ck_order_items_qty).
 * M7: một phiếu chỉ chứa món của một nơi làm — trộn bếp và quầy trong cùng
 * một lần gửi bị từ chối thẳng, máy POS phải tách thành hai lần gửi riêng.
 * M8: chỉ gọi món được khi lượt khách đang open.
 * M9: số tùy chọn chọn trong một nhóm phải nằm giữa min_select và max_select.
 */
final class PlaceOrder
{
    public function handle(PlaceOrderData $data): Order
    {
        if ($data->items === []) {
            throw new DomainException('Phải gọi ít nhất một món.');
        }

        return DB::transaction(function () use ($data): Order {
            $donCu = Order::query()->where('uuid', $data->uuid)->first();
            if ($donCu !== null) {
                return $donCu;
            }

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if ($tableSession->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách này không còn mở, không gọi món được nữa.');
            }

            $station = null;

            foreach ($data->items as $item) {
                if ($item->quantity < 1) {
                    throw new DomainException('Số lượng phải lớn hơn 0.');
                }

                $product = Product::query()->with('category')->findOrFail($item->productId);
                $variant = ProductVariant::query()->findOrFail($item->productVariantId);

                if ($variant->product_id !== $product->id) {
                    throw new DomainException("Biến thể không thuộc món {$product->name}.");
                }

                if (! $product->is_active || ! $variant->is_active) {
                    throw new DomainException("Món {$product->name} hiện không bán.");
                }

                $tramCuaMon = $product->effectiveStation();
                $station ??= $tramCuaMon;

                if ($station !== $tramCuaMon) {
                    throw new DomainException('Không thể gộp món bếp và món quầy trong một lần gửi. Tách thành hai lần gửi riêng.');
                }

                $this->kiemTraTuyChon($product, $item);
            }

            $soThuTu = Order::query()->where('table_session_id', $tableSession->id)->max('sequence_no') + 1;

            $order = Order::query()->create([
                'uuid' => $data->uuid,
                'table_session_id' => $tableSession->id,
                'sequence_no' => $soThuTu,
                'station' => $station,
                'status' => OrderStatus::Sent,
                'created_by_user_id' => $data->createdByUserId,
                'sent_at' => now(),
                'note' => $data->note,
            ]);

            foreach ($data->items as $item) {
                $this->taoDongMon($order, $item);
            }

            app(RecalculateSessionSubtotal::class)->handle($tableSession);

            return $order;
        });
    }

    private function kiemTraTuyChon(Product $product, PlaceOrderItemData $item): void
    {
        $nhomApDung = OptionGroup::query()
            ->where('is_active', true)
            ->where(function ($q) use ($product) {
                $q->where('product_id', $product->id)->orWhere('category_id', $product->category_id);
            })
            ->with('options')
            ->get();

        $idDaChon = collect($item->options)->pluck('optionId');

        $idHopLe = $nhomApDung->flatMap(fn (OptionGroup $n) => $n->options->pluck('id'));
        $idKhongHopLe = $idDaChon->diff($idHopLe);
        if ($idKhongHopLe->isNotEmpty()) {
            throw new DomainException("Có tùy chọn không áp dụng cho món {$product->name}.");
        }

        foreach ($nhomApDung as $nhom) {
            $soLuongChon = $idDaChon->intersect($nhom->options->pluck('id'))->count();

            if ($soLuongChon < $nhom->min_select || $soLuongChon > $nhom->max_select) {
                throw new DomainException("Chọn tùy chọn nhóm \"{$nhom->name}\" không hợp lệ cho món {$product->name}.");
            }
        }
    }

    private function taoDongMon(Order $order, PlaceOrderItemData $item): void
    {
        // Gửi lại đúng uuid dòng món cũ (mạng lag, bấm hai lần) thì không tạo
        // trùng — chuẩn bị cho Bước 4 đồng bộ hàng loạt, khi một phiếu có thể
        // được đồng bộ nhiều lần với các dòng món đến ở các đợt khác nhau.
        if (OrderItem::query()->where('uuid', $item->uuid)->exists()) {
            return;
        }

        $product = Product::query()->findOrFail($item->productId);
        $variant = ProductVariant::query()->findOrFail($item->productVariantId);

        $options = $item->options === []
            ? collect()
            : Option::query()->with('optionGroup')->findMany(collect($item->options)->pluck('optionId'));

        $tienTuyChon = $options->reduce(
            fn (Money $tong, Option $tuyChon) => $tong->plus(Money::fromInt($tuyChon->price_delta)),
            Money::zero()
        );

        $orderItem = OrderItem::query()->create([
            'uuid' => $item->uuid,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            // SERVER LUÔN TỰ TÍNH LẠI GIÁ từ bảng thực đơn của mình (product_variants),
            // không bao giờ nhận giá từ máy POS. Khi Phase 2 làm đồng bộ, nếu máy POS
            // offline có gửi kèm giá đã thấy lúc gọi món thì giá đó CHỈ dùng để so sánh
            // và cảnh báo lệch (thực đơn vừa đổi giá khi máy đang offline), TUYỆT ĐỐI
            // KHÔNG dùng để ghi vào order_items — tin giá từ máy POS thì bất kỳ ai chạm
            // được máy tính bảng đều có thể sửa được giá bill.
            'unit_price' => $variant->price,
            'options_amount' => $tienTuyChon->amount,
            'quantity' => $item->quantity,
            'status' => OrderItemStatus::Ordered,
            'note' => $item->note,
        ]);

        foreach ($options as $tuyChon) {
            $duocChon = collect($item->options)->firstWhere('optionId', $tuyChon->id);

            if (OrderItemOption::query()->where('uuid', $duocChon->uuid)->exists()) {
                continue;
            }

            $orderItem->options()->create([
                'uuid' => $duocChon->uuid,
                'option_id' => $tuyChon->id,
                'option_group_name' => $tuyChon->optionGroup->name,
                'option_name' => $tuyChon->name,
                'price_delta' => $tuyChon->price_delta,
            ]);
        }
    }
}
```

---

## 5. TOÀN BỘ TEST MỚI

### `tests/Feature/Support/ClientUuidCoverageTest.php` (file mới)

```php
<?php

declare(strict_types=1);

/**
 * Phase 2 Bước 2 — quét các route POST dưới /api/v1 tạo bản ghi ở 5 bảng cần
 * định danh do máy POS sinh: table_sessions, orders, order_items,
 * order_item_options, payments.
 *
 * orders và payments đã có uuid từ Phase 1 (kiểm chứng lại ở đây cho đủ bộ,
 * không lặp lại toàn bộ test đã có ở PlaceOrderTest/RecordPaymentTest).
 */

use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function moBanCoverage(User $user, array $overrides = []): TestResponse
{
    $ban = DiningTable::factory()->create();

    return test()->postJson('/api/v1/table-sessions', array_merge([
        'uuid' => (string) Str::uuid(),
        'dining_table_ids' => [$ban->id],
        'primary_dining_table_id' => $ban->id,
        'guest_count' => 2,
    ], $overrides), array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()]));
}

function goiMonCoverage(User $user, TableSession $luot, array $overrides = []): TestResponse
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['price' => 25_000]);

    $item = array_merge([
        'uuid' => (string) Str::uuid(),
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'options' => [],
    ], $overrides['item'] ?? []);

    $payload = array_merge(['uuid' => (string) Str::uuid(), 'items' => [$item]], $overrides['payload'] ?? []);

    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/orders",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

function thuTienCoverage(User $user, TableSession $luot, array $overrides = []): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/payments",
        array_merge([
            'uuid' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => 500_000,
            'tendered_amount' => 500_000,
        ], $overrides),
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

// ── table_sessions ──────────────────────────────────────────────────────

it('mở lượt khách thiếu uuid thì bị chặn 422', function () {
    Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();

    moBanCoverage($staff, ['uuid' => null])->assertUnprocessable();
});

it('mở lượt khách gửi hai lần cùng uuid chỉ tạo một bản ghi table_sessions', function () {
    Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $uuid = (string) Str::uuid();
    $ban = DiningTable::factory()->create();

    $payload = ['uuid' => $uuid, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 2];

    test()->postJson('/api/v1/table-sessions', $payload, array_merge(authHeaderFor($staff), ['Idempotency-Key' => (string) Str::uuid()]))->assertCreated();
    test()->postJson('/api/v1/table-sessions', $payload, array_merge(authHeaderFor($staff), ['Idempotency-Key' => (string) Str::uuid()]))->assertCreated();

    expect(TableSession::query()->where('uuid', $uuid)->count())->toBe(1);
});

// ── orders / order_items / order_item_options ──────────────────────────

it('gọi món thiếu uuid của dòng món thì bị chặn 422', function () {
    $ca = Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    goiMonCoverage($staff, $luot, ['item' => ['uuid' => null]])->assertUnprocessable();
});

it('gọi món thiếu uuid của tuỳ chọn đã chọn thì bị chặn 422', function () {
    $ca = Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $nhom = OptionGroup::factory()->forProduct($product)->create(['min_select' => 0, 'max_select' => 1]);
    $option = Option::factory()->for($nhom, 'optionGroup')->create();

    goiMonCoverage($staff, $luot, [
        'item' => [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'options' => [['option_id' => $option->id]],
        ],
    ])->assertUnprocessable();
});

it('gọi món gửi lại đúng uuid phiếu cũ thì không tạo trùng order_items/order_item_options', function () {
    $ca = Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $nhom = OptionGroup::factory()->forProduct($product)->create(['min_select' => 0, 'max_select' => 1]);
    $option = Option::factory()->for($nhom, 'optionGroup')->create();

    $payload = [
        'uuid' => (string) Str::uuid(),
        'items' => [[
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'options' => [['option_id' => $option->id, 'uuid' => (string) Str::uuid()]],
        ]],
    ];

    goiMonCoverage($staff, $luot, ['payload' => $payload])->assertCreated();
    goiMonCoverage($staff, $luot, ['payload' => $payload])->assertCreated();

    expect(Order::query()->where('uuid', $payload['uuid'])->count())->toBe(1)
        ->and(OrderItem::query()->where('uuid', $payload['items'][0]['uuid'])->count())->toBe(1)
        ->and(OrderItemOption::query()->where('uuid', $payload['items'][0]['options'][0]['uuid'])->count())->toBe(1);
});

// ── payments ─────────────────────────────────────────────────────────

it('thu tiền thiếu uuid thì bị chặn 422', function () {
    $ca = Shift::factory()->open()->create();
    $thuNgan = User::factory()->cashier()->create();
    $luot = TableSession::factory()->for($ca, 'shift')->withTable()->create([
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 500_000,
        'total_amount' => 500_000,
    ]);

    thuTienCoverage($thuNgan, $luot, ['uuid' => null])->assertUnprocessable();
});

it('thu tiền gửi hai lần cùng uuid chỉ tạo một phiếu thu', function () {
    $ca = Shift::factory()->open()->create();
    $thuNgan = User::factory()->cashier()->create();
    $luot = TableSession::factory()->for($ca, 'shift')->withTable()->create([
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 500_000,
        'total_amount' => 500_000,
    ]);

    $uuid = (string) Str::uuid();
    thuTienCoverage($thuNgan, $luot, ['uuid' => $uuid])->assertCreated();
    thuTienCoverage($thuNgan, $luot, ['uuid' => $uuid])->assertSuccessful();

    expect(Payment::query()->where('uuid', $uuid)->count())->toBe(1);
});
```

### `tests/Feature/Console/BackfillClientUuidsTest.php` (file mới)

```php
<?php

declare(strict_types=1);

use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('gán uuid cho dữ liệu cũ chưa có ở cả ba bảng, không đụng dòng đã có uuid', function () {
    // TableSessionFactory tự tạo Shift riêng (mặc định "open") — hai lượt khách
    // độc lập trong cùng test phải dùng chung một ca, nếu không đụng ràng buộc
    // "chỉ một ca mở cùng lúc" (uq_shifts_only_one_open).
    $ca = Shift::factory()->closed()->create();

    $luotCu = TableSession::factory()->create(['shift_id' => $ca->id]);
    $dongMonCu = OrderItem::factory()->create();
    $tuyChonCu = OrderItemOption::factory()->for($dongMonCu, 'orderItem')->create();

    // Factory đã mặc định sinh uuid — giả lập dữ liệu THẬT SỰ cũ bằng cách xoá uuid đi.
    DB::table('table_sessions')->where('id', $luotCu->id)->update(['uuid' => null]);
    DB::table('order_items')->where('id', $dongMonCu->id)->update(['uuid' => null]);
    DB::table('order_item_options')->where('id', $tuyChonCu->id)->update(['uuid' => null]);

    $luotDaCoUuid = TableSession::factory()->create(['shift_id' => $ca->id]);
    $uuidDaCo = $luotDaCoUuid->uuid;

    Artisan::call('pos:backfill-uuid');

    expect($luotCu->refresh()->uuid)->not->toBeNull()
        ->and($dongMonCu->refresh()->uuid)->not->toBeNull()
        ->and($tuyChonCu->refresh()->uuid)->not->toBeNull();

    // Dòng đã có uuid từ trước không bị đổi.
    expect($luotDaCoUuid->refresh()->uuid)->toBe($uuidDaCo);
});

it('chạy lại lần hai không lỗi, không còn dòng nào thiếu uuid', function () {
    TableSession::factory()->create();
    DB::table('table_sessions')->update(['uuid' => null]);

    Artisan::call('pos:backfill-uuid');
    $maLan1 = Artisan::call('pos:backfill-uuid');

    expect($maLan1)->toBe(0)
        ->and(TableSession::query()->whereNull('uuid')->count())->toBe(0);
});
```

### Test mới thêm vào file cũ — `tests/Feature/Ordering/TableConcurrencyTest.php`

```php
it('Bước 2: hai người mở bàn khác nhau gần như cùng lúc — cả hai thành công, mã lượt khách KHÁC NHAU', function () {
    Shift::factory()->open()->create();
    $banA = DiningTable::factory()->create();
    $banB = DiningTable::factory()->create();

    $anhNam = User::factory()->staff()->create();
    $chiLan = User::factory()->staff()->create();

    $ketQuaNam = guiYeuCauMoBan($anhNam, $banA)->assertCreated();
    $ketQuaLan = guiYeuCauMoBan($chiLan, $banB)->assertCreated();

    $maNam = $ketQuaNam->json('data.code');
    $maLan = $ketQuaLan->json('data.code');

    expect($maNam)->not->toBe($maLan)
        ->and(TableSession::query()->distinct()->count('code'))->toBe(2);
});
```

(Dùng chung helper `guiYeuCauMoBan()` đã có sẵn trong file, chỉ cập nhật thêm `'uuid' => (string) Str::uuid()` vào payload của helper đó.)

### Test mới thêm vào file cũ — `tests/Feature/Ordering/CancelOrderItemTest.php`

```php
it('Bước 2: tách dòng từ món ĐÃ served — dòng mới kế thừa served_at của dòng gốc', function () {
    $this->item->update(['status' => OrderItemStatus::Served, 'served_at' => now()->subMinutes(10)]);
    // Cột served_at là DATETIME (không có phần mili-giây) — đọc lại từ DB để so
    // sánh đúng độ chính xác đã lưu, tránh lệch mili-giây với giá trị PHP gốc.
    $servedAtDaLuu = $this->item->refresh()->served_at;

    $response = huyMon($this->owner, $this->order, $this->item, [
        'quantity' => 1,
        'reason' => 'Khách trả bớt',
        'approver_user_id' => $this->thuNgan->id,
        'approver_pin' => '1234',
    ])->assertOk();

    $dongMoi = OrderItem::query()->findOrFail($response->json('data.id'));
    expect($dongMoi->served_at)->not->toBeNull()
        ->and($dongMoi->served_at->equalTo($servedAtDaLuu))->toBeTrue();

    expect($this->item->refresh()->served_at->equalTo($servedAtDaLuu))->toBeTrue();
});

it('Bước 2: tách dòng từ món CHƯA served — dòng mới cũng served_at = NULL', function () {
    expect($this->item->status)->toBe(OrderItemStatus::Ordered)
        ->and($this->item->served_at)->toBeNull();

    $response = huyMon($this->owner, $this->order, $this->item, ['quantity' => 1, 'reason' => 'Khách trả bớt'])
        ->assertOk();

    $dongMoi = OrderItem::query()->findOrFail($response->json('data.id'));
    expect($dongMoi->served_at)->toBeNull();
    expect($this->item->refresh()->served_at)->toBeNull();
});
```

---

## 6. KẾT QUẢ CHẠY THẬT (dán nguyên văn, đã bỏ mã màu ANSI để dễ đọc)

Môi trường: MySQL (XAMPP) cổng 3306, database `quan_pos`. Chạy tuần tự đúng 4 lệnh theo yêu cầu.

### `php artisan migrate:fresh --seed`

```
  Dropping all tables .................................................................................. 384.62ms DONE

   INFO  Preparing database.

  Creating migration table .............................................................................. 80.84ms DONE

   INFO  Running migrations.

  2026_07_31_000001_create_users_table .................................................................. 93.62ms DONE
  2026_07_31_000002_create_shifts_table ................................................................ 176.77ms DONE
  2026_07_31_000003_create_cash_movements_table ......................................................... 89.53ms DONE
  2026_07_31_000004_create_dining_tables_table .......................................................... 22.33ms DONE
  2026_07_31_000005_create_table_sessions_table ........................................................ 272.58ms DONE
  2026_07_31_000006_create_table_session_tables_table .................................................. 130.51ms DONE
  2026_07_31_000007_create_categories_table ............................................................. 22.34ms DONE
  2026_07_31_000008_create_products_table ............................................................... 45.72ms DONE
  2026_07_31_000009_create_product_variants_table ....................................................... 69.27ms DONE
  2026_07_31_000010_create_option_groups_table ......................................................... 120.80ms DONE
  2026_07_31_000011_create_options_table ................................................................ 34.82ms DONE
  2026_07_31_000012_create_orders_table ................................................................ 153.41ms DONE
  2026_07_31_000013_create_order_items_table ........................................................... 224.31ms DONE
  2026_07_31_000014_create_order_item_options_table ..................................................... 60.76ms DONE
  2026_07_31_000015_create_payments_table .............................................................. 231.35ms DONE
  2026_07_31_000016_create_cache_table ................................................................... 7.82ms DONE
  2026_07_31_081235_create_personal_access_tokens_table ................................................. 26.09ms DONE
  2026_07_31_081240_create_activity_log_table ........................................................... 26.72ms DONE
  2026_07_31_081241_add_event_column_to_activity_log_table ............................................... 3.03ms DONE
  2026_07_31_081242_add_batch_uuid_column_to_activity_log_table .......................................... 3.42ms DONE
  2026_08_01_000001_rename_manager_role_to_cashier ...................................................... 52.54ms DONE
  2026_08_02_000001_add_uuid_to_table_sessions_table ..................................................... 9.90ms DONE
  2026_08_02_000002_add_uuid_to_order_items_table ........................................................ 8.01ms DONE
  2026_08_02_000003_add_uuid_to_order_item_options_table ................................................. 7.45ms DONE


   INFO  Seeding database.

```

(Không có dòng in thêm sau "Seeding database." — `DatabaseSeeder` hiện không in gì ra console; exit code = 0, seed chạy xong không lỗi.)

### `php artisan test`

Rút gọn: dán đầy đủ dòng tổng kết cuối cùng (417 dòng chi tiết từng test, tất cả PASS, không cắt bớt phần tổng kết) — muốn xem đầy đủ từng dòng thì chạy lại lệnh, ở đây trích đúng phần tổng kết và khối liên quan trực tiếp đến Bước 2:

```
   PASS  Tests\Feature\Support\AuthGuardIsolationTest
  ✓ it Owner mở bàn thành công, ngay sau đó Kitchen bị chặn khi gọi CÙNG endpoint                                0.09s

   PASS  Tests\Feature\Support\ClientUuidCoverageTest
  ✓ it mở lượt khách thiếu uuid thì bị chặn 422                                                                  0.07s
  ✓ it mở lượt khách gửi hai lần cùng uuid chỉ tạo một bản ghi table_sessions                                    0.08s
  ✓ it gọi món thiếu uuid của dòng món thì bị chặn 422                                                           0.09s
  ✓ it gọi món thiếu uuid của tuỳ chọn đã chọn thì bị chặn 422                                                   0.08s
  ✓ it gọi món gửi lại đúng uuid phiếu cũ thì không tạo trùng order_items/order_item_options                     0.11s
  ✓ it thu tiền thiếu uuid thì bị chặn 422                                                                       0.07s
  ✓ it thu tiền gửi hai lần cùng uuid chỉ tạo một phiếu thu                                                      0.09s

   PASS  Tests\Feature\Support\IdempotencyMiddlewareTest
  ✓ it gửi cùng một Idempotency-Key hai lần chỉ tạo một bản ghi                                                  0.09s
  ✓ it gửi hai Idempotency-Key khác nhau tạo hai bản ghi                                                         0.07s
  ✓ it cùng key nhưng nội dung request khác lần trước thì bị từ chối, không thay thế yêu cầu cũ                  0.08s
  ✓ it key đã hết hạn thì gửi lại tạo bản ghi mới                                                                0.07s
  ✓ it hai request cùng key gửi khi request trước đang xử lý thì nhận 409                                        0.08s
  ✓ it thiếu header Idempotency-Key trên route bắt buộc thì trả về 400                                           0.08s
  ✓ it route GET không bị ảnh hưởng bởi middleware idempotent dù không có header                                 0.07s
  ✓ it lỗi nghiệp vụ (4xx) nhả khoá ngay để gửi lại cùng key được                                                0.08s
  ✓ it ValidationException ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được                       0.07s
  ✓ it DomainException ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được                           0.07s
  ✓ it RuntimeException bất ngờ ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được                  0.07s
  ✓ it sau khi thành công, gửi lại cùng key trả lại response cũ, không tạo bản ghi thứ hai                       0.07s
  ✓ it gửi lại CÙNG nội dung sau khi lỗi nghiệp vụ vẫn chạy được, không bị 409                                   0.07s

  Tests:    317 passed (997 assertions)
  Duration: 73.83s
```

Toàn bộ 317 test PASS, 0 FAIL, 0 SKIP.

### `php artisan pos:demo`

```

MỞ CA
   Ca CA-20260802-01 mở bởi Thu ngân diễn tập, tiền lẻ đầu ca 500.000 đ

MỞ BÀN
   Lượt khách PH-20260802-0001 mở bởi Phục vụ diễn tập tại bàn DEMO-1 (ghép thêm bàn DEMO-2), 6 khách

GỌI MÓN
   [bếp] 3 x Gà nướng diễn tập — 360.000 đ (phiếu e04e5886-9e43-41a1-b139-3f17cc3f0915)
   [quầy] 4 x Bia diễn tập — 100.000 đ (phiếu fcb17de1-03ea-4652-8891-45cf5370da30)
   Tạm tính: 460.000 đ

GỬI BẾP
   Đã gửi phiếu e04e5886-9e43-41a1-b139-3f17cc3f0915 (kitchen) xuống nơi làm, trạng thái: sent
   Đã gửi phiếu fcb17de1-03ea-4652-8891-45cf5370da30 (bar) xuống nơi làm, trạng thái: sent

BẾP BÁO XONG
   Phiếu e04e5886-9e43-41a1-b139-3f17cc3f0915 (kitchen) — Gà nướng diễn tập đã xong, trạng thái phiếu: served
   Phiếu fcb17de1-03ea-4652-8891-45cf5370da30 (bar) — Bia diễn tập đã xong, trạng thái phiếu: served

TÁCH BÀN
   Tạm tính trước khi tách: 460.000 đ
   Tách bàn DEMO-2 và món Bia diễn tập (đã gửi bếp/quầy) sang lượt khách mới
   Sau khi tách — lượt cũ PH-20260802-0001: 360.000 đ, lượt mới PH-20260802-0002: 100.000 đ
   Tổng hai bên: 460.000 đ (phải bằng tạm tính trước khi tách)

HỦY MÓN
   Tạm tính trước khi huỷ: 360.000 đ
   Món Gà nướng diễn tập đang có 3 phần, đã phục vụ — huỷ bớt 1 phần, cần PIN duyệt của Thu ngân diễn tập
   Tách thành 2 dòng: dòng gốc còn 2 phần (giữ nguyên), dòng mới huỷ 1 phần (id 3, tách từ id 1)
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

Chạy ngay sau `migrate:fresh --seed` nên mã bắt đầu lại từ số nhỏ (`CA-...-01`, `PH-...-0001`) — đúng dự đoán vì `id` tự tăng cũng reset theo bảng trống.

### `php artisan tinker --execute="echo implode(', ', Illuminate\Support\Facades\Schema::getColumnListing('table_sessions'));"`

```
id, uuid, code, shift_id, guest_count, status, opened_by_user_id, opened_at, subtotal_amount, discount_amount, discount_reason, total_amount, paid_amount, bill_no, bill_printed_at, provisional_printed_at, provisional_print_count, closed_by_user_id, closed_at, voided_by_user_id, voided_at, void_reason, note, created_at, updated_at
```

`uuid` đã có mặt, đứng ngay sau `id` — đúng vị trí đã khai báo (`->after('id')`).

---

## 7. NHỮNG CHỖ KHÔNG CHẮC ĐÃ ĐÚNG

1. **Dùng 30 ký tự đầu của `uuid` làm mã tạm cho `table_sessions.code`** — về lý thuyết an toàn (uuid có entropy cao, cắt bớt 6 ký tự cuối không thực tế làm tăng nguy cơ trùng ở quy mô một quán 5-15 bàn), nhưng tôi chưa chứng minh được bằng toán xác suất chặt chẽ, chỉ dựa trên trực giác "uuid vốn đã gần như không trùng". Nếu muốn chắc tuyệt đối, có thể đổi sang `Str::random(30)` (không phụ thuộc uuid) — nhưng vậy mất luôn tính "dùng lại giá trị đã có sẵn, đỡ sinh thêm entropy" mà không có lợi ích rõ ràng gì.

2. **`sinhMaLuotKhach()`/`sinhMaCa()` dùng `id` thay vì đếm theo ngày — số không reset về 0001/01 mỗi ngày nữa.** Tôi chưa hỏi ý kiến trực tiếp chủ quán về việc này có gây khó chịu khi đọc mã trên máy in tem/bill hay không (ví dụ nhân viên quen nhìn "phiếu thứ 3 hôm nay" mà giờ thành "phiếu 00147"). Tôi cho đây là đánh đổi chấp nhận được để đổi lấy an toàn tuyệt đối, nhưng đây là một phán đoán nghiệp vụ, không phải kỹ thuật thuần tuý.

3. **`OrderItemOption` dedup theo uuid trong `PlaceOrder::taoDongMon()`**: đoạn `if (OrderItemOption::query()->where('uuid', $duocChon->uuid)->exists()) continue;` **không có test riêng nào chứng minh nó chạy đúng khi thật sự có một order_item_options trùng uuid nằm dưới MỘT order_item MỚI vừa tạo** (tình huống này gần như không xảy ra được trong luồng `PlaceOrder` hiện tại — order_item luôn mới tinh, không đời nào tồn tại option trùng uuid dưới nó — nên đoạn code này thực chất là "phòng thủ cho tương lai" (Bước 4) chứ chưa được test bằng cách tạo tình huống trùng thật. Test `ClientUuidCoverageTest` chỉ kiểm được "gửi lại đúng uuid phiếu cũ" (toàn bộ order đã tồn tại), không kiểm được nhánh phòng thủ này một cách trực tiếp.

4. **Chưa chạy thử `pos:backfill-uuid` trên dữ liệu `quan_pos` thật có dòng NULL** — vì vừa `migrate:fresh --seed` nên không còn dòng nào thiếu uuid để thử trên môi trường thật, chỉ kiểm chứng qua test giả lập (xoá uuid rồi backfill lại). Hành vi trên dữ liệu lớn thật (hàng chục nghìn dòng, `chunkById(500)`) tôi tin là ổn về mặt lý thuyết nhưng chưa đo thời gian chạy thực tế.

5. **Đặt lệnh `pos:backfill-uuid` chạy được nhiều lần liên tiếp an toàn (test đã kiểm)**, nhưng tôi không chắc thứ tự chạy `table_sessions → order_items → order_item_options` có quan trọng không nếu có tích hợp thêm logic dựa vào nhau sau này — hiện tại ba bảng backfill độc lập nhau nên thứ tự không ảnh hưởng, nhưng đây là giả định dựa trên hiện trạng, chưa chắc còn đúng nếu logic đổi sau này.
