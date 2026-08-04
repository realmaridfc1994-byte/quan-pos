<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Sync\Enums\OperationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $loaiThaoTac = array_map(fn (OperationType $t) => $t->value, OperationType::cases());

        return [
            'device_id' => ['required', 'string', 'max:50'],
            'batch_uuid' => ['required', 'uuid'],
            'client_time' => ['required', 'date'],
            'operations' => ['required', 'array', 'max:200'],
            'operations.*.op_uuid' => ['required', 'uuid', 'distinct'],
            'operations.*.type' => ['required', Rule::in($loaiThaoTac)],
            'operations.*.occurred_at' => ['required', 'date'],
            'operations.*.depends_on' => ['nullable', 'array'],
            'operations.*.depends_on.*' => ['uuid'],
            'operations.*.payload' => ['required', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'device_id.required' => 'Thiếu mã thiết bị.',
            'batch_uuid.required' => 'Thiếu mã vân tay của gói.',
            'operations.required' => 'Gói rỗng, không có thao tác nào để đồng bộ.',
            'operations.max' => 'Một gói tối đa 200 thao tác.',
            'operations.*.op_uuid.distinct' => 'Có thao tác trùng mã vân tay trong cùng một gói.',
        ];
    }
}
