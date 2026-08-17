<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTake;
use App\Exceptions\DomainException;
use App\Exceptions\StockLedgerInvariantViolatedException;
use App\Support\StockCost;
use App\Support\StockMovementUuid;
use Illuminate\Support\Facades\DB;

/**
 * MỘT CỬA DUY NHẤT ghi vào stock_balances — xem docs/thiet-ke-gia-von.md mục 1.
 *
 * Nhập hàng, bán món, hỏng vỡ, kiểm kê, điều chỉnh, trả hàng nhà cung cấp —
 * tất cả đi qua Action này. Không Action nào khác được UPDATE/INSERT vào
 * stock_balances hay StockMovement::create() — xem tests/Feature/Inventory/
 * OnlyOneStockWriterTest.php. Luật này được khoá cứng ở tầng Model (xem
 * StockBalance::choPhepGhi()), không chỉ dựa vào quy ước đọc trên giấy.
 *
 * Gọi cho nhiều nguyên liệu trong một giao dịch: người GỌI (Action cha) phải
 * tự khoá/gọi theo ingredient_id TĂNG DẦN, giống quy tắc khoá nhiều bàn ở
 * CLAUDE.md mục 4.18. Action này chỉ khoá đúng một dòng stock_balances mỗi
 * lần handle().
 */
final class RecordStockMovement
{
    public function handle(RecordStockMovementData $data): StockMovement
    {
        if (trim($data->uuid) === '') {
            throw new DomainException('Thiếu mã vân tay của dòng sổ cái kho.');
        }

        return DB::connection('tenant')->transaction(function () use ($data): StockMovement {
            // Ghi hai lần vì mạng lag hoặc vì Action cha bị gọi lại: trả về
            // đúng dòng sổ cái cũ, KHÔNG ghi lần hai, không ném lỗi — cùng
            // khuôn RecordPayment. Đặt TRƯỚC lockForUpdate: đã có dòng rồi thì
            // không cần khoá dòng tồn của ai cả.
            $daCo = StockMovement::query()->where('uuid', $data->uuid)->first();
            if ($daCo !== null) {
                return $daCo;
            }

            $this->chanNeuDangKiemKe($data);

            return StockBalance::choPhepGhi(
                fn (): StockMovement => $this->ghiSoCaiVaCapNhatTon($data)
            );
        });
    }

    /**
     * K18 — KHO PHẢI ĐỨNG YÊN TRONG LÚC PHIẾU KIỂM KÊ ĐANG MỞ.
     *
     * Vì sao: OpenStockTake chụp tồn hệ thống vào stock_take_items.system_qty
     * lúc MỞ phiếu và đóng băng con số đó. CloseStockTake lấy
     * (số đếm − số chụp lúc mở) rồi cộng vào tồn HIỆN TẠI. Nếu kho nhúc nhích
     * giữa lúc mở phiếu và lúc nhân viên đếm tay, biến động đó đã nằm sẵn
     * trong con số đếm được, rồi bị cộng thêm một lần nữa lúc chốt.
     *
     * Đã dựng lại được: tồn 100 → mở phiếu → xe hàng tới nhập 20 (tồn 120) →
     * đếm tay thấy đúng 120 → chốt phiếu → hệ thống ghi 140. Không lỗi nào nổ,
     * và lệnh đối soát Bước 9 vẫn báo "sạch" vì sổ cái tự khớp với bảng tồn.
     * Chính công cụ dùng để phát hiện lệch kho lại đang tạo ra lệch kho.
     *
     * HAI NGOẠI LỆ, cả hai đều có chủ đích:
     *
     *  1. BÁN MÓN không bao giờ bị chặn (docs/schema.md K.9 — bếp không bao giờ
     *     bị chặn báo món xong vì lý do kho). Kho đứng yên ở nhánh này được giữ
     *     Ở ĐẦU TRÊN: OpenTableSession từ chối mở bàn mới khi còn phiếu kiểm kê
     *     đang mở, và OpenStockTake từ chối mở phiếu khi còn bàn chưa tính tiền
     *     xong. Hai chốt đó khép kín: không có bàn nào mở thì không có món nào
     *     để bấm xong.
     *  2. Chính CloseStockTake — nó ghi các dòng điều chỉnh TRONG LÚC phiếu vẫn
     *     còn "open", chỉ đổi trạng thái ở cuối. Xem StockTake::dangChotPhieu().
     */
    private function chanNeuDangKiemKe(RecordStockMovementData $data): void
    {
        if ($data->type === StockMovementType::Sale || StockTake::dangChotPhieu()) {
            return;
        }

        if (StockTake::dangCoPhieuMo()) {
            throw new DomainException(
                'Đang có phiếu kiểm kê mở — kho phải đứng yên cho tới khi chốt phiếu. '.
                'Chốt phiếu kiểm kê xong rồi làm việc này.'
            );
        }
    }

