<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Staffing\Models\User;
use App\Http\Middleware\EnsureIdempotencyKey;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Bảng danh sách kiểm tra Phase 0 — chạy trước khi bắt đầu Phase 1.
 * Mỗi mục là một điều kiện đã chốt trong docs/schema.md, không phải suy đoán.
 */
final class Phase0Check extends Command
{
    protected $signature = 'phase0:check';

    protected $description = 'Kiểm tra toàn bộ điều kiện Phase 0 và in báo cáo tiếng Việt';

    /** @var list<string> */
    private array $mucConChuaXong = [];

    /** Tên 15 bảng nghiệp vụ thật — không tính bảng hạ tầng Laravel (cache, session, activity_log...) */
    private const BANG_NGHIEP_VU = [
        'users', 'shifts', 'cash_movements',
        'dining_tables', 'table_sessions', 'table_session_tables',
        'categories', 'products', 'product_variants', 'option_groups', 'options',
        'orders', 'order_items', 'order_item_options',
        'payments',
    ];

    /** Chỉ mục CHẶN LỖI — nếu thiếu một cái là có thể xảy ra lỗi nghiệp vụ nghiêm trọng */
    private const CHI_MUC_CHAN_LOI = [
        'table_session_tables' => ['uq_tst_one_session_per_table'],
        'orders' => ['uq_orders_uuid', 'uq_orders_session_seq_station'],
        'payments' => ['uq_payments_uuid'],
        'shifts' => ['uq_shifts_only_one_open'],
        'table_sessions' => ['uq_table_sessions_bill_no'],
        'product_variants' => ['uq_variants_product_name'],
        'options' => ['uq_options_group_name'],
        'users' => ['uq_users_username'],
        'dining_tables' => ['uq_dining_tables_code'],
        'products' => ['uq_products_code'],
        'categories' => ['uq_categories_name'],
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('=== PHASE 0 — KIỂM TRA HỆ THỐNG ===');
        $this->newLine();

        $this->kiemTraKetNoiDatabase();
        $this->kiemTraCacheVaKhoa();
        $this->kiemTraCharset();
        $this->kiemTraSoBang();
        $this->kiemTraChiMuc();
        $this->kiemTraUsers();
        $this->kiemTraBan();
        $this->kiemTraThucDon();
        $this->kiemTraMiddlewareIdempotency();
        $this->kiemTraMoney();
        $this->kiemTraActivityLog();
        $this->kiemTraTestSuite();

        $this->newLine();
        if ($this->mucConChuaXong === []) {
            $this->line('<fg=green>PHASE 0 HOÀN TẤT ✅</>');

            return CommandAlias::SUCCESS;
        }

        $this->line('<fg=red>CÒN '.count($this->mucConChuaXong).' MỤC CHƯA XONG ❌</>');
        foreach ($this->mucConChuaXong as $muc) {
            $this->line("  - {$muc}");
        }

        return CommandAlias::FAILURE;
    }

    private function baoOk(string $noiDung): void
    {
        $this->line("✅ {$noiDung}");
    }

    private function baoFail(string $noiDung): void
    {
        $this->line("❌ {$noiDung}");
        $this->mucConChuaXong[] = $noiDung;
    }

    private function kiemTraKetNoiDatabase(): void
    {
        try {
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $phienBan = DB::selectOne('select version() as v')->v;
            $this->baoOk("Kết nối được database ở {$host}:{$port} — phiên bản: {$phienBan}");
        } catch (\Throwable $e) {
            $this->baoFail('Không kết nối được database: '.$e->getMessage());
        }
    }

