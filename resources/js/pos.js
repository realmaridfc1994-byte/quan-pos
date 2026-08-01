import './bootstrap';
import api, { batBuocDangNhap, layNguoiDung, layThongBaoLoi, taoUuid, xoaDangNhap } from './lib/api';

if (!batBuocDangNhap()) {
    throw new Error('Chưa đăng nhập.');
}

const state = {
    nguoiDung: layNguoiDung(),
    ban: [], // danh sách bàn từ floor-plan
    thongTinLuot: {}, // cache theo session id: {status, subtotal_amount_text, total_amount_text, ...}
    luotHienTai: null, // TableSessionResource đầy đủ đang mở trên màn gọi món
    menu: [], // danh mục + món
    nhomDangChon: null,
    gio: [], // giỏ món chưa gửi
    monDangChonTrongModal: null,
};

function formatTien(so) {
    return `${Number(so).toLocaleString('vi-VN')} đ`;
}

function bao(noiDung, loai = 'ok') {
    const div = document.createElement('div');
    div.className = `fixed left-1/2 top-6 z-50 -translate-x-1/2 rounded-xl px-6 py-3 text-lg font-bold text-white shadow-lg ${
        loai === 'loi' ? 'bg-red-600' : 'bg-neutral-800'
    }`;
    div.textContent = noiDung;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 3000);
}

function hien(el) {
    el.classList.remove('hidden');
    el.classList.add('flex');
}
function an(el) {
    el.classList.add('hidden');
    el.classList.remove('flex');
}

/* ---------- Thanh trên cùng ---------- */

document.getElementById('ten-nguoi-dung').textContent = `${state.nguoiDung.name} (${state.nguoiDung.role_label})`;

document.getElementById('nut-dang-xuat').addEventListener('click', async () => {
    try {
        await api.post('/auth/logout');
    } catch {
        // Token có thể đã hết hạn — vẫn cứ đăng xuất khỏi máy.
    }
    xoaDangNhap();
    window.location.href = '/dang-nhap';
});

document.getElementById('nut-ve-so-do').addEventListener('click', veManSoDoBan);

/* ---------- Màn sơ đồ bàn ---------- */

async function taiSoDoBan() {
    const luoi = document.getElementById('luoi-ban');
    luoi.innerHTML = '<p class="col-span-full text-lg text-neutral-500">Đang tải sơ đồ bàn...</p>';

    const { data } = await api.get('/floor-plan');
    state.ban = data.data;

    const idLuotDangChiem = [...new Set(state.ban.filter((b) => b.session).map((b) => b.session.id))];
    await Promise.all(
        idLuotDangChiem.map(async (id) => {
            try {
                const res = await api.get(`/table-sessions/${id}`);
                state.thongTinLuot[id] = res.data.data;
            } catch {
                state.thongTinLuot[id] = null;
            }
        }),
    );

    renderLuoiBan();
}

function renderLuoiBan() {
    const luoi = document.getElementById('luoi-ban');
    luoi.innerHTML = '';

    for (const ban of state.ban) {
        const luot = ban.session ? state.thongTinLuot[ban.session.id] : null;
        const dangCho = luot?.status === 'billing';

        const mau = !ban.is_occupied
            ? 'bg-emerald-500 active:bg-emerald-600'
            : dangCho
                ? 'bg-red-500 active:bg-red-600'
                : 'bg-orange-500 active:bg-orange-600';

        const o = document.createElement('button');
        o.className = `flex min-h-[110px] flex-col items-center justify-center rounded-2xl p-3 text-white shadow ${mau}`;
        o.innerHTML = `
            <span class="text-2xl font-bold">${ban.name}</span>
            ${ban.is_occupied ? `<span class="mt-1 text-sm">${ban.session.guest_count} khách</span>` : `<span class="mt-1 text-sm">${ban.seats} chỗ</span>`}
            ${luot ? `<span class="mt-1 text-lg font-bold">${luot.total_amount_text}</span>` : ''}
            ${dangCho ? '<span class="mt-1 rounded bg-white/20 px-2 text-xs font-bold">CHỜ THANH TOÁN</span>' : ''}
        `;
        o.addEventListener('click', () => (ban.is_occupied ? vaoManGoiMon(ban.session.id) : moModalMoBan(ban)));
        luoi.appendChild(o);
    }
}

