<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Hai ngưỡng chủ quán tự chỉnh được — Phase 3 Bước 6/8
    |--------------------------------------------------------------------------
    |
    | Đây chỉ là GIÁ TRỊ KHỞI ĐẦU. Chủ quán chỉnh lại trong Filament (màn hình
    | "Ngưỡng cảnh báo"), giá trị mới lưu vào kho cấu hình và đè lên hai số
    | dưới đây — xem App\Support\CauHinhQuan.
    |
    | Vì sao 25% chứ không phải 15%: bia lãi 20-30%, món nấu lãi 50-70%. Đặt
    | ngưỡng 15% thì gần như không món nào rơi vào, mà một cảnh báo không bao
    | giờ kêu là một cảnh báo vô dụng. 25% cắt đúng vào nhóm đồ uống lãi mỏng
    | — nhóm cần nhìn kỹ nhất. Con số này là điểm khởi đầu, không phải chân
    | lý: chủ quán nhìn dữ liệu thật vài tuần rồi tự kéo lên hoặc xuống.
    |
    | Vì sao ngưỡng hao hụt tính theo TIỀN chứ không theo số lượng: 5 lon bia
    | và 5 con cua hoàng đế đều là "5", nhưng một bên vài chục nghìn, một bên
    | vài triệu. Ngưỡng theo tiền mới phản ánh đúng mức độ nhạy cảm.
    |
    | 200.000đ mỗi lần: dưới mức đó thu ngân tự ghi (bắt PIN mọi lần thì nhân
    | viên ngừng ghi, mà hao hụt không ghi còn tệ hơn — nó biến thành chênh
    | lệch kiểm kê không rõ nguyên nhân ba tháng sau). Vượt mức đó phải có
    | chủ quán duyệt bằng PIN.
    */

    'nguong_ti_le_lai_thap_phan_tram' => 25,

    'nguong_hao_hut_can_pin' => 200_000,

    /*
    |--------------------------------------------------------------------------
    | Mốc ngày đã kiểm phần thiếu giá vốn — Phase 3 Bước 10
    |--------------------------------------------------------------------------
    |
    | Hai cột đếm phần thiếu giá vốn (qty_no_cost, qty_not_served) chỉ có số
    | từ ngày chúng ra đời — 10/08/2026. Mọi dòng tổng hợp chốt TRƯỚC ngày đó
    | mang số 0 không phải vì sạch, mà vì chưa ai đếm.
    |
    | Đây là dạng số liệu sai nguy hiểm nhất: cảnh báo IM LẶNG ở đúng chỗ đáng
    | ra phải kêu. Chủ quán nhìn tháng 8 có cảnh báo, tháng 6-7 sạch, rồi kết
    | luận nhầm "tháng trước không có vấn đề gì".
    |
    | Nên màn hình lãi gộp nói thẳng: số liệu trước mốc này CHƯA được kiểm.
    | Chạy `php artisan report:summarize --tu=... --den=...` cho khoảng ngày cũ
    | thì lệnh tự hạ mốc xuống, câu cảnh báo tự tắt cho phần đã kiểm.
    |
    | Giá trị này chỉ là mốc KHỞI ĐẦU (giống hai ngưỡng trên): mốc thật lưu
    | trong kho cấu hình dùng chung — xem App\Support\CauHinhQuan.
    */

    'moc_ngay_kiem_thieu_gia_von' => '2026-08-10',
];
