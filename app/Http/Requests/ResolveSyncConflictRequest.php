<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Staffing\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class ResolveSyncConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, [UserRole::Owner, UserRole::Cashier], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dismiss' => ['required', 'boolean'],
            'resolution' => ['required_if:dismiss,false', 'nullable', 'string', 'max:60'],
            'note' => ['required', 'string'],
            'dining_table_ids' => ['sometimes', 'array'],
            'dining_table_ids.*' => ['integer', 'exists:dining_tables,id'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'approver_pin' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resolution.required_if' => 'Phải chọn một phương án xử lý.',
            'note.required' => 'Phải ghi rõ lý do quyết định.',
        ];
    }
}
