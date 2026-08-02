<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TableSession>
 */
class TableSessionFactory extends Factory
{
    protected $model = TableSession::class;

    /**
     * Đúng thứ tự y hệt OpenTableSession::handle() thật: ghi trước bằng mã
     * tạm duy nhất (ở đây dùng luôn uuid), có id thật rồi mới gán mã thật
     * theo định dạng PH-{Ymd}-{id} — xem OpenTableSession::sinhMaLuotKhach().
     * Không dùng numerify() sinh số ngẫu nhiên rời rạc như trước, vì mã đó
     * không phải mã thật máy sẽ sinh ra, gây hiểu nhầm khi tra bằng tinker.
     *
     * CHỈ tự sinh khi `code` vẫn còn nguyên dạng mã tạm (30 ký tự đầu của một
     * uuid) — nếu người gọi factory tự truyền `code` riêng (ví dụ test cần
     * một mã cố định để so khớp thông báo lỗi), giữ nguyên giá trị đó.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (TableSession $tableSession) {
            if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{6}$/i', (string) $tableSession->code)) {
                return;
            }

            $tableSession->update([
                'code' => 'PH-'.now()->format('Ymd').'-'.str_pad((string) $tableSession->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            // Mã tạm — cột code là VARCHAR(30), uuid 36 ký tự không vừa nên
            // cắt bớt, chỉ cần duy nhất trong khoảnh khắc trước khi configure()
            // ở trên gán lại mã thật.
            'code' => substr($uuid, 0, 30),
            'shift_id' => Shift::factory(),
            'guest_count' => fake()->numberBetween(1, 10),
            'status' => TableSessionStatus::Open,
            'opened_by_user_id' => User::factory(),
            'opened_at' => now(),
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'discount_reason' => null,
            'total_amount' => 0,
            'paid_amount' => 0,
            'bill_no' => null,
            'bill_printed_at' => null,
            'provisional_printed_at' => null,
            'provisional_print_count' => 0,
            'closed_by_user_id' => null,
            'closed_at' => null,
            'voided_by_user_id' => null,
            'voided_at' => null,
            'void_reason' => null,
            'note' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TableSessionStatus::Open,
            'closed_at' => null,
            'closed_by_user_id' => null,
        ]);
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TableSessionStatus::Billing,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TableSessionStatus::Closed,
            'closed_at' => now(),
            'closed_by_user_id' => User::factory(),
        ]);
    }

    public function withAmount(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal_amount' => $amount,
            'total_amount' => $amount,
        ]);
    }

    public function withTable(): static
    {
        return $this->afterCreating(function (TableSession $session) {
            TableSessionTable::factory()
                ->for($session)
                ->create();
        });
    }
}
