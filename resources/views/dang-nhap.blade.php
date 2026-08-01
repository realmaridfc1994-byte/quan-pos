<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Đăng nhập — POS Quán Nhậu</title>
    @vite(['resources/css/app.css', 'resources/js/dang-nhap.js'])
</head>
<body class="h-screen w-screen overflow-hidden bg-neutral-900 font-sans text-neutral-900 antialiased">
    <div class="flex h-full w-full items-center justify-center">
        <form id="form-dang-nhap" class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="mb-8 text-center text-2xl font-bold text-neutral-800">Đăng nhập quán</h1>

            <div class="mb-5">
                <label for="phone" class="mb-2 block text-lg font-medium text-neutral-700">Số điện thoại</label>
                <input
                    id="phone" name="phone" type="tel" inputmode="tel" autocomplete="username" required
                    class="h-16 w-full rounded-xl border-2 border-neutral-300 px-4 text-2xl focus:border-blue-500 focus:outline-none"
                    placeholder="09xxxxxxxx"
                >
            </div>

            <div class="mb-6">
                <label for="password" class="mb-2 block text-lg font-medium text-neutral-700">Mật khẩu</label>
                <input
                    id="password" name="password" type="password" autocomplete="current-password" required
                    class="h-16 w-full rounded-xl border-2 border-neutral-300 px-4 text-2xl focus:border-blue-500 focus:outline-none"
                    placeholder="••••••••"
                >
            </div>

            <p id="thong-bao-loi" class="mb-4 hidden rounded-lg bg-red-50 px-4 py-3 text-center text-lg font-medium text-red-700"></p>

            <button
                id="nut-dang-nhap" type="submit"
                class="h-16 w-full rounded-xl bg-blue-600 text-2xl font-bold text-white active:bg-blue-800"
            >
                Đăng nhập
            </button>
        </form>
    </div>
</body>
</html>
