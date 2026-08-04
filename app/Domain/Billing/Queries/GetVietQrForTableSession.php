<?php

declare(strict_types=1);

namespace App\Domain\Billing\Queries;

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Exceptions\DomainException;
use App\Support\Money;
use App\Support\VietQr;

/**
 * Dựng mã QR chuyển khoản cho đúng số tiền CÒN THIẾU của một lượt khách —
 * Phase 2 Bước 7. Chỉ đọc, không ghi gì — xác nhận đã chuyển khoản vẫn đi
 * qua đúng RecordPayment như cũ (method=transfer), file này không tạo phiếu
 * thu nào.
 */
final class GetVietQrForTableSession
{
    /** @return array{payload: string, amount: int, table_session_code: string, account_name: string, bank_bin: string} */
    public function handle(TableSession $tableSession): array
    {
        if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
            throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không sinh mã QR được.');
        }

        $conThieu = Money::fromInt($tableSession->total_amount)->minus(Money::fromInt($tableSession->paid_amount));

        if ($conThieu->isZero()) {
            throw new DomainException('Lượt khách này đã thu đủ tiền, không cần chuyển khoản thêm.');
        }

        $bankBin = config('vietqr.bank_bin');
        $accountNumber = config('vietqr.account_number');
        $accountName = config('vietqr.account_name');

        if (blank($bankBin) || blank($accountNumber) || blank($accountName)) {
            throw new DomainException('Chưa cấu hình tài khoản ngân hàng của quán (VIETQR_BANK_BIN/VIETQR_ACCOUNT_NUMBER/VIETQR_ACCOUNT_NAME trong .env).');
        }

        $payload = VietQr::build(
            bankBin: $bankBin,
            accountNumber: $accountNumber,
            accountName: $accountName,
            amountVnd: $conThieu->amount,
            purpose: $tableSession->code,
        );

        return [
            'payload' => $payload,
            'amount' => $conThieu->amount,
            'table_session_code' => $tableSession->code,
            'account_name' => $accountName,
            'bank_bin' => $bankBin,
        ];
    }
}