    private function kiemTraCacheVaKhoa(): void
    {
        $driver = config('cache.default');

        try {
            $lock = Cache::lock('phase0-check-lock', 10);
            if (! $lock->get()) {
                $this->baoFail("Không lấy được khoá thử (cache driver: {$driver}).");

                return;
            }
            $lock->release();

            if ($driver === 'database') {
                $this->baoOk('Cache và khoá (driver database) hoạt động bình thường.');
            } else {
                $this->baoFail(
                    "Cache và khoá đang chạy được, nhưng driver hiện tại là '{$driver}', không phải 'database'. ".
                    'Muốn dùng khoá phân tán đúng chuẩn Phase 0 thì cần đổi .env sang CACHE_STORE=database — hỏi chủ dự án trước khi đổi.'
                );
            }
        } catch (\Throwable $e) {
            $this->baoFail('Cache/khoá lỗi: '.$e->getMessage());
        }
    }

    private function kiemTraCharset(): void
    {
        $hang = DB::selectOne(
            'select DEFAULT_CHARACTER_SET_NAME as bang_ma, DEFAULT_COLLATION_NAME as doi_chieu
             from information_schema.SCHEMATA where SCHEMA_NAME = DATABASE()'
        );

        if ($hang === null) {
            $this->baoFail('Không đọc được bảng mã database.');

            return;
        }

        $dungBangMa = str_starts_with((string) $hang->bang_ma, 'utf8mb4');
        $dungDoiChieu = str_starts_with((string) $hang->doi_chieu, 'utf8mb4');

        if ($dungBangMa && $dungDoiChieu) {
            $this->baoOk("Bảng mã database đúng chuẩn: {$hang->bang_ma} / {$hang->doi_chieu}");
        } else {
            $this->baoFail("Bảng mã database sai chuẩn: {$hang->bang_ma} / {$hang->doi_chieu} (cần utf8mb4 / utf8mb4_unicode_ci)");
        }
    }

    private function kiemTraSoBang(): void
    {
        $tenBangThat = DB::table('information_schema.tables')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->whereIn('table_name', self::BANG_NGHIEP_VU)
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $soLuong = count($tenBangThat);
        $soCanCo = count(self::BANG_NGHIEP_VU);

        if ($soLuong === $soCanCo) {
            $this->baoOk("Đủ {$soCanCo} bảng nghiệp vụ: ".implode(', ', $tenBangThat));
        } else {
            $thieu = array_diff(self::BANG_NGHIEP_VU, $tenBangThat);
            $this->baoFail("Chỉ có {$soLuong}/{$soCanCo} bảng nghiệp vụ. Thiếu: ".implode(', ', $thieu));
        }
    }

    private function kiemTraChiMuc(): void
    {
        $thieu = [];

        foreach (self::CHI_MUC_CHAN_LOI as $bang => $danhSachChiMuc) {
            $chiMucThat = DB::table('information_schema.statistics')
                ->where('table_schema', DB::raw('DATABASE()'))
                ->where('table_name', $bang)
                ->distinct()
                ->pluck('index_name')
                ->all();

            foreach ($danhSachChiMuc as $chiMuc) {
                if (! in_array($chiMuc, $chiMucThat, true)) {
                    $thieu[] = "{$bang}.{$chiMuc}";
                }
            }
        }

        $tongSo = array_sum(array_map('count', self::CHI_MUC_CHAN_LOI));

        if ($thieu === []) {
            $this->baoOk("Đủ {$tongSo} chỉ mục chặn lỗi bắt buộc.");
        } else {
            $this->baoFail('Thiếu chỉ mục chặn lỗi: '.implode(', ', $thieu));
        }
    }

    private function kiemTraUsers(): void
    {
        $soRole = User::query()->distinct()->count('role');

        if ($soRole >= 4) {
            $this->baoOk("Đủ {$soRole} vai trò người dùng khác nhau.");
        } else {
            $this->baoFail("Chỉ có {$soRole}/4 vai trò người dùng. Chạy `php artisan db:seed` để nạp dữ liệu mẫu.");
        }
    }

