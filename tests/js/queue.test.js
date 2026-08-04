import 'fake-indexeddb/auto';
import Dexie from 'dexie';
import { beforeEach, describe, expect, it } from 'vitest';
import { HangCho } from '../../resources/js/lib/queue.js';

/**
 * Test đơn vị cho lớp hàng chờ (Phase 2 Bước 3) — dùng fake-indexeddb để
 * Dexie chạy được trong Node, không cần trình duyệt thật. Test end-to-end
 * thật (rút mạng bằng công cụ giả lập của trình duyệt) chưa có ở bước này —
 * xem docs/viec-ton.md.
 */

function taoDbTest() {
    const db = new Dexie(`test_${crypto.randomUUID()}`);
    db.version(1).stores({ queue: '++id, type, created_at, table_session_uuid' });
    return db;
}

describe('HangCho', () => {
    let db;
    let hangCho;

    beforeEach(() => {
        db = taoDbTest();
        hangCho = new HangCho(db.queue);
    });

    it('thêm việc vào hàng chờ thì đếm ra đúng số lượng', async () => {
        await hangCho.themVaoHangCho({ type: 'mo-ban', payload: { uuid: 'a' } });
        await hangCho.themVaoHangCho({ type: 'goi-mon', payload: { uuid: 'b' } });

        expect(await hangCho.demSoViecDangCho()).toBe(2);
    });

    it('danh sách đang chờ trả về đúng thứ tự đã thêm vào', async () => {
        await hangCho.themVaoHangCho({ type: 'mo-ban', payload: { uuid: 'a' } });
        await hangCho.themVaoHangCho({ type: 'goi-mon', payload: { uuid: 'b' } });
        await hangCho.themVaoHangCho({ type: 'thu-tien-mat', payload: { uuid: 'c' } });

        const danhSach = await hangCho.danhSachDangCho();

        expect(danhSach.map((v) => v.type)).toEqual(['mo-ban', 'goi-mon', 'thu-tien-mat']);
    });

    it('xoá khỏi hàng chờ thì không còn đếm nữa', async () => {
        const id = await hangCho.themVaoHangCho({ type: 'mo-ban', payload: { uuid: 'a' } });

        await hangCho.xoaKhoiHangCho(id);

        expect(await hangCho.demSoViecDangCho()).toBe(0);
    });

    it('mục 4: phát hiện thu tiền offline từ máy khác cho cùng lượt khách', async () => {
        await hangCho.themVaoHangCho({
            type: 'thu-tien-mat',
            payload: {},
            tableSessionUuid: 'luot-1',
            deviceId: 'may-A',
        });

        const bi = await hangCho.coThuTienOfflineTuMayKhac('luot-1', 'may-B');
        const khongBi = await hangCho.coThuTienOfflineTuMayKhac('luot-1', 'may-A');

        expect(bi).toBe(true);
        expect(khongBi).toBe(false);
    });

    it('mục 4: hai máy hoàn toàn cô lập (chưa từng thấy việc của nhau) thì không phát hiện được', async () => {
        // Không hề gọi themVaoHangCho() cho lượt khách này ở "máy A" — mô
        // phỏng đúng tình huống hai máy độc lập, mỗi máy chỉ biết hàng chờ
        // của chính nó. hangCho ở đây chỉ thấy đúng những gì nó tự ghi.
        const khongBiet = await hangCho.coThuTienOfflineTuMayKhac('luot-chua-tung-thay', 'may-B');

        expect(khongBiet).toBe(false);
    });

    it('lượt khách khác nhau không đụng nhau', async () => {
        await hangCho.themVaoHangCho({
            type: 'thu-tien-mat',
            payload: {},
            tableSessionUuid: 'luot-1',
            deviceId: 'may-A',
        });

        const luotKhac = await hangCho.coThuTienOfflineTuMayKhac('luot-2', 'may-B');

        expect(luotKhac).toBe(false);
    });
});