function veManSoDoBan() {
    state.luotHienTai = null;
    state.gio = [];
    an(document.getElementById('man-goi-mon'));
    document.getElementById('man-so-do-ban').classList.remove('hidden');
    document.getElementById('nut-ve-so-do').classList.add('hidden');
    document.getElementById('tieu-de-man').textContent = 'Sơ đồ bàn';
    taiSoDoBan();
}

/* ---------- Mở bàn mới ---------- */

let banDangMo = null;

function moModalMoBan(ban) {
    banDangMo = ban;
    document.getElementById('ten-ban-mo').textContent = ban.name;
    document.getElementById('so-khach').value = 2;

    const banTrong = state.ban.filter((b) => !b.is_occupied && b.id !== ban.id);
    const vungGhep = document.getElementById('danh-sach-ban-trong-de-ghep');
    vungGhep.innerHTML = '';
    for (const b of banTrong) {
        const nhan = document.createElement('label');
        nhan.className = 'flex h-12 items-center gap-2 rounded-lg border-2 border-neutral-300 px-3 text-base has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50';
        nhan.innerHTML = `<input type="checkbox" value="${b.id}" class="h-5 w-5"> ${b.name}`;
        vungGhep.appendChild(nhan);
    }

    hien(document.getElementById('modal-mo-ban'));
}

document.getElementById('nut-huy-mo-ban').addEventListener('click', () => an(document.getElementById('modal-mo-ban')));

document.getElementById('nut-xac-nhan-mo-ban').addEventListener('click', async () => {
    const soKhach = Number(document.getElementById('so-khach').value);
    if (!soKhach || soKhach < 1) {
        bao('Nhập số khách hợp lệ.', 'loi');
        return;
    }

    const idGhep = [...document.querySelectorAll('#danh-sach-ban-trong-de-ghep input:checked')].map((i) => Number(i.value));

    try {
        const { data } = await api.post('/table-sessions', {
            dining_table_ids: [banDangMo.id, ...idGhep],
            primary_dining_table_id: banDangMo.id,
            guest_count: soKhach,
        });
        an(document.getElementById('modal-mo-ban'));
        await vaoManGoiMon(data.data.id);
    } catch (loi) {
        bao(layThongBaoLoi(loi), 'loi');
    }
});

/* ---------- Màn gọi món ---------- */

async function taiThucDonNeuChua() {
    if (state.menu.length > 0) {
        return;
    }
    const { data } = await api.get('/menu');
    state.menu = data.data;
    state.nhomDangChon = state.menu[0]?.id ?? null;
}

async function vaoManGoiMon(sessionId) {
    const { data } = await api.get(`/table-sessions/${sessionId}`);
    state.luotHienTai = data.data;
    state.gio = [];

    await taiThucDonNeuChua();

    an(document.getElementById('man-so-do-ban'));
    document.getElementById('man-goi-mon').classList.remove('hidden');
    document.getElementById('man-goi-mon').classList.add('flex');
    document.getElementById('nut-ve-so-do').classList.remove('hidden');
    document.getElementById('tieu-de-man').textContent = 'Gọi món';

    renderThongTinBan();
    renderNhomMon();
    chonNhom(state.nhomDangChon);
    renderGio();
}

function renderThongTinBan() {
    const luot = state.luotHienTai;
    const tenBan = luot.tables.map((b) => b.name).join(' + ');
    document.getElementById('thong-tin-ban').textContent = `${tenBan} — ${luot.guest_count} khách — ${luot.code}`;
    document.getElementById('tong-tam-tinh').textContent = `Tạm tính: ${luot.total_amount_text}`;
}

function renderNhomMon() {
    const cot = document.getElementById('cot-nhom-mon');
    cot.innerHTML = '';
    for (const nhom of state.menu) {
        const o = document.createElement('button');
        o.className = `block h-16 w-full border-b border-neutral-100 px-3 text-left text-base font-semibold ${
            nhom.id === state.nhomDangChon ? 'bg-blue-600 text-white' : 'active:bg-neutral-100'
        }`;
        o.textContent = nhom.name;
        o.addEventListener('click', () => chonNhom(nhom.id));
        cot.appendChild(o);
    }
}

