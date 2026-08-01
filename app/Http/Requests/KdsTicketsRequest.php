<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class KdsTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cùng tầng quyền với việc đổi trạng thái món trên KDS — ai xem được thì cũng thao tác được.
        return $this->user()->can('updateStatus', OrderItem::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'station' => ['required', Rule::in(['kitchen', 'bar'])],
        ];
    }
}
