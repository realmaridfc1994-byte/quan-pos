<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PinVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Chỉ cần đã đăng nhập (middleware auth:sanctum) — ai cũng được phép hỏi "PIN này đúng không".
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pin' => ['required', 'string'],
        ];
    }
}