function chonNhom(nhomId) {
    state.nhomDangChon = nhomId;
    renderNhomMon();

    const nhom = state.menu.find((n) => n.id === nhomId);
    const cotMon = document.getElementById('cot-mon');
    cotMon.innerHTML = '';

    for (const mon of nhom?.products ?? []) {
        const o = document.createElement('button');
        o.className = 'flex min-h-[90px] flex-col items-center justify-center rounded-xl bg-white p-2 text-center shadow active:bg-blue-50';
        const giaHienThi = mon.variants.find((v) => v.is_default)?.price_text ?? mon.variants[0]?.price_text ?? '';
        o.innerHTML = `<span class="text-base font-bold">${mon.name}</span><span class="mt-1 text-sm text-neutral-500">${giaHienThi}</span>`;
        o.addEventListener('click', () => moModalMon(mon));
        cotMon.appendChild(o);
    }
}

/* ---------- Modal chọn món ---------- */

let luaChonModalMon = null;

function moModalMon(mon) {
    luaChonModalMon = { mon, variantId: mon.variants.find((v) => v.is_default)?.id ?? mon.variants[0]?.id, optionIds: new Set(), soLuong: 1 };

    document.getElementById('ten-mon-modal').textContent = mon.name;
    document.getElementById('so-luong-mon').textContent = '1';
    document.getElementById('ghi-chu-mon').value = '';

    const noiDung = document.getElementById('noi-dung-modal-mon');
    noiDung.innerHTML = '';

    if (mon.variants.length > 1) {
        const khoi = document.createElement('div');
        khoi.className = 'mb-4';
        khoi.innerHTML = '<div class="mb-1 font-semibold">Chọn loại</div>';
        for (const bt of mon.variants) {
            const nhan = document.createElement('label');
            nhan.className = 'mb-2 flex h-14 items-center justify-between rounded-lg border-2 border-neutral-300 px-3 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50';
            nhan.innerHTML = `<span><input type="radio" name="bien-the" value="${bt.id}" ${bt.id === luaChonModalMon.variantId ? 'checked' : ''} class="mr-2 h-5 w-5">${bt.name}</span><span class="font-bold">${bt.price_text}</span>`;
            nhan.querySelector('input').addEventListener('change', () => (luaChonModalMon.variantId = bt.id));
            khoi.appendChild(nhan);
        }
        noiDung.appendChild(khoi);
    }

    for (const nhom of mon.option_groups) {
        const laMotChon = nhom.max_select === 1;
        const khoi = document.createElement('div');
        khoi.className = 'mb-4';
        khoi.innerHTML = `<div class="mb-1 font-semibold">${nhom.name}${nhom.is_required ? ' *' : ''}</div>`;
        for (const tuyChon of nhom.options) {
            const nhan = document.createElement('label');
            nhan.className = 'mb-2 flex h-14 items-center justify-between rounded-lg border-2 border-neutral-300 px-3 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50';
            nhan.innerHTML = `<span><input type="${laMotChon ? 'radio' : 'checkbox'}" name="nhom-${nhom.id}" value="${tuyChon.id}" ${tuyChon.is_default ? 'checked' : ''} class="mr-2 h-5 w-5">${tuyChon.name}</span><span class="font-bold">${tuyChon.price_delta === 0 ? '' : tuyChon.price_delta_text}</span>`;
            if (tuyChon.is_default) {
                luaChonModalMon.optionIds.add(tuyChon.id);
            }
            nhan.querySelector('input').addEventListener('change', (su) => {
                if (laMotChon) {
                    for (const t of nhom.options) luaChonModalMon.optionIds.delete(t.id);
                }
                if (su.target.checked) {
                    luaChonModalMon.optionIds.add(tuyChon.id);
                } else {
                    luaChonModalMon.optionIds.delete(tuyChon.id);
                }
            });
            khoi.appendChild(nhan);
        }
        noiDung.appendChild(khoi);
    }

    hien(document.getElementById('modal-mon'));
}

