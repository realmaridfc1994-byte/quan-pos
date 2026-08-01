<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Đã qua middleware auth:sanctum + active — ai đăng nhập cũng xem được thực đơn.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'updated_since' => ['nullable', 'date'],
        ];
    }
}
