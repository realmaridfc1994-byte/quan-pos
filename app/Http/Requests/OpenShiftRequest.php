<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Staffing\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;

final class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('open', Shift::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'opening_cash' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'opening_cash.required' => 'Phải nhập tiền lẻ có sẵn trong két (có thể là 0).',
        ];
    }
}
