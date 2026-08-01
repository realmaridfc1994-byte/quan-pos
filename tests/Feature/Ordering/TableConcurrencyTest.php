<?php

declare(strict_types=1);

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * B1: một bàn tại một thời điểm chỉ thuộc tối đa một lượt khách đang mở.
 *
 * Ghi chú thật thà: Pest ở dự án này chạy mỗi test trong một transaction bọc
 * ngoài trên một kết nối database duy nhất — PHP đơn luồng, không có cách nào
 * tạo ra hai kết nối THẬT chạm khoá cùng lúc mà không dựng thêm tiến trình PHP
 * thứ hai (nặng, dễ vỡ, không hợp với quy mô quán 5-15 bàn). Test này mô phỏng
 * bằng hai request GỬI LIÊN TIẾP vào cùng một bàn — đúng hành vi người dùng
 * thật sự thấy khi hai máy "đụng" nhau: người sau luôn nhận đúng thông báo
 * tiếng Việt, không phải lỗi database thô. Chốt chặn thật cho trường hợp hai
 * request đến CÙNG lúc là khoá `uq_tst_one_session_per_table` ở tầng database
 * (không phụ thuộc lập trình viên nhớ hay quên) — được xác nhận riêng ở test
 * dưới cùng bằng cách chèn thẳng hai dòng trùng nhau qua Eloquent, bỏ qua toàn
 * bộ tầng ứng dụng.
 */
function guiYeuCauMoBan(User $user, DiningTable $ban): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->postJson('/api/v1/table-sessions', [
        'dining_table_ids' => [$ban->id],
        'primary_dining_table_id' => $ban->id,
        'guest_count' => 2,
    ], [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

it('B1: hai người cùng bấm mở một bàn — đúng một thành công, người kia nhận thông báo tiếng Việt', function () {
    Shift::factory()->open()->create();
    $ban = DiningTable::factory()->create(['code' => 'B05']);

    $anhNam = User::factory()->staff()->create(['name' => 'Anh Nam']);
    $chiLan = User::factory()->staff()->create(['name' => 'Chị Lan']);

    $ketQuaNam = guiYeuCauMoBan($anhNam, $ban);
    $ketQuaLan = guiYeuCauMoBan($chiLan, $ban);

    $daThanhCong = collect([$ketQuaNam, $ketQuaLan])->filter(fn ($r) => $r->status() === 201);
    $daThatBai = collect([$ketQuaNam, $ketQuaLan])->filter(fn ($r) => $r->status() === 422);

    expect($daThanhCong)->toHaveCount(1)
        ->and($daThatBai)->toHaveCount(1);

    $daThatBai->first()->assertJsonPath('message', 'Bàn này đang có khách.');

    expect(TableSession::query()->count())->toBe(1);
});

it('B1 (chốt DB cuối cùng): uq_tst_one_session_per_table chặn hai dòng "đang chiếm" cùng một bàn dù bỏ qua tầng ứng dụng', function () {
    $ca = Shift::factory()->open()->create();
    $ban = DiningTable::factory()->create();
    $luot1 = TableSession::factory()->create(['shift_id' => $ca->id]);
    $luot2 = TableSession::factory()->create(['shift_id' => $ca->id]);
    $user = User::factory()->create();

    TableSessionTable::query()->create([
        'table_session_id' => $luot1->id,
        'dining_table_id' => $ban->id,
        'is_primary' => true,
        'attached_at' => now(),
        'attached_by_user_id' => $user->id,
    ]);

    expect(fn () => TableSessionTable::query()->create([
        'table_session_id' => $luot2->id,
        'dining_table_id' => $ban->id,
        'is_primary' => true,
        'attached_at' => now(),
        'attached_by_user_id' => $user->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});
