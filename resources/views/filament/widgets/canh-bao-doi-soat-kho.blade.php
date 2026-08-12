<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Đối soát sổ cái kho
        </x-slot>

        @if (! $daChay)
            <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có lần đối soát nào (chạy sau khi đóng ca đầu tiên, hoặc chạy tay <code>php artisan stock:doi-soat</code>).</p>
        @elseif ($sach)
            <p class="text-sm text-success-600">✅ Lần đối soát gần nhất lúc {{ $thoiDiem }}: sổ cái kho sạch, không lệch.</p>
        @else
            <p class="mb-3 text-sm font-medium text-danger-600">❌ Lần đối soát gần nhất lúc {{ $thoiDiem }} phát hiện lệch:</p>

            @if (! empty($lechQty))
                <div class="mb-3">
                    <h4 class="mb-1 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Lệch số lượng</h4>
                    <ul class="list-inside list-disc text-sm">
                        @foreach ($lechQty as $dong)
                            <li>{{ $dong['ingredient_name'] }}: sổ cái {{ $dong['so_cai'] }}, bảng tồn {{ $dong['ton_kho'] }}, lệch {{ $dong['lech_text'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($lechCost))
                <div class="mb-3">
                    <h4 class="mb-1 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Lệch giá trị</h4>
                    <ul class="list-inside list-disc text-sm">
                        @foreach ($lechCost as $dong)
                            <li>{{ $dong['ingredient_name'] }}: {{ $dong['lech_text'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($thieuSoCai))
                <div class="mb-3">
                    <h4 class="mb-1 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Dòng món đã phục vụ nhưng thiếu sổ cái</h4>
                    <ul class="list-inside list-disc text-sm">
                        @foreach ($thieuSoCai as $dong)
                            <li>#{{ $dong['order_item_id'] }} {{ $dong['product_name'] }} — {{ $dong['variant_name'] }} (phục vụ {{ $dong['served_at'] }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($soCaiMoCoi))
                <div>
                    <h4 class="mb-1 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Dòng sổ cái mồ côi</h4>
                    <ul class="list-inside list-disc text-sm">
                        @foreach ($soCaiMoCoi as $dong)
                            <li>Sổ cái #{{ $dong['stock_movement_id'] }} → order_item #{{ $dong['ref_id'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        {{-- Hai mục CẢNH BÁO: hiện cả khi sổ cái sạch, vì đây là việc cần dọn
             chứ không phải sổ sách sai. --}}
        @if ($daChay && (! empty($thieuGiaVon) || ! empty($quenBamXong)))
            <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                <p class="mb-2 text-sm font-medium text-warning-600 dark:text-warning-400">⚠️ Việc cần dọn (không phải lỗi sổ sách):</p>

                @if (! empty($thieuGiaVon))
                    <div class="mb-3">
                        <h4 class="mb-1 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Bán lúc kho âm — chưa biết giá vốn</h4>
                        <ul class="list-inside list-disc text-sm">
                            @foreach ($thieuGiaVon as $dong)
                                <li>{{ $dong['ingredient_name'] }}: {{ $dong['so_dong'] }} dòng</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! empty($quenBamXong))
                    <div>
                        <h4 class="mb-1 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Đã tính tiền mà bếp chưa bấm xong</h4>
                        <ul class="list-inside list-disc text-sm">
                            @foreach ($quenBamXong as $dong)
                                <li>#{{ $dong['order_item_id'] }} {{ $dong['product_name'] }} — {{ $dong['variant_name'] }} (lượt khách {{ $dong['table_session_code'] }}, đóng {{ $dong['closed_at'] }})</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
