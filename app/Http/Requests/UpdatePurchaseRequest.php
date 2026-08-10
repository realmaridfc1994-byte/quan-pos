<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Inventory\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('purchase');

        return $purchase instanceof Purchase && $this->user()->can('update', $purchase);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'invoice_no' => ['nullable', 'string', 'max:50'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'lines.*.unit_name' => ['required', 'string', 'max:30'],
            'lines.*.qty_input' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.required' => 'Phiếu nhập phải có ít nhất một dòng nguyên liệu.',
            'lines.min' => 'Phiếu nhập phải có ít nhất một dòng nguyên liệu.',
            'lines.*.ingredient_id.distinct' => 'Không được có hai dòng cùng một nguyên liệu.',
        ];
    }
}
