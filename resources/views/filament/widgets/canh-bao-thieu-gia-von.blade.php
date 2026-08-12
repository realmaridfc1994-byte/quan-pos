<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <span class="text-warning-600 dark:text-warning-400">⚠️ Lãi gộp tháng {{ $thang }} đang cao hơn thực tế</span>
        </x-slot>

        <p class="text-sm font-medium text-warning-700 dark:text-warning-400">
            {{ $tongKet['cau'] }}
        </p>

        <ul class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-300">
            @foreach ($monThieu as $dong)
                <li>• {{ $dong['canh_bao'] }}</li>
            @endforeach
        </ul>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Hai lý do làm lãi trông cao hơn thật: món bán lúc kho đang âm (chưa biết giá vốn thật là bao nhiêu),
            và món đã tính tiền mà bếp chưa bấm "xong" (nên chưa trừ kho, trong khi tiền vẫn tính đủ).
            Chạy <code>php artisan stock:doi-soat</code> để xem danh sách chi tiết cần dọn.
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
