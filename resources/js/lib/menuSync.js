import api from './api';
import db, { layMenuUpdatedSince, ghiMenuUpdatedSince } from './db';

/**
 * Đồng bộ thực đơn mỗi khi có mạng (Phase 2 Bước 3 mục 2) — máy POS phải
 * luôn có bản thực đơn mới nhất TRƯỚC KHI mất mạng, vì lúc mất mạng máy chỉ
 * đọc lại đúng những gì đã có sẵn trong Dexie.
 *
 * Con trỏ đồng bộ lấy từ `updated_at` LỚN NHẤT trong chính dữ liệu vừa nhận
 * về, KHÔNG dùng giờ máy khách (`Date.now()`) — đồng hồ máy khách có thể
 * lệch với đồng hồ server, dùng giờ máy khách làm mốc có thể bỏ sót một
 * thay đổi thực đơn xảy ra đúng lúc đang đồng bộ.
 */
export async function dongBoThucDonNeuCoMang() {
    if (!navigator.onLine) {
        return false;
    }

    const updatedSince = await layMenuUpdatedSince();
    const { data } = await api.get('/menu', {
        params: updatedSince ? { updated_since: updatedSince } : {},
    });

    const cacNhom = data.data ?? [];
    if (cacNhom.length > 0) {
        await db.menu_categories.bulkPut(cacNhom);

        const moiNhatTrongDot = cacNhom.reduce(
            (moiNhat, nhom) => (nhom.updated_at > moiNhat ? nhom.updated_at : moiNhat),
            cacNhom[0].updated_at,
        );

        if (!updatedSince || moiNhatTrongDot > updatedSince) {
            await ghiMenuUpdatedSince(moiNhatTrongDot);
        }
    }

    return true;
}

/** Đọc thực đơn để hiển thị — luôn đọc từ Dexie, có mạng hay không cũng vậy. */
export async function docThucDonTuKho() {
    return db.menu_categories.toArray();
}
