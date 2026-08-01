<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $shift instanceof Shift && $this->user()->can('recordCashMovement', $shift);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(CashDirection::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Phải ghi rõ lý do thu chi.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
        ];
    }
}