document.getElementById('nut-giam-so-luong').addEventListener('click', () => {
    luaChonModalMon.soLuong = Math.max(1, luaChonModalMon.soLuong - 1);
    document.getElementById('so-luong-mon').textContent = String(luaChonModalMon.soLuong);
});
document.getElementById('nut-tang-so-luong').addEventListener('click', () => {
    luaChonModalMon.soLuong += 1;
    document.getElementById('so-luong-mon').textContent = String(luaChonModalMon.soLuong);
});
document.getElementById('nut-huy-mon').addEventListener('click', () => an(document.getElementById('modal-mon')));

document.getElementById('nut-them-vao-gio').addEventListener('click', () => {
    const { mon, variantId, optionIds, soLuong } = luaChonModalMon;

    for (const nhom of mon.option_groups) {
        const soChon = [...optionIds].filter((id) => nhom.options.some((o) => o.id === id)).length;
        if (soChon < nhom.min_select || soChon > nhom.max_select) {
            bao(`Chọn "${nhom.name}" chưa đúng (${nhom.min_select}-${nhom.max_select} lựa chọn).`, 'loi');
            return;
        }
    }

    const bienThe = mon.variants.find((v) => v.id === variantId);
    const tuyChonDaChon = mon.option_groups.flatMap((n) => n.options.filter((o) => optionIds.has(o.id)).map((o) => ({ id: o.id, name: o.name, groupName: n.name, priceDelta: o.price_delta })));
    const tienTuyChon = tuyChonDaChon.reduce((t, o) => t + o.priceDelta, 0);

    state.gio.push({
        idTam: taoUuid(),
        productId: mon.id,
        productName: mon.name,
        variantId: bienThe.id,
        variantName: bienThe.name,
        unitPrice: bienThe.price,
        station: mon.effective_station,
        quantity: soLuong,
        note: document.getElementById('ghi-chu-mon').value.trim() || null,
        options: tuyChonDaChon,
        lineAmount: (bienThe.price + tienTuyChon) * soLuong,
    });

    an(document.getElementById('modal-mon'));
    renderGio();
});

/* ---------- Giỏ món ---------- */

function renderGio() {
    const vung = document.getElementById('danh-sach-gio');
    vung.innerHTML = '';

    if (state.gio.length === 0) {
        vung.innerHTML = '<p class="p-4 text-center text-neutral-400">Chưa chọn món nào.</p>';
    }

    for (const dong of state.gio) {
        const o = document.createElement('div');
        o.className = 'mb-2 rounded-xl border border-neutral-200 p-2';
        const tuyChonText = dong.options.length > 0 ? `<div class="text-sm text-neutral-500">${dong.options.map((o2) => o2.name).join(', ')}</div>` : '';
        const ghiChuText = dong.note ? `<div class="text-sm italic text-neutral-500">Ghi chú: ${dong.note}</div>` : '';
        o.innerHTML = `
            <div class="flex items-start justify-between">
                <div>
                    <div class="font-bold">${dong.productName} <span class="font-normal text-neutral-500">(${dong.variantName})</span></div>
                    ${tuyChonText}
                    ${ghiChuText}
                </div>
                <button class="nut-xoa-dong h-10 w-10 shrink-0 rounded-lg bg-red-50 text-lg font-bold text-red-600">×</button>
            </div>
            <div class="mt-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button class="nut-giam h-10 w-10 rounded-lg bg-neutral-100 text-xl font-bold">−</button>
                    <span class="w-6 text-center font-bold">${dong.quantity}</span>
                    <button class="nut-tang h-10 w-10 rounded-lg bg-neutral-100 text-xl font-bold">+</button>
                </div>
                <span class="font-bold">${formatTien(dong.lineAmount)}</span>
            </div>
        `;
        o.querySelector('.nut-xoa-dong').addEventListener('click', () => {
            state.gio = state.gio.filter((d) => d.idTam !== dong.idTam);
            renderGio();
        });
        o.querySelector('.nut-giam').addEventListener('click', () => {
            const donGia = dong.lineAmount / dong.quantity;
            dong.quantity = Math.max(1, dong.quantity - 1);
            dong.lineAmount = donGia * dong.quantity;
            renderGio();
        });
        o.querySelector('.nut-tang').addEventListener('click', () => {
            const donGia = dong.lineAmount / dong.quantity;
            dong.quantity += 1;
            dong.lineAmount = donGia * dong.quantity;
            renderGio();
        });
        vung.appendChild(o);
    }

    document.getElementById('nut-gui-bep').disabled = state.gio.length === 0;
}

