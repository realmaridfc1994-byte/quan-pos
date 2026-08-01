<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>POS — Quán Nhậu</title>
    @vite(['resources/css/app.css', 'resources/js/pos.js'])
</head>
<body class="h-screen w-screen overflow-hidden bg-neutral-100 font-sans text-neutral-900 antialiased select-none">

    <div id="man-pos" class="flex h-full w-full flex-col">
        {{-- Thanh trên cùng --}}
        <header class="flex h-16 shrink-0 items-center justify-between bg-neutral-900 px-4 text-white">
            <div class="flex items-center gap-3">
                <button id="nut-ve-so-do" class="hidden h-12 min-w-[60px] items-center justify-center rounded-lg bg-neutral-700 px-4 text-lg font-bold active:bg-neutral-600">
                    ← Bàn
                </button>
                <span id="tieu-de-man" class="text-xl font-bold">Sơ đồ bàn</span>
            </div>
            <div class="flex items-center gap-2">
                <span id="ten-nguoi-dung" class="mr-2 text-lg"></span>
                <button id="nut-khoa-man-hinh" class="h-12 min-w-[60px] rounded-lg bg-neutral-700 px-4 text-lg font-bold active:bg-neutral-600">Khoá màn hình</button>
                <button id="nut-dang-xuat" class="h-12 min-w-[60px] rounded-lg bg-red-700 px-4 text-lg font-bold active:bg-red-800">Đăng xuất</button>
            </div>
        </header>

        {{-- MÀN SƠ ĐỒ BÀN --}}
        <section id="man-so-do-ban" class="flex-1 overflow-y-auto p-4">
            <div class="mb-3 flex gap-4 text-base font-medium">
                <span class="flex items-center gap-2"><span class="h-5 w-5 rounded bg-emerald-500"></span> Trống</span>
                <span class="flex items-center gap-2"><span class="h-5 w-5 rounded bg-orange-500"></span> Đang ăn</span>
                <span class="flex items-center gap-2"><span class="h-5 w-5 rounded bg-red-500"></span> Chờ thanh toán</span>
            </div>
            <div id="luoi-ban" class="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6"></div>
        </section>

        {{-- MÀN GỌI MÓN --}}
        <section id="man-goi-mon" class="hidden flex-1 overflow-hidden">
            <div class="flex h-10 shrink-0 items-center justify-between bg-white px-4 text-sm">
                <div id="thong-tin-ban" class="font-semibold"></div>
                <div id="tong-tam-tinh" class="font-bold text-blue-700"></div>
            </div>
            <div class="flex h-[calc(100%-2.5rem)]">
                {{-- Nhóm món --}}
                <div id="cot-nhom-mon" class="w-32 shrink-0 overflow-y-auto border-r border-neutral-300 bg-white sm:w-44"></div>
                {{-- Món --}}
                <div id="cot-mon" class="grid flex-1 auto-rows-min grid-cols-3 gap-3 overflow-y-auto bg-neutral-50 p-3 sm:grid-cols-4"></div>
                {{-- Giỏ món --}}
                <div class="flex w-72 shrink-0 flex-col border-l border-neutral-300 bg-white sm:w-96">
                    <div class="flex items-center justify-between border-b border-neutral-200 px-3 py-2">
                        <span class="text-lg font-bold">Giỏ món</span>
                        <button id="nut-xem-da-gui" class="text-sm font-medium text-blue-700 underline">Đã gửi trước đó</button>
                    </div>
                    <div id="danh-sach-gio" class="flex-1 overflow-y-auto px-2 py-2"></div>
                    <div class="grid grid-cols-3 gap-2 border-t border-neutral-200 p-3">
                        <button id="nut-gui-bep" class="col-span-3 h-16 rounded-xl bg-emerald-600 text-xl font-bold text-white active:bg-emerald-700 disabled:opacity-40">Gửi bếp</button>
                        <button id="nut-in-tam-tinh" class="h-16 rounded-xl bg-neutral-700 text-base font-bold text-white active:bg-neutral-800">In tạm tính</button>
                        <button id="nut-thu-tien" class="col-span-2 h-16 rounded-xl bg-blue-700 text-xl font-bold text-white active:bg-blue-800">Thu tiền</button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Modal mở bàn --}}
    <div id="modal-mo-ban" class="fixed inset-0 z-30 hidden items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6">
            <h2 class="mb-4 text-xl font-bold">Mở bàn <span id="ten-ban-mo"></span></h2>
            <label class="mb-1 block text-lg font-medium">Số khách</label>
            <input id="so-khach" type="number" min="1" value="2" class="mb-4 h-16 w-full rounded-xl border-2 border-neutral-300 px-4 text-2xl">
            <div class="mb-4">
                <div class="mb-1 text-lg font-medium">Ghép thêm bàn trống (nếu có)</div>
                <div id="danh-sach-ban-trong-de-ghep" class="flex flex-wrap gap-2"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <button id="nut-huy-mo-ban" class="h-16 rounded-xl bg-neutral-200 text-xl font-bold active:bg-neutral-300">Huỷ</button>
                <button id="nut-xac-nhan-mo-ban" class="h-16 rounded-xl bg-emerald-600 text-xl font-bold text-white active:bg-emerald-700">Mở bàn</button>
            </div>
        </div>
    </div>

    {{-- Modal chọn món (biến thể / tuỳ chọn / số lượng) --}}
    <div id="modal-mon" class="fixed inset-0 z-30 hidden items-center justify-center bg-black/50 p-4">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6">
            <h2 id="ten-mon-modal" class="mb-4 text-xl font-bold"></h2>
            <div id="noi-dung-modal-mon"></div>
            <div class="mt-4 flex items-center justify-center gap-4">
                <button id="nut-giam-so-luong" class="h-16 w-16 rounded-xl bg-neutral-200 text-3xl font-bold active:bg-neutral-300">−</button>
                <span id="so-luong-mon" class="w-16 text-center text-3xl font-bold">1</span>
                <button id="nut-tang-so-luong" class="h-16 w-16 rounded-xl bg-neutral-200 text-3xl font-bold active:bg-neutral-300">+</button>
            </div>
            <textarea id="ghi-chu-mon" placeholder="Ghi chú (không bắt buộc)" class="mt-4 h-16 w-full rounded-xl border-2 border-neutral-300 p-3 text-lg"></textarea>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <button id="nut-huy-mon" class="h-16 rounded-xl bg-neutral-200 text-xl font-bold active:bg-neutral-300">Huỷ</button>
                <button id="nut-them-vao-gio" class="h-16 rounded-xl bg-emerald-600 text-xl font-bold text-white active:bg-emerald-700">Thêm vào giỏ</button>
            </div>
        </div>
    </div>

    {{-- Modal xác nhận gửi bếp --}}
    <div id="modal-xac-nhan-gui-bep" class="fixed inset-0 z-30 hidden items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center">
            <p class="mb-6 text-xl font-semibold">Gửi bếp rồi chỉ huỷ được kèm lý do, không sửa được nữa. Xác nhận gửi?</p>
            <div class="grid grid-cols-2 gap-3">
                <button id="nut-huy-xac-nhan-gui" class="h-16 rounded-xl bg-neutral-200 text-xl font-bold active:bg-neutral-300">Xem lại</button>
                <button id="nut-dong-y-gui-bep" class="h-16 rounded-xl bg-emerald-600 text-xl font-bold text-white active:bg-emerald-700">Gửi bếp</button>
            </div>
        </div>
    </div>

    {{-- Modal thu tiền --}}
    <div id="modal-thu-tien" class="fixed inset-0 z-30 hidden items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-2xl rounded-2xl bg-white p-6">
            <h2 class="mb-2 text-xl font-bold">Thu tiền</h2>
            <div class="mb-4 grid grid-cols-2 gap-4 text-lg">
                <div>Tổng hoá đơn: <span id="tt-tong" class="font-bold"></span></div>
                <div>Đã thu: <span id="tt-da-thu" class="font-bold"></span></div>
                <div class="col-span-2">Còn thiếu: <span id="tt-con-thieu" class="text-2xl font-bold text-red-600"></span></div>
            </div>

            <div class="mb-3 flex gap-3">
                <button data-method="cash" class="nut-phuong-thuc h-14 flex-1 rounded-xl border-2 border-blue-600 text-lg font-bold text-blue-700">Tiền mặt</button>
                <button data-method="transfer" class="nut-phuong-thuc h-14 flex-1 rounded-xl border-2 border-neutral-300 text-lg font-bold text-neutral-500">Chuyển khoản</button>
            </div>

            <div id="vung-tien-mat">
                <div class="mb-2 grid grid-cols-3 gap-2">
                    <button data-nhanh="100000" class="nut-tien-nhanh h-14 rounded-xl bg-neutral-100 text-lg font-bold active:bg-neutral-200">100.000</button>
                    <button data-nhanh="200000" class="nut-tien-nhanh h-14 rounded-xl bg-neutral-100 text-lg font-bold active:bg-neutral-200">200.000</button>
                    <button data-nhanh="500000" class="nut-tien-nhanh h-14 rounded-xl bg-neutral-100 text-lg font-bold active:bg-neutral-200">500.000</button>
                </div>
                <div class="mb-2">
                    <label class="mb-1 block text-base font-medium">Khách đưa</label>
                    <input id="tien-khach-dua" type="number" inputmode="numeric" class="h-16 w-full rounded-xl border-2 border-neutral-300 px-4 text-right text-3xl font-bold">
                </div>
                <div class="mb-2 text-center text-lg">Tiền thối: <span id="tien-thoi" class="text-3xl font-bold text-emerald-700">0 đ</span></div>
            </div>

            <div id="vung-chuyen-khoan" class="hidden mb-2">
                <label class="mb-1 block text-base font-medium">Mã tham chiếu (không bắt buộc)</label>
                <input id="ma-tham-chieu" type="text" class="h-14 w-full rounded-xl border-2 border-neutral-300 px-4 text-lg">
            </div>

            <div class="mb-2">
                <label class="mb-1 block text-base font-medium">Số tiền ghi nhận</label>
                <input id="so-tien-thu" type="number" inputmode="numeric" class="h-16 w-full rounded-xl border-2 border-neutral-300 px-4 text-right text-3xl font-bold">
            </div>

            <p id="loi-thu-tien" class="mb-2 hidden rounded-lg bg-red-50 px-4 py-2 text-center font-medium text-red-700"></p>

            <div class="grid grid-cols-2 gap-3">
                <button id="nut-dong-thu-tien" class="h-16 rounded-xl bg-neutral-200 text-xl font-bold active:bg-neutral-300">Đóng</button>
                <button id="nut-xac-nhan-thu-tien" class="h-16 rounded-xl bg-blue-700 text-xl font-bold text-white active:bg-blue-800">Xác nhận thu</button>
            </div>
        </div>
    </div>

    {{-- Modal xem tạm tính / đã gửi --}}
    <div id="modal-xem" class="fixed inset-0 z-30 hidden items-center justify-center bg-black/50 p-4">
        <div class="max-h-[85vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-6">
            <h2 id="tieu-de-modal-xem" class="mb-4 text-xl font-bold"></h2>
            <div id="noi-dung-modal-xem" class="text-base"></div>
            <button id="nut-dong-modal-xem" class="mt-4 h-14 w-full rounded-xl bg-neutral-200 text-lg font-bold active:bg-neutral-300">Đóng</button>
        </div>
    </div>

    {{-- Màn khoá nhanh bằng PIN --}}
    <div id="man-khoa" class="fixed inset-0 z-40 hidden flex-col items-center justify-center bg-neutral-900 text-white">
        <p class="mb-2 text-2xl font-bold">Màn hình đang khoá</p>
        <p id="ten-nguoi-khoa" class="mb-8 text-lg text-neutral-300"></p>
        <div id="pin-cham" class="mb-8 flex gap-4">
            <span class="h-5 w-5 rounded-full border-2 border-white"></span>
            <span class="h-5 w-5 rounded-full border-2 border-white"></span>
            <span class="h-5 w-5 rounded-full border-2 border-white"></span>
            <span class="h-5 w-5 rounded-full border-2 border-white"></span>
        </div>
        <div id="ban-phim-pin" class="grid grid-cols-3 gap-4"></div>
        <p id="loi-mo-khoa" class="mt-4 hidden text-lg font-medium text-red-400"></p>
        <button id="nut-doi-tai-khoan" class="mt-8 text-lg font-medium text-neutral-300 underline">Đăng xuất, đổi tài khoản khác</button>
    </div>

</body>
</html>
