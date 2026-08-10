<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\CancelPurchaseData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Purchase;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Huỷ một phiếu nhập CHỈ khi còn draft. Phiếu đã received đã đưa hàng vào
 * kho thật — muốn trả lại thì dùng nghiệp vụ trả hàng nhà cung cấp (Bước 6),
 * không phải huỷ phiếu nhập.
 */
final class CancelPurchase
{
    public function handle(CancelPurchaseData $data): Purchase
    {
        if (trim($data->reason) === '') {
            throw new DomainException('Phải ghi lý do khi huỷ phiếu nhập.');
        }

        return DB::transaction(function () use ($data): Purchase {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($data->purchaseId);

            if ($purchase->status === PurchaseStatus::Received) {
                throw new DomainException('Phiếu nhập đã nhận hàng vào kho, không huỷ được nữa.');
            }

            if ($purchase->status === PurchaseStatus::Cancelled) {
                throw new DomainException('Phiếu nhập này đã huỷ rồi.');
            }

            $purchase->update([
                'status' => PurchaseStatus::Cancelled,
                'cancel_reason' => $data->reason,
            ]);

            return $purchase;
        });
    }
}
