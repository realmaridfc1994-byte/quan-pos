<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Cảnh báo tồn thấp
        </x-slot>

        @if (empty($tonThap))
            <p class="text-sm text-gray-500 dark:text-gray-400">Không có nguyên liệu nào dưới mức cảnh báo.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Nguyên liệu</th>
                            <th class="py-2 pr-4 text-right">Tồn hiện tại</th>
                            <th class="py-2 text-right">Mức cảnh báo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tonThap as $dong)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium">{{ $dong['ingredient_name'] }}</td>
                                <td class="py-2 pr-4 text-right {{ $dong['qty'] < 0 ? 'text-danger-600 font-semibold' : 'text-warning-600' }}">{{ $dong['qty'] }}</td>
                                <td class="py-2 text-right">{{ $dong['min_qty'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
