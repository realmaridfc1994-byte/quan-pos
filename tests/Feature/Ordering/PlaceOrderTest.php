<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function guiMon(User $user, TableSession $luot, array $payload, ?string $idempotencyKey = null): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->postJson("/api/v1/table-sessions/{$luot->id}/orders", $payload, [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
    ]);
}

beforeEach(function () {
    $this->ca = Shift::factory()->open()->create();
    $this->staff = User::factory()->staff()->create();
    $this->luot = TableSession::factory()->withTable()->create([
        'shift_id' => $this->ca->id,
        'status' => TableSessionStatus::Open,
    ]);

    $this->nhomBep = Category::factory()->kitchenCategory()->create();
    $this->mon = Product::factory()->for($this->nhomBep)->create(['name' => 'Gà nướng']);
    $this->bienThe = ProductVariant::factory()->for($this->mon)->create(['name' => 'Phần', 'price' => 80_000]);
});

function goiMonDon(TableSession $luot, Product $mon, ProductVariant $bienThe, int $soLuong = 1, array $optionIds = []): array
{
    return [
        'uuid' => (string) Str::uuid(),
        'items' => [
            [
                'product_id' => $mon->id,
                'product_variant_id' => $bienThe->id,
                'quantity' => $soLuong,
                'option_ids' => $optionIds,
            ],
        ],
    ];
}

it('M1: phiếu gọi món gắn vào lượt khách, không gắn vào bàn', function () {
    $payload = goiMonDon($this->luot, $this->mon, $this->bienThe);

    guiMon($this->staff, $this->luot, $payload)->assertCreated();

    $order = Order::query()->sole();
    expect($order->table_session_id)->toBe($this->luot->id);
    expect(Schema::hasColumn('orders', 'dining_table_id'))->toBeFalse();
});

it('M2: mỗi phiếu có uuid riêng, hai lần gọi khác uuid tạo hai phiếu', function () {
    guiMon($this->staff, $this->luot, goiMonDon($this->luot, $this->mon, $this->bienThe))->assertCreated();
    guiMon($this->staff, $this->luot, goiMonDon($this->luot, $this->mon, $this->bienThe))->assertCreated();

    expect(Order::query()->count())->toBe(2);
    expect(Order::query()->distinct()->count('uuid'))->toBe(2);
});

it('M3: gửi lại đúng uuid cũ trả về đúng phiếu cũ, không tạo phiếu mới', function () {
    $payload = goiMonDon($this->luot, $this->mon, $this->bienThe);

    $lanDau = guiMon($this->staff, $this->luot, $payload)->assertCreated();
    $lanHai = guiMon($this->staff, $this->luot, $payload)->assertCreated();

    expect(Order::query()->count())->toBe(1);
    expect($lanHai->json('data.id'))->toBe($lanDau->json('data.id'));
});

