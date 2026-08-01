<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\TableSession;
use Illuminate\Foundation\Http\FormRequest;

final class CalculateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tableSession = $this->route('tableSession');

        return $tableSession instanceof TableSession && $this->user()->can('requestDiscount', $tableSession);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'discount_amount' => ['required', 'integer', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'approver_pin' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'discount_amount.min' => 'Số tiền giảm giá không được âm.',
        ];
    }
}