    private function ghiSoCaiVaCapNhatTon(RecordStockMovementData $data): StockMovement
    {
        $balance = StockBalance::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['ingredient_id' => $data->ingredientId],
                ['qty' => 0, 'total_cost' => 0]
            );

        $this->chotGiaVonHangDaBanLucKhoAm($data, $balance);

        [$costDelta, $hasCost] = $this->tinhGiaVon($data, $balance);

        return $this->ghiMotDong(
            data: $data,
            balance: $balance,
            uuid: $data->uuid,
            type: $data->type,
            qtyDelta: $data->qtyDelta,
            costDelta: $costDelta,
            hasCost: $hasCost,
            refType: $data->refType,
            refId: $data->refId,
            reason: $data->reason,
            approvedByUserId: $data->approvedByUserId,
        );
    }

    /**
     * NHẬP HÀNG BÙ CHO SỐ ĐÃ BÁN LÚC KHO ÂM (sửa 12/08, Bước 10).
     *
     * Kho được phép xuống âm — bếp không bao giờ bị chặn báo món xong. Lúc tồn
     * âm thì trị giá tồn bằng 0 và những phần bán ra mang has_cost = false:
     * hệ thống thành thật nói "chưa biết mấy phần này tốn bao nhiêu".
     *
     * Khi hàng về, một phần lô hàng là để TRẢ NỢ cho số đã bán đó. Hàng ấy đã
     * ra khỏi quán từ lâu, không được nằm lại trong kho. Trước đây toàn bộ tiền
     * lô hàng được cộng thẳng vào trị giá tồn, gây hai chuyện:
     *
     *   a. Nhập bù ĐÚNG BẰNG số đang thiếu: tồn về 0 mà trị giá còn tiền → van
     *      an toàn K9 nổ, cả phiếu nhập quay lui, KHÔNG NHẬN ĐƯỢC HÀNG. Chủ
     *      quán nhìn thấy câu "Lỗi lập trình" và không có đường nào đi tiếp.
     *   b. Nhập bù NHIỀU HƠN số đang thiếu: nhập 10 lon giá 500.000 (50.000/lon)
     *      → bán 12 → nhập 5 lon giá 300.000 (60.000/lon) → 3 lon còn lại mang
     *      trị giá 300.000, tức 100.000/lon. Ba lon bán tiếp theo bị tính giá
     *      vốn gấp rưỡi giá thật, lãi gộp của món đó thấp giả.
     *
     * Cách làm: ghi TRƯỚC một dòng sổ cái loại `close_residual` (qty_delta = 0,
     * cost_delta âm) mang đúng phần tiền trả nợ, rồi mới ghi dòng nhập với phần
     * tiền còn lại. Tiền không biến mất khỏi sổ — nó đi ra bằng cửa chính, có
     * một dòng đứng tên nó, tra ngược được. Đây chính là công dụng mà
     * docs/schema.md K.4 đã chừa sẵn cho loại `close_residual`.
     *
     * Vì sao ghi dòng trả nợ TRƯỚC chứ không phải sau: ghi sau thì dòng nhập
     * đưa tồn về đúng 0 với trị giá còn dương trong một khoảnh khắc, và van an
     * toàn K9 nổ ngay tại đó. Ghi trước thì tồn vẫn đang âm, mà trị giá âm lúc
     * tồn âm là hợp lệ (ck_stock_balances_cost chỉ đòi trị giá không âm khi
     * tồn dương).
     *
     * @return int số tiền đã chốt làm giá vốn hàng đã bán, 0 nếu không có
     */
    private function chotGiaVonHangDaBanLucKhoAm(RecordStockMovementData $data, StockBalance $balance): int
    {
        if ($data->type !== StockMovementType::Purchase || $data->knownCost === null || $balance->qty >= 0) {
            return 0;
        }

        $soBuNo = min(-$balance->qty, $data->qtyDelta);
        if ($soBuNo <= 0) {
            return 0;
        }

        $tienBuNo = StockCost::phanTienTheoSoLuong($data->knownCost, $soBuNo, $data->qtyDelta);
        if ($tienBuNo <= 0) {
            return 0;
        }

        $this->ghiMotDong(
            data: $data,
            balance: $balance,
            // Mã vân tay TẤT ĐỊNH sinh từ mã của chính dòng nhập — gửi lại lần
            // hai thì dòng nhập đã bị chặn ở cửa uuid từ trước, không bao giờ
            // chạy tới đây lần nữa.
            uuid: StockMovementUuid::tuChuoi("close_residual:{$data->uuid}"),
            type: StockMovementType::CloseResidual,
            qtyDelta: 0,
            costDelta: -$tienBuNo,
            hasCost: true,
            // Không dùng lại ref của dòng nhập: khoá uq_stock_movements_ref
            // gồm (ref_type, ref_id, ingredient_id) sẽ coi hai dòng đó là trùng.
            refType: StockMovementRefType::Manual,
            refId: null,
            reason: "Giá vốn {$soBuNo} đơn vị đã bán lúc kho âm, chốt khi nhập bù",
            approvedByUserId: null,
        );

        return $tienBuNo;
    }

    private function ghiMotDong(
        RecordStockMovementData $data,
        StockBalance $balance,
        string $uuid,
        StockMovementType $type,
        int $qtyDelta,
        int $costDelta,
        bool $hasCost,
        StockMovementRefType $refType,
        ?int $refId,
        ?string $reason,
        ?int $approvedByUserId,
    ): StockMovement {
        $qtyAfter = $balance->qty + $qtyDelta;
        $costAfter = $balance->total_cost + $costDelta;

        // Van an toàn K9 — nổ ra là lỗi lập trình trong tính giá vốn,
        // không phải lỗi dữ liệu người dùng nhập vào.
        if ($qtyAfter === 0 && $costAfter !== 0) {
            throw new StockLedgerInvariantViolatedException(
                "Lỗi lập trình (không phải lỗi dữ liệu): tồn nguyên liệu #{$data->ingredientId} về 0 mà trị giá còn {$costAfter} đồng."
            );
        }

        if ($qtyAfter > 0 && $costAfter < 0) {
            throw new StockLedgerInvariantViolatedException(
                "Lỗi lập trình (không phải lỗi dữ liệu): tồn nguyên liệu #{$data->ingredientId} dương ({$qtyAfter}) mà trị giá âm ({$costAfter} đồng)."
            );
        }

        // Ghi sổ cái TRƯỚC, cập nhật tồn SAU — sổ cái là sự thật, bảng
        // tồn là bản tóm tắt.
        $movement = StockMovement::query()->create([
            'uuid' => $uuid,
            'ingredient_id' => $data->ingredientId,
            'type' => $type,
            'qty_delta' => $qtyDelta,
            'cost_delta' => $costDelta,
            'qty_after' => $qtyAfter,
            'cost_after' => $costAfter,
            'has_cost' => $hasCost,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'reason' => $reason,
            'approved_by_user_id' => $approvedByUserId,
            'created_by_user_id' => $data->createdByUserId,
            'shift_id' => $data->shiftId,
            'occurred_at' => $data->occurredAt ?? now(),
        ]);

        $balance->update([
            'qty' => $qtyAfter,
            'total_cost' => $costAfter,
            'last_movement_id' => $movement->id,
            'updated_at' => now(),
        ]);

        return $movement;
    }

    /**
     * @return array{0: int, 1: bool} [costDelta, hasCost]
     */
    private function tinhGiaVon(RecordStockMovementData $data, StockBalance $balance): array
    {
        if ($data->qtyDelta > 0) {
            if ($data->type === StockMovementType::Purchase) {
                if ($data->knownCost === null) {
                    throw new DomainException('Nhập hàng phải ghi rõ số tiền thật trả nhà cung cấp.');
                }

                // Luôn cộng ĐỦ số tiền thật trả nhà cung cấp. Phần tiền dùng để
                // trả nợ cho hàng đã bán lúc kho âm (nếu có) đã được lấy ra
                // bằng một dòng `close_residual` riêng ghi TRƯỚC dòng này —
                // xem chotGiaVonHangDaBanLucKhoAm(). Trừ ở cả hai chỗ là trừ
                // hai lần.
                return [$data->knownCost, true];
            }

            // Kiểm kê thừa, điều chỉnh tăng: dùng giá trung bình hiện tại
            // (docs/thiet-ke-gia-von.md mục 5.1), không phải tiền thật.
            $ketQua = StockCost::giaVonNhapTheoTrungBinh($balance->qty, $balance->total_cost, $data->qtyDelta);

            return [$ketQua['cost'], $ketQua['has_cost']];
        }

        // Ra kho — bán, hỏng vỡ, kiểm kê thiếu, trả hàng, điều chỉnh giảm.
        $n = abs($data->qtyDelta);
        $ketQua = StockCost::giaVonXuat($balance->qty, $balance->total_cost, $n);

        return [-$ketQua['cost'], $ketQua['has_cost']];
    }
}
