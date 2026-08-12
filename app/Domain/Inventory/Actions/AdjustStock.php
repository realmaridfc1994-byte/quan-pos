<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\AdjustStockData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

/**
 * Điều chỉnh tay tồn kho khi biết chắc số liệu sai (ví dụ đếm lại thấy lệch
 * ngoài luồng kiểm kê chính thức — kiểm kê thật là Bước 7, chưa làm).
 *
 * CHỈ chủ quán được thực hiện VÀ chỉ chủ quán được duyệt bằng PIN — nhạy cảm
 * hơn hao hụt (WriteOffStock) vì đây là ghi đè số liệu bằng tay, không có
 * chứng từ gốc (hoá đơn nhập, dòng món bán) đi kèm.
 *
 * CLAUDE.md mục 12: PIN phải xác thực xong TRƯỚC khi mở DB::transaction —
 * VerifyApproverPin chạy trước, RecordStockMovement (tự mở transaction
 * riêng) chạy sau.
 *
 * StockMovement được dùng ở đây CHỈ để ĐỌC (tra mã vân tay xem đã ghi chưa) —
 * mọi việc ghi sổ cái vẫn đi qua RecordStockMovement, một cửa duy nhất.
 *
 * Ghi type = 'adjust' — TÁCH RIÊNG khỏi 'waste' để Bước 8 báo cáo lọc được
 * chính xác qua cột type có sẵn (ck_stock_movements_adjust bắt buộc DB có
 * approved_by_user_id và reason ≥ 10 ký tự — K12).
 */
final class AdjustStock
{
    private const DO_DAI_LY_DO_TOI_THIEU = 10;

    public function __construct(
        private readonly VerifyApproverPin $verifyApproverPin,
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(AdjustStockData $data): StockMovement
    {
        $nguoiThucHien = User::query()->findOrFail($data->requestedByUserId);
        if ($nguoiThucHien->role !== UserRole::Owner) {
            throw new DomainException('Chỉ chủ quán được điều chỉnh tồn kho tay.');
        }

        if ($data->qtyDelta === 0) {
            throw new DomainException('Số lượng điều chỉnh không được bằng 0.');
        }

        $lyDo = trim($data->reason);
        if (mb_strlen($lyDo) < self::DO_DAI_LY_DO_TOI_THIEU) {
            throw new DomainException('Lý do điều chỉnh phải ghi rõ ràng, tối thiểu '.self::DO_DAI_LY_DO_TOI_THIEU.' ký tự — không được ghi qua loa.');
        }

        // Bấm lại lần hai với cùng mã vân tay: trả về đúng dòng cũ NGAY, trước
        // cả bước hỏi PIN. Nếu để sau, mỗi lần bấm lại đẻ thêm một dòng
        // 'pin-verify' trong nhật ký hoạt động dù chẳng có gì được ghi thêm.
        // Đọc không khoá, ngoài giao dịch — RecordStockMovement vẫn kiểm lại
        // lần nữa BÊN TRONG giao dịch, đó mới là chốt chặn thật.
        $daGhi = StockMovement::query()->where('uuid', $data->uuid)->first();
        if ($daGhi !== null) {
            return $daGhi;
        }

        $nguoiDuyet = $this->verifyApproverPin->handle(new PinVerifyData(
            userId: $data->approverUserId,
            pin: $data->approverPin,
            requestedByUserId: $data->requestedByUserId,
        ));

        if ($nguoiDuyet->role !== UserRole::Owner) {
            throw new DomainException('Chỉ chủ quán được duyệt điều chỉnh tồn kho tay.');
        }

        return $this->recordStockMovement->handle(new RecordStockMovementData(
            uuid: $data->uuid,
            ingredientId: $data->ingredientId,
            type: StockMovementType::Adjust,
            qtyDelta: $data->qtyDelta,
            knownCost: null,
            refType: StockMovementRefType::Manual,
            refId: null,
            reason: $lyDo,
            approvedByUserId: $nguoiDuyet->id,
            createdByUserId: $data->requestedByUserId,
            shiftId: $data->shiftId,
        ));
    }
}
