<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\RecordCashMovementData;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ghi một khoản thu chi vặt trong ca: "chi 200k mua đá", "chủ rút 1 triệu".
 */
final class RecordCashMovement
{
    public function handle(RecordCashMovementData $data): CashMovement
    {
        return DB::transaction(function () use ($data): CashMovement {
            $shift = Shift::query()->lockForUpdate()->findOrFail($data->shiftId);

            if ($shift->status !== ShiftStatus::Open) {
                throw new DomainException('Ca đã đóng, không ghi thu chi vặt được nữa.');
            }

            $lyDo = trim($data->reason);

            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do thu chi.');
            }

            return CashMovement::query()->create([
                'shift_id' => $shift->id,
                'direction' => $data->direction,
                'amount' => $data->amount->amount,
                'reason' => $lyDo,
                'created_by_user_id' => $data->createdByUserId,
                'occurred_at' => now(),
            ]);
        });
    }
}