document.getElementById('nut-xem-da-gui').addEventListener('click', () => {
    const luot = state.luotHienTai;
    const noiDung = document.getElementById('noi-dung-modal-xem');

    if (luot.orders.length === 0) {
        noiDung.innerHTML = '<p class="text-neutral-500">Chưa gửi bếp lần nào.</p>';
    } else {
        noiDung.innerHTML = luot.orders
            .map(
                (don) => `
            <div class="mb-3 rounded-lg border border-neutral-200 p-2">
                <div class="mb-1 text-sm font-bold text-neutral-500">Phiếu #${don.sequence_no} — ${don.station === 'kitchen' ? 'Bếp' : 'Quầy'} — ${don.status}</div>
                ${don.items
                    .map(
                        (m) => `<div class="flex justify-between text-sm ${m.status === 'cancelled' ? 'text-red-500 line-through' : ''}">
                    <span>${m.quantity}× ${m.product_name} (${m.variant_name})</span><span>${m.line_amount_text}</span>
                </div>`,
                    )
                    .join('')}
            </div>`,
            )
            .join('');
    }

    document.getElementById('tieu-de-modal-xem').textContent = 'Đã gửi trước đó';
    hien(document.getElementById('modal-xem'));
});
document.getElementById('nut-dong-modal-xem').addEventListener('click', () => an(document.getElementById('modal-xem')));

/* ---------- Gửi bếp ---------- */

document.getElementById('nut-gui-bep').addEventListener('click', () => {
    if (state.gio.length === 0) return;
    hien(document.getElementById('modal-xac-nhan-gui-bep'));
});
document.getElementById('nut-huy-xac-nhan-gui').addEventListener('click', () => an(document.getElementById('modal-xac-nhan-gui-bep')));

document.getElementById('nut-dong-y-gui-bep').addEventListener('click', async () => {
    an(document.getElementById('modal-xac-nhan-gui-bep'));
    const nutGui = document.getElementById('nut-gui-bep');
    nutGui.disabled = true;
    nutGui.textContent = 'Đang gửi...';

    try {
        const theoTram = { kitchen: [], bar: [] };
        for (const dong of state.gio) {
            theoTram[dong.station].push(dong);
        }

        for (const tram of ['kitchen', 'bar']) {
            if (theoTram[tram].length === 0) continue;

            const { data } = await api.post(`/table-sessions/${state.luotHienTai.id}/orders`, {
                uuid: taoUuid(),
                items: theoTram[tram].map((d) => ({
                    product_id: d.productId,
                    product_variant_id: d.variantId,
                    quantity: d.quantity,
                    note: d.note,
                    option_ids: d.options.map((o) => o.id),
                })),
            });

            await api.post(`/orders/${data.data.id}/send`);
        }

        bao('Đã gửi bếp.');
        state.gio = [];
        const { data: luotMoi } = await api.get(`/table-sessions/${state.luotHienTai.id}`);
        state.luotHienTai = luotMoi.data;
        renderThongTinBan();
        renderGio();
    } catch (loi) {
        bao(layThongBaoLoi(loi), 'loi');
    } finally {
        nutGui.textContent = 'Gửi bếp';
        nutGui.disabled = state.gio.length === 0;
    }
});

/* ---------- In tạm tính ---------- */

document.getElementById('nut-in-tam-tinh').addEventListener('click', async () => {
    try {
        const { data } = await api.get(`/table-sessions/${state.luotHienTai.id}/bill`);
        const bill = data.data;

        document.getElementById('tieu-de-modal-xem').textContent = `Tạm tính — ${bill.code}`;
        document.getElementById('noi-dung-modal-xem').innerHTML = `
            <div class="mb-3 border-b border-dashed border-neutral-300 pb-2 text-sm text-neutral-500">Bàn: ${state.luotHienTai.tables.map((b) => b.name).join(' + ')}</div>
            ${bill.payments && bill.payments.length >= 0 ? '' : ''}
            <div class="mb-2 flex justify-between font-bold"><span>Tạm tính</span><span>${bill.subtotal_amount_text}</span></div>
            ${bill.discount_amount > 0 ? `<div class="mb-2 flex justify-between text-red-600"><span>Giảm giá</span><span>-${bill.discount_amount_text}</span></div>` : ''}
            <div class="mb-2 flex justify-between border-t border-neutral-300 pt-2 text-lg font-bold"><span>Tổng cộng</span><span>${bill.total_amount_text}</span></div>
        `;
        hien(document.getElementById('modal-xem'));
        setTimeout(() => window.print(), 200);
    } catch (loi) {
        bao(layThongBaoLoi(loi), 'loi');
    }
});

