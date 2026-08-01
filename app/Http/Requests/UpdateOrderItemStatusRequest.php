<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrderItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', OrderItem::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
