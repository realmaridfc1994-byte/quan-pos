<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Top 10 món bán chạy tuần này
        </x-slot>

        @if (empty($top10))
            <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu tuần này.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                            <th class="py-2 pr-4">#</th>
                            <th class="py-2 pr-4">Món</th>
                            <th class="py-2 pr-4 text-right">Số lượng</th>
                            <th class="py-2 text-right">Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($top10 as $i => $dong)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 text-gray-500">{{ $i + 1 }}</td>
                                <td class="py-2 pr-4 font-medium">{{ $dong['product_name'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $dong['quantity_sold'] }}</td>
                                <td class="py-2 text-right">{{ $dong['revenue_amount_text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
