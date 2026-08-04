<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

/**
 * Chín loại xung đột thật sự sinh ra bản ghi `sync_conflicts` —
 * docs/thiet-ke-dong-bo.md mục 5 (dòng 3 không phải xung đột, không có kind).
 */
enum ConflictKind: string
{
    case MonDaHet = 'mon_da_het'; // dòng 1 — hoãn, xem docs/viec-ton.md
    case HaiMayMoBan = 'hai_may_mo_ban'; // dòng 2
    case ThuTienTrung = 'thu_tien_trung'; // dòng 4
    case ThuVuotGiamGia = 'thu_vuot_giam_gia'; // dòng 5
    case LuotDaDong = 'luot_da_dong'; // dòng 6
    case CaDaDong = 'ca_da_dong'; // dòng 7
    case GiaLech = 'gia_lech'; // dòng 8
    case LuotDaHuy = 'luot_da_huy'; // dòng 9
    case ThieuThaoTacGoc = 'thieu_thao_tac_goc'; // dòng 10
}
