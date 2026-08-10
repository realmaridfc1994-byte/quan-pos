<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Inventory\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;

final class CancelPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('purchase');

        return $purchase instanceof Purchase && $this->user()->can('cancel', $purchase);
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
            'reason.required' => 'Phải ghi lý do khi huỷ phiếu nhập.',
        ];
    }
}
