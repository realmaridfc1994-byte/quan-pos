<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\TableSession;
use Illuminate\Foundation\Http\FormRequest;

final class VoidTableSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tableSession = $this->route('tableSession');

        return $tableSession instanceof TableSession && $this->user()->can('void', $tableSession);
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
            'reason.required' => 'Phải ghi rõ lý do huỷ lượt khách.',
        ];
    }
}
