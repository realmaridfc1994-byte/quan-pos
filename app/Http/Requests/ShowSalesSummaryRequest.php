<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * GET /api/v1/reports/summary — Phase 4 Bước 4B.1.
 *
 * Ai đăng nhập cũng gọi được và thấy DOANH THU; phần lãi gộp/giá vốn bị
 * SalesSummaryResource cắt khỏi JSON nếu không có quyền `view-cost-profit`.
 * Cố ý KHÔNG chặn cả endpoint theo vai trò: chặn thì thu ngân mất luôn báo
 * cáo doanh thu vốn được phép xem (CLAUDE.md mục 1).
 */
final class ShowSalesSummaryRequest extends FormRequest
{
    /** Một cú gọi nhầm không được kéo cả kho dữ liệu lên. */
    private const SO_NGAY_TOI_DA = 366;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tu' => ['nullable', 'date_format:Y-m-d'],
            'den' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tu'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tu.date_format' => 'Ngày đầu phải theo dạng 2026-08-01.',
            'den.date_format' => 'Ngày cuối phải theo dạng 2026-08-31.',
            'den.after_or_equal' => 'Ngày cuối phải bằng hoặc sau ngày đầu.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            [$tu, $den] = $this->khoangNgay();

            if ((int) $tu->diffInDays($den) + 1 > self::SO_NGAY_TOI_DA) {
                $validator->errors()->add('den', 'Chỉ xem được tối đa '.self::SO_NGAY_TOI_DA.' ngày một lần.');
            }
        });
    }

    /**
     * Mặc định: từ đầu tháng này tới hôm nay.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function khoangNgay(): array
    {
        $den = $this->filled('den')
            ? Carbon::parse($this->string('den')->toString())->startOfDay()
            : Carbon::today();

        $tu = $this->filled('tu')
            ? Carbon::parse($this->string('tu')->toString())->startOfDay()
            : Carbon::today()->startOfMonth();

        return [$tu, $den];
    }
}
