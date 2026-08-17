<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/schema.md PHẦN O, bảng 23 — CẤU HÌNH CỦA QUÁN (Phase 5 Bước 5A.1).
 *
 * Trước bước này, ba ngưỡng chủ quán chỉnh được nằm nhờ trong bảng `cache` —
 * nghĩa là một lệnh `php artisan cache:clear` đưa cả ba về mặc định mà không
 * ai thấy. Ngưỡng hao hụt cần PIN nằm trong số đó: mất nó là NỚI LỎNG một lá
 * chắn trong im lặng.
 *
 * Bảng này là chỗ ở thật của chúng, cộng thêm dòng `ma_quan` — thứ mà bước
 * 5A.4 (khoá cache), 5A.5 (đường dẫn trên tem QR) và 5A.6 (máy tính bảng) đều
 * cần tới.
 *
 * Hai quyết định trong hình dạng bảng:
 *
 *  - `gia_tri` cho phép NULL, và NULL có nghĩa "đang dùng giá trị khởi đầu
 *    trong config/pos.php". Nhờ vậy nút "đặt lại mặc định" KHÔNG phải xoá
 *    dòng — sổ của quán không có cục tẩy, kể cả sổ cấu hình.
 *  - `kieu_du_lieu` để lúc đọc ra biết ép về số nguyên hay ngày hay chuỗi.
 *    Không có nó thì mỗi chỗ đọc phải tự nhớ, và chỗ nào quên thì so sánh
 *    chuỗi "200000" với số 200000 rồi ra kết quả sai một cách khó tìm.
 *
 * Ràng buộc CHECK ở cuối là luật cứng, không phải trang trí: dòng `ma_quan`
 * không bao giờ được để trống. Mã quán không có đường "rơi về mặc định" —
 * nó đã in lên tem giấy và nằm trong máy tính bảng, đoán bừa một giá trị
 * khác là làm mồ côi cả hai thứ đó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cau_hinh_quan', function (Blueprint $table) {
            $table->id();

            $table->string('khoa', 64)->comment('Tên khoá cấu hình, ví dụ ma_quan');
            $table->text('gia_tri')->nullable()
                ->comment('NULL = chưa ai chỉnh, đang dùng giá trị khởi đầu trong config/pos.php');
            $table->enum('kieu_du_lieu', ['chuoi', 'so_nguyen', 'ngay'])
                ->comment('Ép kiểu khi đọc ra');
            $table->string('mo_ta', 255)->nullable()
                ->comment('Một câu tiếng Việt cho người đọc bảng bằng tay');

            $table->timestamps();

            $table->unique('khoa', 'uq_cau_hinh_quan_khoa');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cau_hinh_quan ADD CONSTRAINT ck_cau_hinh_quan_ma_quan CHECK (
                khoa <> 'ma_quan' OR (gia_tri IS NOT NULL AND gia_tri <> '')
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cau_hinh_quan');
    }
};
