<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Lãi gộp theo món — tháng {{ $thang }}
        </x-slot>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <h3 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Xếp theo tổng lãi</h3>
                @if (empty($theoTong))
                    <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu tháng này.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                                    <th class="py-2 pr-4">Món</th>
                                    <th class="py-2 pr-4 text-right">SL</th>
                                    <th class="py-2 pr-4 text-right">Doanh thu</th>
                                    <th class="py-2 text-right">Lãi gộp</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($theoTong as $dong)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-2 pr-4 font-medium">{{ $dong['product_name'] }} — {{ $dong['variant_name'] }}</td>
                                        <td class="py-2 pr-4 text-right">{{ $dong['quantity_sold'] }}</td>
                                        <td class="py-2 pr-4 text-right">{{ $dong['revenue_amount_text'] }}</td>
                                        <td class="py-2 text-right {{ $dong['profit_amount'] < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $dong['profit_amount_text'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div>
                <h3 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Xếp theo tỉ lệ lãi</h3>
                @if (empty($theoTiLe))
                    <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu tháng này.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                                    <th class="py-2 pr-4">Món</th>
                                    <th class="py-2 pr-4 text-right">SL</th>
                                    <th class="py-2 text-right">Tỉ lệ lãi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($theoTiLe as $dong)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-2 pr-4 font-medium">{{ $dong['product_name'] }} — {{ $dong['variant_name'] }}</td>
                                        <td class="py-2 pr-4 text-right">{{ $dong['quantity_sold'] }}</td>
                                        <td class="py-2 text-right">{{ $dong['margin_text'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
