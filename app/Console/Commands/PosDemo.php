<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Staffing\Actions\OpenShift;
use App\Domain\Staffing\DTO\OpenShiftData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Diễn tập một ca bán hàng thật, in tiếng Việt từng mốc, để chủ quán tự mắt
 * thấy hệ thống chạy đúng mà không cần đọc code. Mỗi mốc in ra CON SỐ tự
 * kiểm được (mã ca, số tiền...), không chỉ in "thành công".
 *
 * Toàn bộ dữ liệu demo tạo ra được bọc trong một transaction và LUÔN rollback ở
 * cuối cùng (kể cả khi lỗi giữa chừng) — không có lệnh xoá nào cả, vì quy tắc
 * của dự án là không bao giờ xoá cứng dữ liệu giao dịch (shifts, ...).
 */
final class PosDemo extends Command
{
    protected $signature = 'pos:demo {--den=ca : Mốc dừng lại — hiện chỉ hỗ trợ "ca"}';

    protected $description = 'Diễn tập một ca bán hàng mẫu, dùng để tự kiểm tra bằng mắt (chỉ chạy ở môi trường local)';

    /** Tên mốc hiện tại — dùng để in "DỪNG Ở MỐC" đúng chỗ khi có lỗi. */
    private string $mocHienTai = '';

    public function handle(OpenShift $moCa): int
    {
        if (! app()->environment('local')) {
            $this->line('<fg=red>❌ Lệnh này chỉ chạy ở môi trường local, môi trường hiện tại là "'.app()->environment().'".</>');

            return CommandAlias::FAILURE;
        }

        $den = $this->option('den');

        if ($den !== 'ca') {
            $this->line("<fg=red>❌ Chưa hỗ trợ --den={$den}. Bước này chỉ hỗ trợ --den=ca.</>");

            return CommandAlias::FAILURE;
        }

        $this->newLine();

        DB::beginTransaction();

        try {
            $this->dienTapMoCa($moCa);

            $ketQua = CommandAlias::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->line("<fg=red>❌ DỪNG Ở MỐC: {$this->mocHienTai}</>");
            $this->line('<fg=red>   Lý do: '.$e->getMessage().'</>');
            $ketQua = CommandAlias::FAILURE;
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        if ($ketQua === CommandAlias::SUCCESS) {
            $this->line('<fg=green;options=bold>✅ TOÀN BỘ LƯỢT BÁN CHẠY ĐÚNG</>');
        }
        $this->line('<fg=yellow>Đã dọn sạch toàn bộ dữ liệu diễn tập (rollback, không có gì được ghi thật vào database).</>');

        return $ketQua;
    }

    private function dienTapMoCa(OpenShift $moCa): void
    {
        $this->mocHienTai = 'MỞ CA';
        $this->line('<fg=cyan;options=bold>MỞ CA</>');

        $thuNgan = User::factory()->create([
            'name' => 'Thu ngân diễn tập',
            'role' => UserRole::Cashier,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $tienDauCa = Money::fromInt(500_000);

        $ca = $moCa->handle(new OpenShiftData(
            openingCash: $tienDauCa,
            openedByUserId: $thuNgan->id,
        ));

        $this->line("   Ca {$ca->code} mở bởi {$thuNgan->name}, tiền lẻ đầu ca {$tienDauCa->format()}");
    }
}
