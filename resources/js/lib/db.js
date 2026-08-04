import Dexie from 'dexie';

/**
 * Kho dữ liệu trên máy POS (Phase 2 Bước 3) — sống được khi mất mạng.
 *
 * Bảng:
 *  - menu_categories : bản sao thực đơn (nhóm món, lồng sẵn món/biến thể/tuỳ chọn)
 *  - dining_tables   : sơ đồ bàn
 *  - table_sessions  : lượt khách đang mở, biết được lúc offline
 *  - order_items     : dòng món của các lượt khách trên, khoá theo uuid
 *  - queue           : HÀNG CHỜ GỬI — mọi thao tác chưa gửi được lên server
 *  - meta            : con trỏ đồng bộ thực đơn (updated_since) + mã thiết bị
 *
 * uuid là khoá chính cho những bảng ánh xạ đúng bảng bên server (table_sessions,
 * order_items) — cùng uuid máy POS đã sinh khi gọi API thật (xem lib/api.js
 * taoUuid()), để lúc đồng bộ (Bước 4) khớp lại đúng bản ghi, không tạo trùng.
 */
export const db = new Dexie('pos_quan_nhau');

db.version(1).stores({
    menu_categories: 'id, updated_at',
    dining_tables: 'id, code',
    table_sessions: 'uuid, id, status',
    order_items: 'uuid, table_session_uuid',
    queue: '++id, type, created_at, table_session_uuid',
    meta: 'key',
});

const KHOA_META_MENU_UPDATED_SINCE = 'menu_updated_since';
const KHOA_META_DEVICE_ID = 'device_id';

/** Con trỏ đồng bộ thực đơn — lần gần nhất gọi GET /menu?updated_since= thành công. */
export async function layMenuUpdatedSince() {
    const dong = await db.meta.get(KHOA_META_MENU_UPDATED_SINCE);
    return dong?.value ?? null;
}

export async function ghiMenuUpdatedSince(isoString) {
    await db.meta.put({ key: KHOA_META_MENU_UPDATED_SINCE, value: isoString });
}

/**
 * Mã thiết bị riêng cho máy POS này — sinh một lần, lưu vĩnh viễn trong Dexie
 * (không phải localStorage, để cùng một chỗ với toàn bộ kho dữ liệu offline).
 * Dùng để khoá thu tiền offline theo thiết bị (mục 4).
 */
export async function layMaThietBi() {
    const dong = await db.meta.get(KHOA_META_DEVICE_ID);
    if (dong?.value) {
        return dong.value;
    }

    const maMoi = crypto.randomUUID();
    await db.meta.put({ key: KHOA_META_DEVICE_ID, value: maMoi });
    return maMoi;
}

export default db;
