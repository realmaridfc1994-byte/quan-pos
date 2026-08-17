<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\CancelPurchaseData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Purchase;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Huỷ một phiếu nhập CHỈ khi còn draft. Phiếu đã received đã đưa hàng vào kho
 * thật — huỷ phiếu không phải cách lấy hàng ra.
 *
 * Sửa 12/08 (review Phase 3 Bước 10): câu thông báo cũ chỉ người dùng sang
 * "nghiệp vụ trả hàng nhà cung cấp (Bước 6)" — nghiệp vụ đó KHÔNG TỒN TẠI.
 * Loại `return` có trong enum, có ràng buộc ở database, có test ở tầng
 * RecordStockMovement, nhưng không Action nào và không màn hình nào sinh ra nó.
 * Chỉ đường tới một cánh cửa không có thật còn tệ hơn nói thẳng là chưa có.
 * Xem docs/viec-ton.md.
 */
final class CancelPurchase
{
    public function handle(CancelPurchaseData $data): Purchase
    {
        if (trim($data->reason) === '') {
            throw new DomainException('Phải ghi lý do khi huỷ phiếu nhập.');
        }

        return DB::connection('tenant')->transaction(function () use ($data): Purchase {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($data->purchaseId);

            if ($purchase->status === PurchaseStatus::Received) {
                throw new DomainException(
                    'Phiếu nhập đã nhận hàng vào kho, không huỷ được nữa. '.
                    'Hàng nhập nhầm thì chủ quán điều chỉnh tồn kho bằng tay (có ghi lý do và mã PIN).'
                );
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
