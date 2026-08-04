<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VietQrResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'payload' => $this->resource['payload'],
            'amount' => $this->resource['amount'],
            'amount_text' => Money::fromInt($this->resource['amount'])->format(),
            'table_session_code' => $this->resource['table_session_code'],
            'account_name' => $this->resource['account_name'],
            'bank_bin' => $this->resource['bank_bin'],
        ];
    }
}
