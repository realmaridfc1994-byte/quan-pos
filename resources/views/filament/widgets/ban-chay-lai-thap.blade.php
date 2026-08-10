<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Bán chạy nhưng lãi thấp — tháng {{ $thang }}
        </x-slot>
        <x-slot name="description">
            Bán trên mức trung bình nhưng tỉ lệ lãi dưới 15% — nhóm cần xem lại giá bán hoặc định lượng.
        </x-slot>

        @if (empty($monCanChuY))
            <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có món nào rơi vào nhóm này tháng này.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Món</th>
                            <th class="py-2 pr-4 text-right">Số lượng bán</th>
                            <th class="py-2 pr-4 text-right">Doanh thu</th>
                            <th class="py-2 text-right">Tỉ lệ lãi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monCanChuY as $dong)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium">{{ $dong['product_name'] }} — {{ $dong['variant_name'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $dong['quantity_sold'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $dong['revenue_amount_text'] }}</td>
                                <td class="py-2 text-right text-warning-600">{{ $dong['margin_text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
