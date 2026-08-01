<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Bếp — Quán Nhậu</title>
    @vite(['resources/css/app.css', 'resources/js/bep.js'])
</head>
<body class="h-screen w-screen overflow-hidden bg-neutral-950 font-sans text-white antialiased select-none">

    <header class="flex h-16 shrink-0 items-center justify-between bg-neutral-900 px-4">
        <div class="flex items-center gap-3">
            <span class="text-2xl font-bold">Màn hình bếp</span>
            <div class="flex overflow-hidden rounded-lg border border-neutral-600">
                <button id="nut-tram-kitchen" data-station="kitchen" class="nut-tram h-12 px-5 text-lg font-bold">Bếp</button>
                <button id="nut-tram-bar" data-station="bar" class="nut-tram h-12 px-5 text-lg font-bold">Quầy</button>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span id="gio-hien-tai" class="text-xl font-bold"></span>
            <button id="nut-dang-xuat" class="h-12 rounded-lg bg-red-700 px-4 text-lg font-bold active:bg-red-800">Đăng xuất</button>
        </div>
    </header>

    <main id="luoi-phieu" class="grid h-[calc(100%-4rem)] grid-cols-2 gap-3 overflow-y-auto p-3 sm:grid-cols-3 lg:grid-cols-4"></main>

</body>
</html>
