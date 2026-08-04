/**
 * Lớp HÀNG CHỜ GỬI (Phase 2 Bước 3 mục 1/4) — nơi giữ mọi thao tác chưa gửi
 * được lên server khi máy POS mất mạng. Tách riêng khỏi giao diện và khỏi
 * `db.js` (nhận bảng Dexie qua constructor) để test đơn vị được mà không
 * cần trình duyệt thật.
 *
 * `type` hiện dùng: 'mo-ban' | 'goi-mon' | 'thu-tien-mat'.
 */
export class HangCho {
    constructor(bangQueueDexie) {
        this.bang = bangQueueDexie;
    }

    /**
     * @param {{type: string, payload: object, tableSessionUuid?: string|null, deviceId?: string|null}} viec
     * @returns {Promise<number>} id của dòng vừa thêm trong bảng queue
     */
    async themVaoHangCho(viec) {
        return this.bang.add({
            type: viec.type,
            payload: viec.payload,
            table_session_uuid: viec.tableSessionUuid ?? null,
            device_id: viec.deviceId ?? null,
            created_at: new Date().toISOString(),
        });
    }

    /** Liệt kê theo đúng thứ tự thêm vào — Bước 4 phải gửi lại đúng thứ tự này. */
    async danhSachDangCho() {
        return this.bang.orderBy('created_at').toArray();
    }

    async demSoViecDangCho() {
        return this.bang.count();
    }

    async xoaKhoiHangCho(id) {
        return this.bang.delete(id);
    }

    /**
     * Mục 4: chặn thu tiền offline một lượt khách nếu MÁY NÀY đã thấy một
     * thao tác thu tiền offline khác từ MÁY KHÁC cho đúng lượt khách đó.
     *
     * Đây CHỈ chặn được trường hợp hai máy có nhìn thấy hàng chờ của nhau
     * (ví dụ đồng bộ qua lại trước khi mất mạng, hoặc dùng chung một kho dữ
     * liệu). Hai máy hoàn toàn cô lập với nhau, chưa từng thấy thao tác của
     * nhau, thì lớp này KHÔNG có cách nào biết được — máy nào cũng tưởng
     * mình là máy đầu tiên thu tiền lượt khách đó. Việc phát hiện và xử lý
     * xung đột thật giữa hai máy độc lập là việc của Bước 4 (đồng bộ) khi
     * cả hai thao tác cùng tới server.
     */
    async coThuTienOfflineTuMayKhac(tableSessionUuid, maThietBiHienTai) {
        const cacViecCuaLuot = await this.bang.where('table_session_uuid').equals(tableSessionUuid).toArray();

        return cacViecCuaLuot.some(
            (viec) => viec.type === 'thu-tien-mat' && viec.device_id !== null && viec.device_id !== maThietBiHienTai,
        );
    }
}

export default HangCho;
