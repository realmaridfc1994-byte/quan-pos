<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GHI CHÚ ĐỐI SOÁT KHO — bảng thứ 16 của nhóm kho, xem docs/schema.md.
 *
 * Vì sao cần bảng này (review Phase 3 mục 8.2-K): lệnh đối soát tìm ra "dòng
 * sổ cái mồ côi" — dòng đã trừ kho nhưng dòng món tương ứng không còn được
 * tính là đã phục vụ. Sổ cái thì KHÔNG BAO GIỜ xoá được (K1), nên một dòng
 * như vậy làm lệnh đối soát báo đỏ mãi mãi, không có cách nào dọn. Người ta
 * quen với màu đỏ rồi sẽ bỏ qua cả lệch thật — đúng thứ đối soát sinh ra để bắt.
 *
 * Bảng này là đường thoát: chủ quán XEM XONG một dòng mồ côi và xác nhận
 * "biết rồi, không phải lỗi", có ghi ai xác nhận và vì sao. Dòng đó vẫn hiện
 * trong bản đối soát nhưng thôi tính vào số lỗi.
 *
 * Đây KHÔNG phải cách xoá dòng sổ cái. Sổ cái không đổi một chữ nào; chỉ có
 * thêm một tờ giấy dán bên cạnh nói "chỗ này đã xem".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reconciliation_notes', function (Blueprint $table) {
            $table->id();

            // UNIQUE: một dòng sổ cái chỉ ghi chú đúng một lần. Xác nhận lại
            // lần hai không có nghĩa gì — nó đã thôi báo đỏ từ lần đầu rồi.
            $table->foreignId('stock_movement_id')
                ->comment('Dòng sổ cái mồ côi được xác nhận')
                ->constrained('stock_movements')->restrictOnDelete();
            $table->unique('stock_movement_id', 'uq_srn_one_note_per_movement');

            $table->string('note', 255)->comment('Ghi chú của người xác nhận');
            $table->string('reason', 255)->comment('Vì sao dòng này không phải lỗi sổ sách');

            $table->foreignId('acknowledged_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('acknowledged_at');

            $table->timestamps();
        });

        // Xác nhận mà không nói rõ vì sao thì vô giá trị — ba tháng sau không
        // ai nhớ nổi dòng đó đã được tha vì lý do gì. Cùng tinh thần với luật
        // "huỷ phải đủ ai/khi nào/vì sao" ở CLAUDE.md mục 13.
        DB::statement(<<<'SQL'
            ALTER TABLE stock_reconciliation_notes ADD CONSTRAINT ck_srn_reason CHECK (
                CHAR_LENGTH(TRIM(reason)) >= 5 AND CHAR_LENGTH(TRIM(note)) >= 5
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reconciliation_notes');
    }
};
