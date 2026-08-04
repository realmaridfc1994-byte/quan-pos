<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;

final class CancelOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', OrderItem::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'approver_pin' => ['nullable', 'string'],
            // Bắt buộc khi huỷ MỘT PHẦN (tách dòng mới) — Action tự kiểm tra
            // theo quy tắc nghiệp vụ (so quantity với dòng gốc), FormRequest
            // chỉ kiểm tra đúng hình dạng dữ liệu.
            'new_item_uuid' => ['nullable', 'uuid'],
            'option_uuids' => ['nullable', 'array'],
            'option_uuids.*' => ['uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Phải ghi rõ lý do huỷ món.',
            'quantity.min' => 'Số lượng huỷ phải lớn hơn 0.',
            'new_item_uuid.uuid' => 'Mã vân tay dòng món mới không đúng định dạng uuid.',
            'option_uuids.*.uuid' => 'Mã vân tay tuỳ chọn không đúng định dạng uuid.',
        ];
    }
}
