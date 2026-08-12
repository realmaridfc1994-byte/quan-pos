<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\DTO\WriteOffStockData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\ApprovalPinRequiredException;
use App\Exceptions\DomainException;
use App\Support\CauHinhQuan;
use App\Support\Money;
use App\Support\StockCost;
use Illuminate\Support\Facades\DB;

/**
 * Ghi hao hụt: vỡ/hỏng, hết hạn, hao hụt tự nhiên, dùng nội bộ.
 *
 * Chỉ chủ quán và thu ngân được ghi (staff/kitchen không quản lý kho, giống
 * quy tắc ở PurchasePolicy). Loại hao hụt (WasteReasonCategory) chỉ tồn tại
 * ở tầng code — schema.md chốt stock_movements.type không có chỗ riêng cho
 * từng loại, nên ghép thành tiền tố của reason (xem ghepLyDo()).
 *
 * Luôn ghi type = 'waste' (K5/K7 không áp dụng ở đây vì không có ref tới
 * order_items — dùng ref_type = manual).
 *
 * ── DUYỆT PIN THEO NGƯỠNG GIÁ TRỊ (quyết định 10/08) ─────────────────────
 * Hao hụt là đường rút hàng ra khỏi kho mà không cần bán, nên phải có chốt.
 * Nhưng bắt PIN mọi lần thì nhân viên ngừng ghi — và hao hụt KHÔNG ghi còn
 * tệ hơn hao hụt ghi mà không ai duyệt, vì nó biến thành chênh lệch kiểm kê
 * không rõ nguyên nhân ba tháng sau. Nên chốt đặt theo GIÁ TRỊ TIỀN của lô
 * hao hụt, không theo số lượng (5 lon bia và 5 con cua đều là "5"):
 *
 *   - Dưới ngưỡng (mặc định 200.000đ/lần): thu ngân tự ghi, chỉ cần lý do.
 *   - Từ ngưỡng trở lên: bắt buộc chủ quán duyệt bằng PIN.
 *   - Chủ quán tự ghi thì không bao giờ cần PIN, mức nào cũng vậy.
 *
 * Ngưỡng chỉnh được trên Filament — xem App\Support\CauHinhQuan.
 *
 * CLAUDE.md mục 12: PIN xác thực xong TRƯỚC khi mở DB::transaction.
 *
 * ── HAI LỚP CHẶN, GIỐNG CHỖ GIẢM GIÁ (bổ sung 11/08) ─────────────────────
 * Lớp 1 — NGOÀI giao dịch: ƯỚC TÍNH giá trị lô bằng cách đọc stock_balances
 * KHÔNG KHOÁ (đúng cách ResolveSyncConflict::duyetPinTruocGiaoDich() làm với
 * ngưỡng giảm giá), vượt ngưỡng thì đòi PIN ngay từ đầu.
 *
 * Lớp 2 — TRONG giao dịch: sau khi sổ cái đã ghi, tính lại bằng số tiền THẬT
 * đã chốt (cost_delta). Nếu số thật vượt ngưỡng mà lô này chưa có ai duyệt
 * thì NÉM LỖI — cả giao dịch quay lui, không dòng sổ cái nào ở lại, tồn kho
 * không đổi. Người dùng nhận thông báo bảo nhập PIN rồi bấm lại.
 *
 * Vì sao lớp 2 không vi phạm luật "không hỏi PIN giữa giao dịch": nó KHÔNG
 * hỏi PIN, nó TỪ CHỐI. Giao dịch đóng lại ngay, không giữ khoá chờ ai nhập gì.
 *
 * Lỗ mà lớp 2 bịt: giá vốn trung bình có thể nhích giữa lúc ước tính và lúc
 * ghi sổ (có món bán ra xen vào), nên một lô ước tính 190.000đ có thể chốt
 * thật thành 210.000đ và lọt qua ngưỡng 200.000đ nếu chỉ có lớp 1.
 */
