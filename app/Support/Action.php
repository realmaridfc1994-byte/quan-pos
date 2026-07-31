<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lớp đánh dấu cho mọi Action nghiệp vụ. Không khai báo handle() ở đây vì
 * tham số đầu vào (DTO) và kiểu trả về khác nhau giữa các Action — ép chung
 * một chữ ký sẽ làm mất kiểu dữ liệu cụ thể. Mỗi Action con tự khai báo
 * đúng một public method handle() theo quy tắc ở CLAUDE.md mục 4.2.
 */
abstract class Action {}
