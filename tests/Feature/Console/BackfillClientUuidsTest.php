<?php

declare(strict_types=1);

/**
 * Lệnh `pos:backfill-uuid` (app/Console/Commands/BackfillClientUuids.php) đã
 * dùng thật một lần để gán uuid cho dữ liệu cũ trước khi Phase 2 Bước 2 siết
 * ba cột `uuid` (table_sessions, order_items, order_item_options) thành
 * NOT NULL (migration 2026_08_04_000001_make_client_uuid_not_null).
 *
 * Hai test trước đây ở file này giả lập "dữ liệu cũ thiếu uuid" bằng cách tự
 * `DB::table(...)->update(['uuid' => null])` — cách này không còn hợp lệ vì
 * cột đã NOT NULL, chính câu UPDATE đó bị MySQL từ chối. Không còn cách nào
 * tạo ra được một dòng uuid rỗng trong môi trường đã siết constraint, nên
 * không còn kịch bản nào để kiểm thử lệnh này nữa — xoá hai test, GIỮ NGUYÊN
 * lệnh `pos:backfill-uuid` (đã hoàn thành nhiệm vụ lịch sử, không cần chạy
 * lại, không xoá code vì vẫn có thể cần tham khảo).
 */
