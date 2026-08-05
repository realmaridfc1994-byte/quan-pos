<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

/**
 * Mười một loại xung đột thật sự sinh ra bản ghi `sync_conflicts` —
 * docs/thiet-ke-dong-bo.md mục 5 (dòng 3 không phải xung đột, không có kind).
 *
 * Dòng 4/5 gốc của thiết kế ("hai máy cùng thu tiền" / "thu offline, giảm
 * giá online") tách thành BỐN loại (sửa 05/08 sau kiểm toán — SyncBatch cũ
 * suy đoán nguyên nhân chỉ bằng `paid_amount > 0`, sai trong nhiều tình
 * huống thật: thu MỘT PHẦN ở hai nơi khác `paid_amount`, hoặc tổng đổi vì
 * HUỶ MÓN chứ không phải giảm giá — xem SyncBatch::phanLoaiThuVuot()):
 *   - ThuTienTrung: đã có phiếu thu `completed`, số tiền offline TRÙNG đúng
 *     số đã thu — nghi thu lại đúng khoản đó.
 *   - ThuMotPhanVuot: đã có phiếu thu `completed` nhưng số tiền KHÁC — thu
 *     một phần ở hai nơi, cộng lại vượt tổng còn thiếu.
 *   - ThuVuotGiamGia: chưa có phiếu thu nào, `discount_amount > 0` — tổng
 *     giảm vì giảm giá thật.
 *   - ThuVuotTongDoiKhac: chưa có phiếu thu nào, không có giảm giá — tổng
 *     đổi vì lý do khác (thường là huỷ món), không suy đoán bừa.
 */
enum ConflictKind: string
{
    case MonDaHet = 'mon_da_het'; // dòng 1 — hoãn, xem docs/viec-ton.md
    case HaiMayMoBan = 'hai_may_mo_ban'; // dòng 2
    case ThuTienTrung = 'thu_tien_trung'; // dòng 4a
    case ThuMotPhanVuot = 'thu_mot_phan_vuot'; // dòng 4b
    case ThuVuotGiamGia = 'thu_vuot_giam_gia'; // dòng 5a
    case ThuVuotTongDoiKhac = 'thu_vuot_tong_doi_khac'; // dòng 5b
    case LuotDaDong = 'luot_da_dong'; // dòng 6
    case CaDaDong = 'ca_da_dong'; // dòng 7
    case GiaLech = 'gia_lech'; // dòng 8
    case LuotDaHuy = 'luot_da_huy'; // dòng 9
    case ThieuThaoTacGoc = 'thieu_thao_tac_goc'; // dòng 10
}
