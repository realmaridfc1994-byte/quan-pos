<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\AcknowledgeOrphanMovementData;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReconciliationNote;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Xác nhận một dòng sổ cái mồ côi: "đã xem, không phải lỗi sổ sách".
 *
 * Sổ cái KHÔNG đổi một chữ nào — Action này chỉ ghi thêm một dòng ghi chú
 * (xem StockReconciliationNote). Từ sau lúc đó, lệnh stock:doi-soat vẫn liệt
 * kê dòng ấy nhưng thôi tính nó vào số lỗi và thôi làm lệnh trả về mã lỗi.
 *
 * Chỉ chủ quán và thu ngân được xác nhận — cùng nhóm quyền quản lý kho như
 * WriteOffStock và PurchasePolicy. Bắt buộc ghi rõ lý do: một dòng được tha
 * mà không nói vì sao thì ba tháng sau không ai dựng lại được câu chuyện.
 */
final class AcknowledgeOrphanMovement
{
    private const DO_DAI_TOI_THIEU = 5;

    public function handle(AcknowledgeOrphanMovementData $data): StockReconciliationNote
    {
        $nguoiXacNhan = User::query()->findOrFail($data->acknowledgedByUserId);

        if (! in_array($nguoiXacNhan->role, [UserRole::Owner, UserRole::Cashier], true)) {
            throw new DomainException('Chỉ chủ quán hoặc thu ngân được xác nhận dòng sổ cái mồ côi.');
        }

        $note = trim($data->note);
        $reason = trim($data->reason);

        if (mb_strlen($note) < self::DO_DAI_TOI_THIEU) {
            throw new DomainException('Phải ghi rõ đã xem thấy gì, ít nhất '.self::DO_DAI_TOI_THIEU.' ký tự.');
        }

        if (mb_strlen($reason) < self::DO_DAI_TOI_THIEU) {
            throw new DomainException('Phải ghi rõ vì sao dòng này không phải lỗi, ít nhất '.self::DO_DAI_TOI_THIEU.' ký tự.');
        }

        return DB::connection('tenant')->transaction(function () use ($data, $note, $reason): StockReconciliationNote {
            $movement = StockMovement::query()->findOrFail($data->stockMovementId);

            // Xác nhận hai lần: trả về đúng ghi chú cũ, không ghi đè lý do của
            // người trước. Cùng khuôn chống bấm hai lần ở RecordPayment.
            $daCo = StockReconciliationNote::query()
                ->where('stock_movement_id', $movement->id)
                ->first();

            if ($daCo !== null) {
                return $daCo;
            }

            return StockReconciliationNote::query()->create([
                'stock_movement_id' => $movement->id,
                'note' => $note,
                'reason' => $reason,
                'acknowledged_by_user_id' => $data->acknowledgedByUserId,
                'acknowledged_at' => now(),
            ]);
        });
    }
}
