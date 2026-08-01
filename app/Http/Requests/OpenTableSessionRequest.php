<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\TableSession;
use Illuminate\Foundation\Http\FormRequest;

final class OpenTableSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('open', TableSession::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dining_table_ids' => ['required', 'array', 'min:1'],
            'dining_table_ids.*' => ['integer', 'distinct', 'exists:dining_tables,id'],
            'primary_dining_table_id' => ['required', 'integer'],
            'guest_count' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dining_table_ids.required' => 'Phải chọn ít nhất một bàn.',
            'dining_table_ids.min' => 'Phải chọn ít nhất một bàn.',
            'dining_table_ids.*.distinct' => 'Một bàn không được chọn hai lần.',
            'primary_dining_table_id.required' => 'Phải chọn bàn chính.',
            'guest_count.min' => 'Số khách phải lớn hơn 0.',
        ];
    }
}
