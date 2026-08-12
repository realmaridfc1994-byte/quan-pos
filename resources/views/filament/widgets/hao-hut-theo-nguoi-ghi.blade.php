<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Hao hụt theo người ghi — tháng {{ $thang }}
        </x-slot>

        <x-slot name="description">
            Một người ghi hao hụt gấp nhiều lần người khác là điều đáng hỏi, kể cả khi mỗi lần chỉ vài chục nghìn.
        </x-slot>

        @if (empty($theoNguoi))
            <p class="text-sm text-gray-500 dark:text-gray-400">Tháng này chưa ai ghi hao hụt.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Người ghi</th>
                            <th class="py-2 pr-4 text-right">Số lần ghi</th>
                            <th class="py-2 pr-4 text-right">Tổng giá trị</th>
                            <th class="py-2 text-right">Trung bình mỗi lần</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($theoNguoi as $dong)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium">{{ $dong['user_name'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $dong['so_lan'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $dong['tong_cost_text'] }}</td>
                                <td class="py-2 text-right">{{ $dong['trung_binh_text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td class="py-2 pr-4">Cả quán</td>
                            <td class="py-2 pr-4 text-right">{{ array_sum(array_column($theoNguoi, 'so_lan')) }}</td>
                            <td class="py-2 pr-4 text-right">{{ $tongTatCaText }}</td>
                            <td class="py-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