/* ---------- Thu tiền ---------- */

let hoaDonThu = null;

document.getElementById('nut-thu-tien').addEventListener('click', async () => {
    try {
        const { data } = await api.get(`/table-sessions/${state.luotHienTai.id}/bill`);
        hoaDonThu = data.data;
        renderModalThuTien();
        hien(document.getElementById('modal-thu-tien'));
    } catch (loi) {
        bao(layThongBaoLoi(loi), 'loi');
    }
});

function renderModalThuTien() {
    document.getElementById('tt-tong').textContent = hoaDonThu.total_amount_text;
    document.getElementById('tt-da-thu').textContent = hoaDonThu.paid_amount_text;
    document.getElementById('tt-con-thieu').textContent = hoaDonThu.remaining_amount_text;
    document.getElementById('so-tien-thu').value = hoaDonThu.remaining_amount;
    document.getElementById('tien-khach-dua').value = '';
    document.getElementById('tien-thoi').textContent = formatTien(0);
    document.getElementById('loi-thu-tien').classList.add('hidden');
    chonPhuongThuc('cash');
}

function chonPhuongThuc(phuongThuc) {
    for (const nut of document.querySelectorAll('.nut-phuong-thuc')) {
        const dangChon = nut.dataset.method === phuongThuc;
        nut.classList.toggle('border-blue-600', dangChon);
        nut.classList.toggle('text-blue-700', dangChon);
        nut.classList.toggle('border-neutral-300', !dangChon);
        nut.classList.toggle('text-neutral-500', !dangChon);
    }
    document.getElementById('vung-tien-mat').classList.toggle('hidden', phuongThuc !== 'cash');
    document.getElementById('vung-chuyen-khoan').classList.toggle('hidden', phuongThuc === 'cash');
    document.getElementById('modal-thu-tien').dataset.method = phuongThuc;
}

for (const nut of document.querySelectorAll('.nut-phuong-thuc')) {
    nut.addEventListener('click', () => chonPhuongThuc(nut.dataset.method));
}

for (const nut of document.querySelectorAll('.nut-tien-nhanh')) {
    nut.addEventListener('click', () => {
        const o = document.getElementById('tien-khach-dua');
        o.value = Number(o.value || 0) + Number(nut.dataset.nhanh);
        capNhatTienThoi();
    });
}

function capNhatTienThoi() {
    const khachDua = Number(document.getElementById('tien-khach-dua').value || 0);
    const soTienThu = Number(document.getElementById('so-tien-thu').value || 0);
    document.getElementById('tien-thoi').textContent = formatTien(Math.max(0, khachDua - soTienThu));
}
document.getElementById('tien-khach-dua').addEventListener('input', capNhatTienThoi);
document.getElementById('so-tien-thu').addEventListener('input', capNhatTienThoi);

document.getElementById('nut-dong-thu-tien').addEventListener('click', () => an(document.getElementById('modal-thu-tien')));

