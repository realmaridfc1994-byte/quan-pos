<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Xác thực mã PIN của chủ quán/thu ngân để duyệt một hành động nhạy cảm
 * (ví dụ: hủy món đã phục vụ ra bàn — xem bất biến H5 ở docs/schema.md).
 *
 * Chống dò PIN bằng vét cạn: PIN chỉ 4-6 số nên phải tự khoá tạm người bị dò,
 * không thể chỉ dựa vào rate limit theo người GỌI (một người dò PIN của nhiều
 * người khác nhau vẫn né được rate limit theo IP/user gọi).
 */
final class VerifyApproverPin
{
    private const MAX_SAI_LIEN_TIEP = 5;

    private const KHOA_PHUT = 15;

    public function handle(PinVerifyData $data): User
    {
        $approver = User::query()->find($data->userId);

        if ($approver === null || ! $approver->is_active) {
            throw new DomainException('Người này không có quyền duyệt.');
        }

        if (! in_array($approver->role, [UserRole::Owner, UserRole::Cashier], true)) {
            throw new DomainException('Người này không có quyền duyệt.');
        }

        if ($approver->pin_code === null) {
            throw new DomainException('Người này chưa thiết lập mã PIN.');
        }

        $store = Cache::store('database');

        if ($store->has($this->khoaKeyCho($data->requestedByUserId, $approver->id))) {
            throw new DomainException('Bạn đã nhập sai mã PIN quá nhiều lần. Đợi 15 phút rồi thử lại.');
        }

        if (! Hash::check($data->pin, $approver->pin_code)) {
            $this->ghiNhanSaiVaKhoaNeuCan($store, $approver, $data->requestedByUserId);

            throw new DomainException('Mã PIN không đúng.');
        }

        $store->forget($this->demSaiKeyCho($data->requestedByUserId, $approver->id));

        return $approver;
    }

    private function ghiNhanSaiVaKhoaNeuCan(CacheRepository $store, User $approver, int $requestedByUserId): void
    {
        $demKey = $this->demSaiKeyCho($requestedByUserId, $approver->id);

        // add() + increment() thay vì get()+put(): hai request cùng lúc (race
        // condition) vẫn cộng đúng — increment() của driver database chạy trong
        // transaction có lockForUpdate(), không bị "đếm hụt" như đọc-rồi-ghi.
        // add() chỉ tạo key với hạn 15 phút nếu CHƯA có, không đụng key đã tồn tại.
        $store->add($demKey, 0, now()->addMinutes(self::KHOA_PHUT));
        $soLanSai = $store->increment($demKey);

        // Truyền thẳng Model thay vì int cho causedBy(): CauserResolver chỉ tra được
        // int qua guard hiện tại của request, guard đó không đảm bảo còn tồn tại
        // đúng lúc gọi (ví dụ gọi trực tiếp Action ngoài vòng đời HTTP như trong test).
        $nguoiGoi = User::query()->find($requestedByUserId);

        activity('pin-verify')
            ->causedBy($nguoiGoi)
            ->performedOn($approver)
            ->withProperties([
                'requested_by_user_id' => $requestedByUserId,
                'approver_user_id' => $approver->id,
                'so_lan_sai_lien_tiep' => $soLanSai,
            ])
            ->log('Thử mã PIN sai.');

        if ($soLanSai >= self::MAX_SAI_LIEN_TIEP) {
            $store->put($this->khoaKeyCho($requestedByUserId, $approver->id), true, now()->addMinutes(self::KHOA_PHUT));
            $store->forget($demKey);

            // Log riêng để chủ quán/thu ngân tra được AI đang dò PIN của mình,
            // tách khỏi log 'pin-verify' (từng lần sai) để dễ lọc sự kiện nghiêm trọng hơn.
            activity('pin-verify-khoa')
                ->causedBy($nguoiGoi)
                ->performedOn($approver)
                ->withProperties([
                    'requested_by_user_id' => $requestedByUserId,
                    'approver_user_id' => $approver->id,
                ])
                ->log('Khoá tạm: một người đã nhập sai PIN của người này quá nhiều lần liên tiếp.');
        }
    }

    private function demSaiKeyCho(int $requestedByUserId, int $approverId): string
    {
        return "pin-verify-sai:{$requestedByUserId}:{$approverId}";
    }

    private function khoaKeyCho(int $requestedByUserId, int $approverId): string
    {
        return "pin-verify-khoa:{$requestedByUserId}:{$approverId}";
    }
}
