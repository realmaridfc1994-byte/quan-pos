<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\TableSession;
use Illuminate\Foundation\Http\FormRequest;

final class AttachTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tableSession = $this->route('tableSession');

        return $tableSession instanceof TableSession && $this->user()->can('attachTable', $tableSession);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dining_table_id' => ['required', 'integer', 'exists:dining_tables,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dining_table_id.required' => 'Phải chọn bàn cần ghép.',
        ];
    }
}
