<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Xác thực mã PIN của chủ quán/quản lý để duyệt một hành động nhạy cảm
 * (ví dụ: hủy món đã phục vụ ra bàn — xem bất biến H5 ở docs/schema.md).
 */
final class VerifyManagerPin
{
    public function handle(PinVerifyData $data): User
    {
        $approver = User::query()->find($data->userId);

        if ($approver === null || ! $approver->is_active) {
            throw new DomainException('Người này không có quyền duyệt.');
        }

        if (! in_array($approver->role, [UserRole::Owner, UserRole::Manager], true)) {
            throw new DomainException('Người này không có quyền duyệt.');
        }

        if ($approver->pin_code === null) {
            throw new DomainException('Người này chưa thiết lập mã PIN.');
        }

        if (! Hash::check($data->pin, $approver->pin_code)) {
            throw new DomainException('Mã PIN không đúng.');
        }

        return $approver;
    }
}
