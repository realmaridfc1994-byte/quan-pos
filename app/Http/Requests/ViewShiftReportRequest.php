<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Staffing\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;

final class ViewShiftReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $shift instanceof Shift && $this->user()->can('viewReport', $shift);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
