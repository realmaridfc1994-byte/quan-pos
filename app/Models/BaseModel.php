<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model gốc của toàn dự án. Nó chỉ làm đúng MỘT việc và không được phép làm
 * thêm việc thứ hai: chốt rằng mọi bảng nghiệp vụ của quán nằm trên kết nối
 * mang tên `tenant`, không phải kết nối mặc định.
 *
 * Vì sao cần một chỗ duy nhất thay vì khai `$connection` ở từng Model: chỉ cần
 * một Model quên khai là dữ liệu của bảng đó đi lối khác, và không ai thấy —
 * câu truy vấn vẫn chạy, số vẫn ra, chỉ khác đường đi. Đúng loại lỗi im lặng
 * mà dự án này sợ nhất. Để ở đây thì Model mới viết sau tự động đi đúng đường,
 * không cần ai nhớ.
 *
 * Ngoại lệ duy nhất: `App\Domain\Staffing\Models\User` phải kế thừa
 * `Authenticatable` để chạy được đăng nhập, Sanctum và Filament, nên nó khai
 * thẳng `protected $connection = 'tenant'` trong file của nó.
 */
abstract class BaseModel extends Model
{
    protected $connection = 'tenant';
}
