<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\TableSession;
use Illuminate\Foundation\Http\FormRequest;

final class TransferTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tableSession = $this->route('tableSession');

        return $tableSession instanceof TableSession && $this->user()->can('transferTable', $tableSession);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from_dining_table_id' => ['required', 'integer', 'exists:dining_tables,id'],
            'to_dining_table_id' => ['required', 'integer', 'exists:dining_tables,id', 'different:from_dining_table_id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from_dining_table_id.required' => 'Phải chọn bàn hiện tại.',
            'to_dining_table_id.required' => 'Phải chọn bàn muốn chuyển sang.',
            'to_dining_table_id.different' => 'Bàn mới phải khác bàn hiện tại.',
        ];
    }
}
