<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MaBanCongKhai;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/guest/sessions — khách quét mã QR trên bàn, đổi lấy token.
 *
 * Đường này CÔNG KHAI, không cần đăng nhập: khách bước vào quán không có tài
 * khoản nào cả. Chốt chặn không nằm ở việc đăng nhập mà nằm ở chỗ server chỉ
 * cấp token khi bàn đó ĐANG CÓ KHÁCH NGỒI THẬT.
 */
final class StoreGuestSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ma_ban' => ['required', 'string', 'size:'.MaBanCongKhai::DO_DAI, 'alpha_num'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ma_ban.required' => 'Thiếu mã bàn. Vui lòng quét lại mã QR trên bàn.',
            'ma_ban.size' => 'Mã bàn không đúng. Vui lòng quét lại mã QR trên bàn.',
            'ma_ban.alpha_num' => 'Mã bàn không đúng. Vui lòng quét lại mã QR trên bàn.',
        ];
    }
}
