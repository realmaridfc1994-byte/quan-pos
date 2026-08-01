<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

final class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.option_ids' => ['nullable', 'array'],
            'items.*.option_ids.*' => ['integer', 'exists:options,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'uuid.required' => 'Thiếu mã vân tay của phiếu gọi món.',
            'items.required' => 'Phải gọi ít nhất một món.',
            'items.min' => 'Phải gọi ít nhất một món.',
        ];
    }
}
