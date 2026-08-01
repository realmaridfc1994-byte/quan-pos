<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Staffing\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;

final class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $shift instanceof Shift && $this->user()->can('close', $shift);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'counted_cash.required' => 'Phải nhập số tiền mặt đếm thực tế trong két.',
        ];
    }
}