document.getElementById('nut-xac-nhan-thu-tien').addEventListener('click', async () => {
    const phuongThuc = document.getElementById('modal-thu-tien').dataset.method;
    const soTienThu = Number(document.getElementById('so-tien-thu').value || 0);
    const khachDua = Number(document.getElementById('tien-khach-dua').value || 0);
    const loiO = document.getElementById('loi-thu-tien');
    loiO.classList.add('hidden');

    if (soTienThu < 1) {
        loiO.textContent = 'Số tiền ghi nhận phải lớn hơn 0.';
        loiO.classList.remove('hidden');
        return;
    }
    if (phuongThuc === 'cash' && khachDua < soTienThu) {
        loiO.textContent = 'Tiền khách đưa không được ít hơn số tiền ghi nhận.';
        loiO.classList.remove('hidden');
        return;
    }

    const nut = document.getElementById('nut-xac-nhan-thu-tien');
    nut.disabled = true;
    nut.textContent = 'Đang xử lý...';

    try {
        const { data } = await api.post(`/table-sessions/${state.luotHienTai.id}/payments`, {
            uuid: taoUuid(),
            method: phuongThuc,
            amount: soTienThu,
            tendered_amount: phuongThuc === 'cash' ? khachDua : null,
            reference: phuongThuc === 'transfer' ? document.getElementById('ma-tham-chieu').value.trim() || null : null,
        });

        const { data: luotMoi } = await api.get(`/table-sessions/${state.luotHienTai.id}`);
        state.luotHienTai = luotMoi.data;

        if (luotMoi.data.status === 'closed') {
            bao('Đã thu đủ tiền, bàn đã được nhả.');
            an(document.getElementById('modal-thu-tien'));
            veManSoDoBan();
        } else {
            bao('Đã ghi nhận thu tiền.');
            const { data: billMoi } = await api.get(`/table-sessions/${state.luotHienTai.id}/bill`);
            hoaDonThu = billMoi.data;
            renderModalThuTien();
            renderThongTinBan();
        }
    } catch (loi) {
        loiO.textContent = layThongBaoLoi(loi);
        loiO.classList.remove('hidden');
    } finally {
        nut.disabled = false;
        nut.textContent = 'Xác nhận thu';
    }
});

/* ---------- Khoá màn hình bằng PIN ---------- */

let pinDangNhap = '';

function renderBanPhimPin() {
    const luoi = document.getElementById('ban-phim-pin');
    luoi.innerHTML = '';
    const phim = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'xoa', '0', 'ok'];
    for (const p of phim) {
        const o = document.createElement('button');
        o.className = 'flex h-20 w-20 items-center justify-center rounded-full bg-neutral-700 text-3xl font-bold active:bg-neutral-600';
        o.textContent = p === 'xoa' ? '⌫' : p === 'ok' ? '' : p;
        if (p === 'ok') {
            o.classList.add('invisible');
        } else {
            o.addEventListener('click', () => nhanPhimPin(p));
        }
        luoi.appendChild(o);
    }
}

function nhanPhimPin(p) {
    if (p === 'xoa') {
        pinDangNhap = pinDangNhap.slice(0, -1);
    } else if (pinDangNhap.length < 4) {
        pinDangNhap += p;
    }
    capNhatChamPin();
    if (pinDangNhap.length === 4) {
        moKhoaBangPin();
    }
}

function capNhatChamPin() {
    const cham = document.querySelectorAll('#pin-cham span');
    cham.forEach((c, i) => {
        c.classList.toggle('bg-white', i < pinDangNhap.length);
    });
}

async function moKhoaBangPin() {
    const loiO = document.getElementById('loi-mo-khoa');
    loiO.classList.add('hidden');
    try {
        await api.post('/auth/pin-verify', { user_id: state.nguoiDung.id, pin: pinDangNhap });
        an(document.getElementById('man-khoa'));
        pinDangNhap = '';
        capNhatChamPin();
    } catch (loi) {
        loiO.textContent = layThongBaoLoi(loi);
        loiO.classList.remove('hidden');
        pinDangNhap = '';
        capNhatChamPin();
    }
}

document.getElementById('nut-khoa-man-hinh').addEventListener('click', () => {
    document.getElementById('ten-nguoi-khoa').textContent = `${state.nguoiDung.name} — mở khoá bằng PIN của bạn`;
    pinDangNhap = '';
    capNhatChamPin();
    renderBanPhimPin();
    hien(document.getElementById('man-khoa'));
});

document.getElementById('nut-doi-tai-khoan').addEventListener('click', async () => {
    try {
        await api.post('/auth/logout');
    } catch {
        // bỏ qua, vẫn đăng xuất khỏi máy
    }
    xoaDangNhap();
    window.location.href = '/dang-nhap';
});

/* ---------- Khởi động ---------- */

veManSoDoBan();
