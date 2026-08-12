<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Inventory\Enums\StockMovementRefType;
use Ramsey\Uuid\Uuid;

/**
 * Sinh mã vân tay TẤT ĐỊNH cho dòng sổ cái kho — cùng một nguồn gốc thì luôn
 * cho ra cùng một mã, chạy lại bao nhiêu lần cũng vậy.
 *
 * Vì sao cần: ba đường trừ/nhập kho có chứng từ gốc (bán món, nhập hàng, kiểm
 * kê) đã được khoá uq_stock_movements_ref chặn trùng theo bộ ba
 * (ref_type, ref_id, ingredient_id). Mã vân tay ở đây là LỚP THỨ HAI, và nó
 * được sinh từ ĐÚNG bộ ba đó — nên hai lớp luôn nói cùng một câu, không có
 * chuyện lớp này cho qua còn lớp kia từ chối.
 *
 * Ngược lại, hao hụt và điều chỉnh tay KHÔNG có chứng từ gốc (ref_id là rỗng,
 * mà trong MariaDB nhiều dòng cùng rỗng không bị coi là trùng) — hai đường đó
 * phải nhận mã vân tay do MÀN HÌNH sinh và gửi lên, không dùng hàm ở đây.
 *
 * UUID phiên bản 5 là một phép băm: đầu vào giống nhau ra kết quả giống nhau,
 * không có yếu tố ngẫu nhiên hay giờ giấc.
 */
final class StockMovementUuid
{
    /**
     * Vùng tên cố định của dự án. Đổi hằng số này là đổi TOÀN BỘ mã vân tay
     * sinh ra từ trước tới nay — không bao giờ được sửa.
     */
    private const VUNG_TEN = '5b6c0f3a-1d24-4a7e-9c58-4f2b8e6d1a90';

    /** Mã vân tay cho một dòng sổ cái sinh từ chứng từ gốc. */
    public static function tuChungTu(StockMovementRefType $refType, int $refId, int $ingredientId): string
    {
        return self::tuChuoi("{$refType->value}:{$refId}:{$ingredientId}");
    }

    /**
     * Mã vân tay cho dòng sổ cái không có chứng từ gốc nhưng vẫn cần tất định
     * (hiện chỉ dùng cho lệnh diễn tập `pos:demo`).
     */
    public static function tuChuoi(string $nguon): string
    {
        return Uuid::uuid5(self::VUNG_TEN, "stock_movement:{$nguon}")->toString();
    }
}
