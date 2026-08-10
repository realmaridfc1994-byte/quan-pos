<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 4 — bảng 20 trong docs/schema.md K.5: SỔ CÁI KHO.
 *
 * Chỉ ghi thêm, không bao giờ sửa hay xoá — không có cột updated_at.
 * Chỉ RecordStockMovement được phép ghi vào bảng này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ingredient_id');

            $table->enum('type', ['purchase', 'sale', 'waste', 'adjust', 'stocktake', 'return', 'close_residual'])
                ->comment('purchase=nhập, sale=bán, waste=hỏng vỡ, adjust=điều chỉnh tay, stocktake=kiểm kê, return=trả NCC, close_residual=chốt số dư khi tồn về 0');

            $table->bigInteger('qty_delta')->comment('Thay đổi số lượng, theo đơn vị gốc. Dương = vào kho, âm = ra kho');
            $table->bigInteger('cost_delta')->comment('Thay đổi trị giá tồn (đồng)');

            $table->bigInteger('qty_after')->comment('Chụp lại tồn ngay sau dòng này, để đối soát nhanh');
            $table->bigInteger('cost_after');

            $table->boolean('has_cost')->default(true)->comment('0 = xuất lúc tồn âm, chưa xác định được giá vốn');

            $table->enum('ref_type', ['order_item', 'purchase_item', 'stock_take_item', 'manual']);
            $table->unsignedBigInteger('ref_id')->nullable();

            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->comment('Bắt buộc với type=adjust (K12)');
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('shift_id')->nullable()->comment('Ca lúc phát sinh, để tra ngược');

            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['ref_type', 'ref_id', 'ingredient_id'], 'uq_stock_movements_ref');

            $table->index(['ingredient_id', 'id'], 'idx_stock_movements_ledger');
            $table->index(['type', 'occurred_at'], 'idx_stock_movements_type_time');
            $table->index(['shift_id', 'occurred_at'], 'idx_stock_movements_shift');

            $table->foreign('ingredient_id', 'fk_stock_movements_ingredient')
                ->references('id')->on('ingredients')->restrictOnDelete();
            $table->foreign('approved_by_user_id', 'fk_stock_movements_approver')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_stock_movements_creator')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('shift_id', 'fk_stock_movements_shift')
                ->references('id')->on('shifts')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT ck_stock_movements_delta CHECK (qty_delta <> 0 OR cost_delta <> 0)');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT ck_stock_movements_purchase CHECK (type <> 'purchase' OR qty_delta > 0)");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT ck_stock_movements_sale CHECK (type <> 'sale' OR qty_delta < 0)");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT ck_stock_movements_waste CHECK (type <> 'waste' OR qty_delta < 0)");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT ck_stock_movements_residual CHECK (type <> 'close_residual' OR qty_delta = 0)");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT ck_stock_movements_ref CHECK (ref_type = 'manual' OR ref_id IS NOT NULL)");
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT ck_stock_movements_adjust CHECK (
                type <> 'adjust'
             OR (approved_by_user_id IS NOT NULL
                 AND reason IS NOT NULL
                 AND CHAR_LENGTH(reason) >= 10)
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT ck_stock_movements_waste_reason CHECK (
                type <> 'waste' OR (reason IS NOT NULL AND CHAR_LENGTH(reason) >= 5)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
