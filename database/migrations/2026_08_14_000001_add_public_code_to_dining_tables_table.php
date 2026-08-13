<?php

declare(strict_types=1);

use App\Support\MaBanCongKhai;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/schema.md bảng 4 (dining_tables) — Phase 4, khách quét QR tự gọi món.
 *
 * `code` sẵn có (B01, VIP1) KHÔNG dùng làm mã QR được: đoán được ngay từ bàn
 * thứ nhất. Thêm `public_code` — 22 ký tự ngẫu nhiên, không suy ra được từ
 * `id` tuần tự cũng không suy ra được từ `code`.
 *
 * Đây là mã CÔNG KHAI chứ không phải bí mật: nó chỉ nói "đây là bàn nào".
 * Muốn gọi món phải đổi nó lấy token phiên ngắn hạn, và chỉ đổi được khi bàn
 * đang có khách ngồi. Xem App\Support\MaBanCongKhai.
 *
 * Điền giá trị cho các bàn đang có là BẮT BUỘC KỸ THUẬT, không phải backfill
 * nghiệp vụ: không thể thêm một cột NOT NULL UNIQUE vào bảng đã có dòng mà
 * bỏ trống. Mỗi bàn nhận một mã riêng, sinh bằng đúng hàm mà Factory/Seeder
 * dùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->char('public_code', MaBanCongKhai::DO_DAI)
                ->charset('ascii')->collation('ascii_bin')
                ->nullable()
                ->after('code')
                ->comment('Mã định danh bàn in trong mã QR — CÔNG KHAI, không phải bí mật');
        });

        foreach (DB::table('dining_tables')->pluck('id') as $id) {
            DB::table('dining_tables')->where('id', $id)->update(['public_code' => MaBanCongKhai::sinh()]);
        }

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->char('public_code', MaBanCongKhai::DO_DAI)
                ->charset('ascii')->collation('ascii_bin')
                ->nullable(false)
                ->comment('Mã định danh bàn in trong mã QR — CÔNG KHAI, không phải bí mật')
                ->change();

            $table->unique('public_code', 'uq_dining_tables_public_code');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropUnique('uq_dining_tables_public_code');
            $table->dropColumn('public_code');
        });
    }
};
