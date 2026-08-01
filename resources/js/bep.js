import './bootstrap';
import api, { batBuocDangNhap, layThongBaoLoi, xoaDangNhap } from './lib/api';

if (!batBuocDangNhap()) {
    throw new Error('Chưa đăng nhập.');
}

const KHOA_TRAM = 'pos_kds_tram';
let tramHienTai = localStorage.getItem(KHOA_TRAM) ?? 'kitchen';
let dangTai = false;

function chonTram(tram) {
    tramHienTai = tram;
    localStorage.setItem(KHOA_TRAM, tram);
    for (const nut of document.querySelectorAll('.nut-tram')) {
        const dangChon = nut.dataset.station === tram;
        nut.classList.toggle('bg-blue-600', dangChon);
        nut.classList.toggle('bg-neutral-800', !dangChon);
    }
    taiPhieu();
}

for (const nut of document.querySelectorAll('.nut-tram')) {
    nut.addEventListener('click', () => chonTram(nut.dataset.station));
}

document.getElementById('nut-dang-xuat').addEventListener('click', async () => {
    try {
        await api.post('/auth/logout');
    } catch {
        // token có thể đã hết hạn, vẫn đăng xuất khỏi máy
    }
    xoaDangNhap();
    window.location.href = '/dang-nhap';
});

function phutDaTroi(sentAt) {
    return (Date.now() - new Date(sentAt).getTime()) / 60000;
}

function mauTheoThoiGianCho(phut) {
    if (phut < 5) return 'border-emerald-500 bg-emerald-950';
    if (phut < 10) return 'border-amber-400 bg-amber-950';
    return 'border-red-500 bg-red-950';
}

function renderPhieu(phieu) {
    const phut = phutDaTroi(phieu.sent_at);
    const gio = new Date(phieu.sent_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    const conMonChuaXong = phieu.items.some((m) => m.status === 'ordered');

    const the = document.createElement('div');
    the.className = `flex flex-col rounded-2xl border-4 p-3 ${mauTheoThoiGianCho(phut)}`;
    the.innerHTML = `
        <div class="mb-2 flex items-baseline justify-between">
            <span class="text-2xl font-black">${phieu.table_code ?? phieu.table_session_code}</span>
            <span class="text-xl font-bold">${gio} · ${Math.floor(phut)} phút</span>
        </div>
        ${phieu.note ? `<div class="mb-2 rounded bg-black/30 px-2 py-1 text-lg italic">${phieu.note}</div>` : ''}
        <div class="danh-sach-mon mb-3 flex flex-1 flex-col gap-2"></div>
        <button class="nut-xong-het h-16 rounded-xl bg-emerald-600 text-2xl font-black active:bg-emerald-700 disabled:opacity-30" ${conMonChuaXong ? '' : 'disabled'}>
            XONG CẢ PHIẾU
        </button>
    `;

    const dsMon = the.querySelector('.danh-sach-mon');
    for (const mon of phieu.items) {
        const xong = mon.status === 'served';
        const huy = mon.status === 'cancelled';
        const dong = document.createElement('div');
        dong.className = `flex items-center justify-between rounded-lg px-2 py-2 text-xl font-bold ${
            huy ? 'bg-black/20 text-neutral-500 line-through' : xong ? 'bg-black/20 text-neutral-400 line-through' : 'bg-black/30'
        }`;
        dong.innerHTML = `
            <span>${mon.quantity}× ${mon.product_name}${mon.variant_name ? ` (${mon.variant_name})` : ''}${mon.note ? ` — <span class="italic">${mon.note}</span>` : ''}</span>
            ${!xong && !huy ? '<button class="nut-xong-mon ml-2 h-12 shrink-0 rounded-lg bg-emerald-600 px-4 text-lg active:bg-emerald-700">XONG</button>' : ''}
        `;
        if (!xong && !huy) {
            dong.querySelector('.nut-xong-mon').addEventListener('click', () => danhDauMonXong(mon.id));
        }
        dsMon.appendChild(dong);
    }

    the.querySelector('.nut-xong-het').addEventListener('click', async () => {
        for (const mon of phieu.items) {
            if (mon.status === 'ordered') {
                await danhDauMonXong(mon.id, false);
            }
        }
        taiPhieu();
    });

    return the;
}

async function danhDauMonXong(orderItemId, taiLaiNgay = true) {
    try {
        await api.post(`/kds/items/${orderItemId}/status`);
    } catch (loi) {
        alert(layThongBaoLoi(loi));
    }
    if (taiLaiNgay) {
        taiPhieu();
    }
}

async function taiPhieu() {
    if (dangTai) return;
    dangTai = true;
    try {
        const { data } = await api.get('/kds/tickets', { params: { station: tramHienTai } });
        const luoi = document.getElementById('luoi-phieu');
        luoi.innerHTML = '';

        const phieuConViec = data.data.filter((p) => p.status !== 'served' && p.status !== 'cancelled');

        if (phieuConViec.length === 0) {
            luoi.innerHTML = '<p class="col-span-full mt-10 text-center text-2xl text-neutral-500">Không có phiếu nào đang chờ.</p>';
        } else {
            for (const phieu of phieuConViec) {
                luoi.appendChild(renderPhieu(phieu));
            }
        }
    } catch (loi) {
        console.error(loi);
    } finally {
        dangTai = false;
    }
}

function capNhatGio() {
    document.getElementById('gio-hien-tai').textContent = new Date().toLocaleTimeString('vi-VN');
}

chonTram(tramHienTai);
capNhatGio();
setInterval(taiPhieu, 3000);
setInterval(capNhatGio, 1000);
