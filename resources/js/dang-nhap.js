import './bootstrap';
import api, { coDangNhap, layNguoiDung, layThongBaoLoi, luuDangNhap } from './lib/api';

function trangSauDangNhap(nguoiDung) {
    return nguoiDung.role === 'kitchen' ? '/bep' : '/pos';
}

// Đã đăng nhập sẵn thì khỏi phải nhập lại.
if (coDangNhap()) {
    window.location.href = trangSauDangNhap(layNguoiDung());
}

const form = document.getElementById('form-dang-nhap');
const nutDangNhap = document.getElementById('nut-dang-nhap');
const thongBaoLoi = document.getElementById('thong-bao-loi');

form.addEventListener('submit', async (su) => {
    su.preventDefault();

    thongBaoLoi.classList.add('hidden');
    nutDangNhap.disabled = true;
    nutDangNhap.textContent = 'Đang đăng nhập...';

    try {
        const { data } = await api.post('/auth/login', {
            phone: document.getElementById('phone').value.trim(),
            password: document.getElementById('password').value,
        });

        luuDangNhap(data.data.token, data.data.user);
        window.location.href = trangSauDangNhap(data.data.user);
    } catch (loi) {
        thongBaoLoi.textContent = layThongBaoLoi(loi);
        thongBaoLoi.classList.remove('hidden');
        nutDangNhap.disabled = false;
        nutDangNhap.textContent = 'Đăng nhập';
    }
});
