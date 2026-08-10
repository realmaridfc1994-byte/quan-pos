<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Inventory\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;

final class ReceivePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('purchase');

        return $purchase instanceof Purchase && $this->user()->can('receive', $purchase);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
