import axios from 'axios';

const KHOA_TOKEN = 'pos_token';
const KHOA_NGUOI_DUNG = 'pos_user';

const api = axios.create({ baseURL: '/api/v1' });

api.interceptors.request.use((config) => {
    const token = localStorage.getItem(KHOA_TOKEN);
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const phuongThuc = (config.method ?? '').toLowerCase();
    if ((phuongThuc === 'post' || phuongThuc === 'patch') && !config.headers['Idempotency-Key']) {
        config.headers['Idempotency-Key'] = taoUuid();
    }

    return config;
});

api.interceptors.response.use(
    (res) => res,
    (loi) => {
        if (loi.response?.status === 401) {
            xoaDangNhap();
            if (!window.location.pathname.startsWith('/dang-nhap')) {
                window.location.href = '/dang-nhap';
            }
        }

        return Promise.reject(loi);
    },
);

export function taoUuid() {
    return crypto.randomUUID();
}

export function luuDangNhap(token, nguoiDung) {
    localStorage.setItem(KHOA_TOKEN, token);
    localStorage.setItem(KHOA_NGUOI_DUNG, JSON.stringify(nguoiDung));
}

export function xoaDangNhap() {
    localStorage.removeItem(KHOA_TOKEN);
    localStorage.removeItem(KHOA_NGUOI_DUNG);
}

export function layNguoiDung() {
    const raw = localStorage.getItem(KHOA_NGUOI_DUNG);
    return raw ? JSON.parse(raw) : null;
}

export function coDangNhap() {
    return localStorage.getItem(KHOA_TOKEN) !== null;
}

/** Chặn vào trang cần đăng nhập — gọi ở đầu mỗi trang POS/bếp. */
export function batBuocDangNhap() {
    if (!coDangNhap()) {
        window.location.href = '/dang-nhap';
        return false;
    }
    return true;
}

/** Lấy thông báo lỗi tiếng Việt từ response API để hiện cho người dùng. */
export function layThongBaoLoi(loi) {
    return loi.response?.data?.message ?? 'Có lỗi xảy ra, vui lòng thử lại.';
}

export default api;
