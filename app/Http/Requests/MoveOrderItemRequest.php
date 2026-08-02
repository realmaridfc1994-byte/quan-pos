<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\TableSession;
use Illuminate\Foundation\Http\FormRequest;

final class MoveOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tableSession = $this->route('tableSession');

        return $tableSession instanceof TableSession && $this->user()->can('moveItems', $tableSession);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target_table_session_id' => ['required', 'integer', 'exists:table_sessions,id'],
            'order_item_ids' => ['required', 'array', 'min:1'],
            'order_item_ids.*' => ['integer', 'distinct', 'exists:order_items,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'target_table_session_id.required' => 'Phải chọn lượt khách đích.',
            'order_item_ids.required' => 'Phải chọn ít nhất một dòng món để chuyển.',
            'order_item_ids.min' => 'Phải chọn ít nhất một dòng món để chuyển.',
            'order_item_ids.*.distinct' => 'Một dòng món không được chọn hai lần.',
        ];
    }
}
