<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'tendered_amount' => ['required_if:method,cash', 'nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'uuid.required' => 'Thiếu mã vân tay của phiếu thu.',
            'amount.min' => 'Số tiền thu phải lớn hơn 0.',
            'tendered_amount.required_if' => 'Thu tiền mặt phải ghi số tiền khách đưa.',
        ];
    }

    /** Chuẩn hoá: chuyển khoản thì không có "tiền khách đưa" (ràng buộc ck_payments_cash). */
    protected function prepareForValidation(): void
    {
        if ($this->input('method') === PaymentMethod::Transfer->value) {
            $this->merge(['tendered_amount' => null]);
        }
    }
}
