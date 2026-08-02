<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * Đúng thứ tự y hệt OpenShift::handle() thật: ghi trước bằng mã tạm duy
     * nhất (uuid rút gọn), có id thật rồi mới gán mã thật theo định dạng
     * CA-{Ymd}-{id} — xem OpenShift::sinhMaCa(). Không dùng numerify() sinh
     * số ngẫu nhiên rời rạc như trước, vì mã đó không phải mã thật máy sẽ
     * sinh ra, gây hiểu nhầm khi tra bằng tinker.
     *
     * CHỈ tự sinh khi `code` vẫn còn nguyên dạng mã tạm (30 ký tự đầu của một
     * uuid) — nếu người gọi factory tự truyền `code` riêng, giữ nguyên giá trị đó.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Shift $shift) {
            if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{6}$/i', (string) $shift->code)) {
                return;
            }

            $shift->update([
                'code' => 'CA-'.now()->format('Ymd').'-'.str_pad((string) $shift->id, 2, '0', STR_PAD_LEFT),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Mã tạm — cột code là VARCHAR(30), uuid 36 ký tự không vừa nên
            // cắt bớt, chỉ cần duy nhất trong khoảnh khắc trước khi configure()
            // ở trên gán lại mã thật.
            'code' => substr((string) Str::uuid(), 0, 30),
            'opened_by_user_id' => User::factory(),
            'opened_at' => now(),
            'opening_cash' => 0,
            'status' => ShiftStatus::Open,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShiftStatus::Open,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'counted_cash' => null,
            'expected_cash' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShiftStatus::Closed,
            'closed_at' => now(),
            'closed_by_user_id' => User::factory(),
            'counted_cash' => 0,
            'expected_cash' => 0,
        ]);
    }
}
