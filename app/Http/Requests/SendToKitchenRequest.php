<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

final class SendToKitchenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send', Order::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
