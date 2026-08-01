<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Billing\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

final class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');

        return $payment instanceof Payment && $this->user()->can('void', $payment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Phải ghi rõ lý do huỷ phiếu thu.',
        ];
    }
}