it('M4: đổi giá món sau khi gọi, hoá đơn cũ không đổi giá', function () {
    $payload = goiMonDon($this->luot, $this->mon, $this->bienThe, soLuong: 2);
    guiMon($this->staff, $this->luot, $payload)->assertCreated();

    $this->bienThe->update(['price' => 999_000]);
    $this->mon->update(['name' => 'Gà nướng đặc biệt (đã đổi tên)']);

    $token = $this->staff->createToken('pos-app')->plainTextToken;
    $chiTiet = test()->getJson("/api/v1/table-sessions/{$this->luot->id}", [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    $dongMon = $chiTiet->json('data.orders.0.items.0');
    expect($dongMon['unit_price'])->toBe(80_000)
        ->and($dongMon['product_name'])->toBe('Gà nướng')
        ->and($dongMon['line_amount'])->toBe(160_000);
});

it('M5: line_amount là cột database tự tính, không ai ghi tay sai được', function () {
    $payload = goiMonDon($this->luot, $this->mon, $this->bienThe, soLuong: 3);
    guiMon($this->staff, $this->luot, $payload)->assertCreated();

    $dongMon = OrderItem::query()->sole();
    expect($dongMon->line_amount)->toBe(240_000);

    // Cố ghi thẳng một giá trị sai qua SQL thô, bỏ qua tầng Eloquent/Money hoàn toàn.
    try {
        DB::statement("UPDATE order_items SET line_amount = 1 WHERE id = {$dongMon->id}");
    } catch (Throwable $e) {
        // MySQL 8: từ chối cả câu lệnh — đúng hành vi mong đợi, không cần làm gì thêm.
    }

    // MariaDB 10.4 (máy dev): nhận câu lệnh nhưng tự bỏ giá trị sai, tính lại đúng.
    expect($dongMon->refresh()->line_amount)->toBe(240_000);
});

it('M6: số lượng phải >= 1', function () {
    $payload = goiMonDon($this->luot, $this->mon, $this->bienThe, soLuong: 0);

    guiMon($this->staff, $this->luot, $payload)->assertUnprocessable();

    expect(Order::query()->count())->toBe(0);
});

it('M8: lượt khách đang billing thì không gọi món được nữa', function () {
    $this->luot->update(['status' => TableSessionStatus::Billing]);

    guiMon($this->staff, $this->luot, goiMonDon($this->luot, $this->mon, $this->bienThe))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Lượt khách này không còn mở, không gọi món được nữa.');
});

it('M8: lượt khách đã đóng thì không gọi món được nữa', function () {
    $this->luot->update([
        'status' => TableSessionStatus::Closed,
        'closed_at' => now(),
        'closed_by_user_id' => $this->staff->id,
    ]);

    guiMon($this->staff, $this->luot, goiMonDon($this->luot, $this->mon, $this->bienThe))
        ->assertUnprocessable();
});

it('M9: chọn tuỳ chọn ít hơn số tối thiểu bắt buộc thì bị chặn', function () {
    $nhom = OptionGroup::factory()->forProduct($this->mon)->required()->create([
        'name' => 'Độ cay',
        'min_select' => 1,
        'max_select' => 1,
    ]);
    Option::factory()->for($nhom, 'optionGroup')->create();

    $payload = goiMonDon($this->luot, $this->mon, $this->bienThe, optionIds: []);

    guiMon($this->staff, $this->luot, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'DOMAIN_ERROR');

    expect(Order::query()->count())->toBe(0);
});

it('M9: chọn nhiều hơn số tối đa cho phép thì bị chặn', function () {
    $nhom = OptionGroup::factory()->forProduct($this->mon)->create([
        'name' => 'Đá',
        'min_select' => 0,
        'max_select' => 1,
    ]);
    $itCa = Option::factory()->for($nhom, 'optionGroup')->create(['name' => 'Ít đá']);
    $nhieuDa = Option::factory()->for($nhom, 'optionGroup')->create(['name' => 'Nhiều đá']);

    $payload = goiMonDon($this->luot, $this->mon, $this->bienThe, optionIds: [$itCa->id, $nhieuDa->id]);

    guiMon($this->staff, $this->luot, $payload)->assertUnprocessable();
});

it('M9: chọn đúng số tùy chọn trong giới hạn thì thành công và giá được cộng thêm', function () {
    $nhom = OptionGroup::factory()->forProduct($this->mon)->create([
        'name' => 'Thêm',
        'min_select' => 0,
        'max_select' => 2,
    ]);
    $themMi = Option::factory()->for($nhom, 'optionGroup')->create(['name' => 'Thêm mì', 'price_delta' => 10_000]);

    $payload = goiMonDon($this->luot, $this->mon, $this->bienThe, optionIds: [$themMi->id]);

    $response = guiMon($this->staff, $this->luot, $payload)->assertCreated();

    expect($response->json('data.items.0.options_amount'))->toBe(10_000)
        ->and($response->json('data.items.0.line_amount'))->toBe(90_000)
        ->and($response->json('data.items.0.options.0.option_name'))->toBe('Thêm mì');
});

it('M7: trộn món bếp và món quầy trong một lần gửi thì bị chặn', function () {
    $nhomBar = Category::factory()->barCategory()->create();
    $monBar = Product::factory()->for($nhomBar)->create();
    $bienTheBar = ProductVariant::factory()->for($monBar)->create();

    $payload = [
        'uuid' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $this->mon->id, 'product_variant_id' => $this->bienThe->id, 'quantity' => 1],
            ['product_id' => $monBar->id, 'product_variant_id' => $bienTheBar->id, 'quantity' => 1],
        ],
    ];

    guiMon($this->staff, $this->luot, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Không thể gộp món bếp và món quầy trong một lần gửi. Tách thành hai lần gửi riêng.');

    expect(Order::query()->count())->toBe(0);
});

it('bếp không có quyền gọi món', function () {
    $bep = User::factory()->kitchen()->create();

    guiMon($bep, $this->luot, goiMonDon($this->luot, $this->mon, $this->bienThe))->assertForbidden();
});
