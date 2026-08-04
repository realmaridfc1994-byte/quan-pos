/**
 * Trạng thái mạng + luật "việc nào được làm khi mất mạng" (Phase 2 Bước 3
 * mục 3 và 5).
 */

const THONG_BAO_CAN_MANG = 'Cần có mạng để làm việc này. Đang mất kết nối.';

/**
 * Được phép làm khi offline: mở bàn, gọi món, gửi bếp, in tem, thu tiền mặt.
 * KHÔNG được phép: giảm giá, huỷ món đã bưng ra, void bill, mở ca, đóng ca.
 *
 * Bốn việc "không được phép" (trừ giảm giá) hiện CHƯA có nút trên giao diện
 * POS (`pos.js`) — xem docs/viec-ton.md. Hàm này viết sẵn cho khi các nút
 * đó được xây, không tự thêm nút giả để gọi thử.
 */
const HANH_DONG_DUOC_PHEP_OFFLINE = new Set(['mo-ban', 'goi-mon', 'gui-bep', 'in-tem', 'thu-tien-mat']);

/**
 * @param {string} hanhDong
 * @param {boolean} coDangOnline
 * @returns {{duocPhep: boolean, thongBao: string|null}}
 */
export function duocPhepKhiMatMang(hanhDong, coDangOnline) {
    if (coDangOnline || HANH_DONG_DUOC_PHEP_OFFLINE.has(hanhDong)) {
        return { duocPhep: true, thongBao: null };
    }

    return { duocPhep: false, thongBao: THONG_BAO_CAN_MANG };
}

/**
 * Theo dõi mạng có/mất VÀ số việc đang chờ trong hàng chờ, gọi lại `onDoi`
 * mỗi khi một trong hai đổi. Trả về hàm để huỷ theo dõi.
 *
 * @param {{layTiepSoViecDangCho: () => Promise<number>, onDoi: (trangThai: {online: boolean, soViecDangCho: number}) => void}} tuyChon
 */
export function theoDoiTrangThaiMang({ layTiepSoViecDangCho, onDoi }) {
    async function capNhat() {
        const soViecDangCho = await layTiepSoViecDangCho();
        onDoi({ online: navigator.onLine, soViecDangCho });
    }

    window.addEventListener('online', capNhat);
    window.addEventListener('offline', capNhat);
    // Hàng chờ có thể vơi dần ở nền (gửi xong từng việc) mà không có sự kiện
    // online/offline nào xảy ra — dò định kỳ để chấm vàng chuyển xanh kịp lúc.
    const idInterval = setInterval(capNhat, 5000);

    capNhat();

    return function huyTheoDoi() {
        window.removeEventListener('online', capNhat);
        window.removeEventListener('offline', capNhat);
        clearInterval(idInterval);
    };
}

/**
 * Vẽ chỉ báo mạng — chấm màu + dải cảnh báo cam. Nhìn được từ 1 mét: dải
 * cảnh báo chiếm hẳn một dòng ngang đầu màn hình, chữ to, không phải icon nhỏ.
 *
 * @param {{elCham: HTMLElement, elSoViecDangCho: HTMLElement, elDaiCanhBao: HTMLElement}} elements
 * @param {{online: boolean, soViecDangCho: number}} trangThai
 */
export function capNhatChiBaoMang({ elCham, elSoViecDangCho, elDaiCanhBao }, { online, soViecDangCho }) {
    if (!online) {
        elCham.className = 'h-3 w-3 rounded-full bg-orange-500';
        elSoViecDangCho.classList.add('hidden');
        elDaiCanhBao.classList.remove('hidden');
        return;
    }

    elDaiCanhBao.classList.add('hidden');

    if (soViecDangCho > 0) {
        elCham.className = 'h-3 w-3 rounded-full bg-amber-400';
        elSoViecDangCho.textContent = String(soViecDangCho);
        elSoViecDangCho.classList.remove('hidden');
        return;
    }

    elCham.className = 'h-3 w-3 rounded-full bg-emerald-500';
    elSoViecDangCho.classList.add('hidden');
}
