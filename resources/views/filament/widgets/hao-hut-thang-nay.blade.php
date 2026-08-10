<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Hao hụt theo nguyên liệu — tháng {{ $thang }}
        </x-slot>

        @if (empty($haoHut))
            <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có hao hụt nào ghi nhận.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Nguyên liệu</th>
                            <th class="py-2 pr-4 text-right">Số lượng hao hụt</th>
                            <th class="py-2 pr-4 text-right">Giá trị tháng này</th>
                            <th class="py-2 pr-4 text-right">Tháng trước</th>
                            <th class="py-2 text-right">Chênh lệch</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($haoHut as $dong)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium">{{ $dong['ingredient_name'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $dong['waste_qty'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $dong['waste_cost_text'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $dong['waste_cost_thang_truoc_text'] }}</td>
                                <td class="py-2 text-right {{ $dong['chenh_lech'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                    {{ $dong['chenh_lech'] > 0 ? '+' : '' }}{{ number_format($dong['chenh_lech']) }} đ
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
