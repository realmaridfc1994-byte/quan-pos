<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\TableSession;
use Illuminate\Foundation\Http\FormRequest;

final class SplitTableSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tableSession = $this->route('tableSession');

        return $tableSession instanceof TableSession && $this->user()->can('splitTableSession', $tableSession);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order_item_ids' => ['required', 'array', 'min:1'],
            'order_item_ids.*' => ['integer', 'distinct', 'exists:order_items,id'],
            'dining_table_ids' => ['required', 'array', 'min:1'],
            'dining_table_ids.*' => ['integer', 'distinct', 'exists:dining_tables,id'],
            'guest_count' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'order_item_ids.required' => 'Phải chọn ít nhất một dòng món để tách.',
            'order_item_ids.min' => 'Phải chọn ít nhất một dòng món để tách.',
            'order_item_ids.*.distinct' => 'Một dòng món không được chọn hai lần.',
            'dining_table_ids.required' => 'Phải gán ít nhất một bàn cho lượt khách mới.',
            'dining_table_ids.min' => 'Phải gán ít nhất một bàn cho lượt khách mới.',
            'dining_table_ids.*.distinct' => 'Một bàn không được chọn hai lần.',
            'guest_count.min' => 'Số khách phải lớn hơn 0.',
        ];
    }
}