final class WriteOffStock
{
    /**
     * Ràng buộc ck_stock_movements_waste_reason đòi reason dài ≥ 5 ký tự (K15).
     *
     * Đếm trên PHẦN NGƯỜI DÙNG GÕ, không trên chuỗi đã ghép tiền tố. Trước đây
     * code chỉ chặn chuỗi rỗng, và "x" một ký tự vẫn qua được database CHỈ NHỜ
     * tiền tố "[Vỡ/hỏng] " kéo dài chuỗi lên — an toàn nhờ may mắn, không nhờ
     * thiết kế. Đổi tên loại hao hụt cho ngắn lại là ràng buộc nổ lỗi database
     * thô ngay trước mặt thu ngân.
     */
    private const DO_DAI_LY_DO_TOI_THIEU = 5;

    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
        private readonly VerifyApproverPin $verifyApproverPin,
        private readonly CauHinhQuan $cauHinhQuan,
    ) {}

    public function handle(WriteOffStockData $data): StockMovement
    {
        $nguoiGhi = User::query()->findOrFail($data->createdByUserId);

        if (! in_array($nguoiGhi->role, [UserRole::Owner, UserRole::Cashier], true)) {
            throw new DomainException('Chỉ chủ quán hoặc thu ngân được ghi hao hụt.');
        }

        if ($data->qty < 1) {
            throw new DomainException('Số lượng hao hụt phải lớn hơn 0.');
        }

        $chiTiet = trim($data->detail);
        if (mb_strlen($chiTiet) < self::DO_DAI_LY_DO_TOI_THIEU) {
            throw new DomainException(
                'Phải ghi rõ lý do hao hụt, ít nhất '.self::DO_DAI_LY_DO_TOI_THIEU.' ký tự.'
            );
        }

        // Bấm lưu hai lần vì mạng lag: trả về đúng dòng cũ NGAY, trước cả bước
        // hỏi PIN — nếu để sau, mỗi lần bấm lại đẻ thêm một dòng 'pin-verify'
        // trong nhật ký hoạt động dù chẳng ghi thêm gì. Đọc không khoá, ngoài
        // giao dịch; RecordStockMovement vẫn kiểm lại BÊN TRONG giao dịch, đó
        // mới là chốt chặn thật.
        $daGhi = StockMovement::query()->where('uuid', $data->uuid)->first();
        if ($daGhi !== null) {
            return $daGhi;
        }

        $nguoiDuyet = $this->duyetPinTruocGiaoDich($data, $nguoiGhi);

        // Lô đi qua lớp 1 mà KHÔNG có ai duyệt thì phải kiểm lại bằng số thật
        // ở lớp 2. Chủ quán tự ghi và lô đã có PIN duyệt thì khỏi kiểm.
        $canKiemLai = $nguoiGhi->role !== UserRole::Owner && $nguoiDuyet === null;

        return DB::transaction(function () use ($data, $chiTiet, $nguoiDuyet, $canKiemLai): StockMovement {
            $movement = $this->recordStockMovement->handle(new RecordStockMovementData(
                uuid: $data->uuid,
                ingredientId: $data->ingredientId,
                type: StockMovementType::Waste,
                qtyDelta: -$data->qty,
                knownCost: null,
                refType: StockMovementRefType::Manual,
                refId: null,
                reason: $this->ghepLyDo($data->category, $chiTiet),
                approvedByUserId: $nguoiDuyet?->id,
                createdByUserId: $data->createdByUserId,
                shiftId: $data->shiftId,
            ));

            // Chỉ kiểm lô VỪA ghi. Dòng cũ trả về do gửi trùng đã hợp lệ từ lần
            // ghi đầu rồi — kiểm lại chỉ làm lần bấm lại bị chặn vô cớ.
            if ($canKiemLai && $movement->wasRecentlyCreated) {
                $this->kiemLaiBangSoThat($movement);
            }

            return $movement;
        });
    }

    /**
     * Lớp chặn thứ hai — chạy BÊN TRONG giao dịch, trên số tiền THẬT đã chốt
     * vào sổ cái. Vượt ngưỡng mà chưa ai duyệt thì từ chối và quay lui tất cả.
     */
    private function kiemLaiBangSoThat(StockMovement $movement): void
    {
        $giaTriThat = Money::fromInt(abs($movement->cost_delta));

        if ($giaTriThat->isAtLeast($this->cauHinhQuan->nguongHaoHutCanPin())) {
            throw new ApprovalPinRequiredException(
                'Giá trị lô hàng vừa thay đổi, cần mã PIN chủ quán. Vui lòng nhập PIN và thử lại.'
            );
        }
    }

    /**
     * Ước tính giá trị lô hao hụt, và nếu vượt ngưỡng thì xác thực PIN chủ
     * quán NGAY TẠI ĐÂY — trước mọi transaction. Trả về người duyệt để ghi
     * vào sổ cái, hoặc null khi lô này không cần duyệt.
     */
    private function duyetPinTruocGiaoDich(WriteOffStockData $data, User $nguoiGhi): ?User
    {
        // Chủ quán tự ghi thì không cần ai duyệt hộ mình, mức nào cũng vậy.
        if ($nguoiGhi->role === UserRole::Owner) {
            return null;
        }

        $coPin = $data->approverUserId !== null && $data->approverPin !== null;

        if (! $coPin) {
            $giaTri = $this->uocTinhGiaTri($data->ingredientId, $data->qty);
            $nguong = $this->cauHinhQuan->nguongHaoHutCanPin();

            if (! $giaTri->isAtLeast($nguong)) {
                return null;
            }

            throw new ApprovalPinRequiredException(
                "Lô hao hụt này đáng giá {$giaTri->format()}, từ {$nguong->format()} trở lên phải có chủ quán duyệt bằng mã PIN."
            );
        }

        // Đã nhập PIN thì LUÔN xác thực, kể cả khi ước tính đang dưới ngưỡng:
        // đây chính là lần bấm lại sau khi lớp chặn thứ hai từ chối, lúc đó
        // ước tính vẫn có thể ra số dưới ngưỡng.

        $nguoiDuyet = $this->verifyApproverPin->handle(new PinVerifyData(
            userId: $data->approverUserId,
            pin: $data->approverPin,
            requestedByUserId: $data->createdByUserId,
        ));

        if ($nguoiDuyet->role !== UserRole::Owner) {
            throw new ApprovalPinRequiredException('Chỉ chủ quán được duyệt hao hụt vượt ngưỡng.');
        }

        return $nguoiDuyet;
    }

    /**
     * Giá trị lô hao hụt theo giá vốn bình quân gia quyền hiện tại — cùng
     * công thức RecordStockMovement sẽ dùng để ghi sổ, chỉ khác là đọc không
     * khoá. Nguyên liệu chưa có dòng tồn nào thì giá trị bằng 0.
     */
    private function uocTinhGiaTri(int $ingredientId, int $qty): Money
    {
        $balance = StockBalance::query()->find($ingredientId);

        if ($balance === null) {
            return Money::zero();
        }

        return Money::fromInt(StockCost::giaVonXuat($balance->qty, $balance->total_cost, $qty)['cost']);
    }

    private function ghepLyDo(WasteReasonCategory $category, string $chiTiet): string
    {
        return "[{$category->label()}] {$chiTiet}";
    }
}