    private function kiemTraBan(): void
    {
        $soBan = DiningTable::query()->count();

        if ($soBan >= 12) {
            $this->baoOk("Đủ {$soBan} bàn.");
        } else {
            $this->baoFail("Chỉ có {$soBan}/12 bàn. Chạy `php artisan db:seed` để nạp dữ liệu mẫu.");
        }
    }

    private function kiemTraThucDon(): void
    {
        $soMon = Product::query()->count();
        $soMonCoBienThe = Product::query()->has('variants', '>=', 2)->count();
        $soMonCoTuyChon = Product::query()->has('optionGroups')->count();

        $moTa = "{$soMon} món, {$soMonCoBienThe} món có nhiều biến thể, {$soMonCoTuyChon} món có tùy chọn";

        if ($soMon >= 60 && $soMonCoBienThe >= 1 && $soMonCoTuyChon >= 10) {
            $this->baoOk("Thực đơn đủ: {$moTa}.");
        } else {
            $this->baoFail("Thực đơn chưa đủ: {$moTa} (cần ≥60 món, ≥1 món nhiều biến thể, ≥10 món có tùy chọn).");
        }
    }

    private function kiemTraMiddlewareIdempotency(): void
    {
        $alias = Route::getMiddleware()['idempotent'] ?? null;

        if ($alias === EnsureIdempotencyKey::class) {
            $this->baoOk("Middleware Idempotency đã đăng ký với alias 'idempotent'.");
        } else {
            $this->baoFail("Middleware Idempotency chưa đăng ký alias 'idempotent' trong bootstrap/app.php.");
        }
    }

    private function kiemTraMoney(): void
    {
        try {
            $tong = Money::fromInt(100_000)->plus(Money::fromInt(50_000));
            if ($tong->amount === 150_000) {
                $this->baoOk("Class Money hoạt động: 100.000 đ + 50.000 đ = {$tong->format()}");
            } else {
                $this->baoFail("Class Money tính sai: kết quả {$tong->amount}, phải là 150000.");
            }
        } catch (\Throwable $e) {
            $this->baoFail('Class Money lỗi: '.$e->getMessage());
        }
    }

    private function kiemTraActivityLog(): void
    {
        try {
            $soLuongTruoc = Activity::query()->count();

            activity('phase0-check')
                ->withProperties(['nguon' => 'phase0:check'])
                ->log('Bản ghi thử để kiểm tra activity log — an toàn xoá.');

            $banGhiMoi = Activity::query()->where('log_name', 'phase0-check')->latest('id')->first();

            if ($banGhiMoi !== null && Activity::query()->count() === $soLuongTruoc + 1) {
                $this->baoOk("Activitylog đang ghi bình thường (bản ghi mới id={$banGhiMoi->id}).");
            } else {
                $this->baoFail('Activitylog không ghi được bản ghi thử.');
            }
        } catch (\Throwable $e) {
            $this->baoFail('Activitylog lỗi: '.$e->getMessage());
        }
    }

    private function kiemTraTestSuite(): void
    {
        $this->newLine();
        $this->line('<fg=yellow>Đang chạy toàn bộ test suite (Pest)... việc này sẽ XOÁ SẠCH VÀ NẠP LẠI schema database vì test dùng RefreshDatabase.</>');
        $this->line('<fg=yellow>Sau khi chạy xong, nhớ chạy `php artisan db:seed` để có lại dữ liệu mẫu.</>');

        $ketQua = Process::timeout(600)->run(base_path('vendor/bin/pest').' --colors=never');

        $output = $ketQua->output().$ketQua->errorOutput();

        if (preg_match('/Tests:\s*(.+)/', $output, $m)) {
            $dongTomTat = trim($m[1]);
        } else {
            $dongTomTat = 'không đọc được dòng tóm tắt — xem chi tiết bên dưới';
        }

        if ($ketQua->successful()) {
            $this->baoOk("Toàn bộ test suite PASS. {$dongTomTat}");
        } else {
            $this->baoFail("Test suite có test FAIL. {$dongTomTat}");
            $this->line($output);
        }
    }
}
