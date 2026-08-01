<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', OrderItem::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
