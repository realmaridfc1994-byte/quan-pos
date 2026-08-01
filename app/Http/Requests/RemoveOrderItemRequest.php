<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;

final class RemoveOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('remove', OrderItem::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
