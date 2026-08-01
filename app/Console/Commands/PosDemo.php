<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Actions\CancelOrderItem;
use App\Domain\Ordering\Actions\OpenTableSession;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\SendToKitchen;
use App\Domain\Ordering\Actions\UpdateOrderItemStatus;
use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\PlaceOrderItemData;
use App\Domain\Ordering\DTO\SendToKitchenData;
use App\Domain\Ordering\DTO\UpdateOrderItemStatusData;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Actions\OpenShift;
use App\Domain\Staffing\DTO\OpenShiftData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Diễn tập một ca bán hàng thật, in tiếng Việt từng mốc, để chủ quán tự mắt
 * thấy hệ thống chạy đúng mà không cần đọc code. Mỗi mốc in ra CON SỐ tự
 * kiểm được (mã ca, số tiền...), không chỉ in "thành công".
 *
 * Toàn bộ dữ liệu demo tạo ra được bọc trong một transaction và LUÔN rollback ở
 * cuối cùng (kể cả khi lỗi giữa chừng) — không có lệnh xoá nào cả, vì quy tắc
 * của dự án là không bao giờ xoá cứng dữ liệu giao dịch (shifts, ...).
 */
final class PosDemo extends Command
{
    protected $signature = 'pos:demo {--den=ca : Mốc dừng lại — "ca", "ban", "goi-mon", "gui-bep", "huy-mon" hoặc "thu-tien"}';

    protected $description = 'Diễn tập một ca bán hàng mẫu, dùng để tự kiểm tra bằng mắt (chỉ chạy ở môi trường local)';

    /** Tên mốc hiện tại — dùng để in "DỪNG Ở MỐC" đúng chỗ khi có lỗi. */
    private string $mocHienTai = '';

    public function handle(
        OpenShift $moCa,
        OpenTableSession $moBan,
        PlaceOrder $goiMon,
        SendToKitchen $guiBep,
        UpdateOrderItemStatus $capNhatTrangThaiMon,
        CancelOrderItem $huyMon,
        RecordPayment $thuTien,
    ): int {
        if (! app()->environment('local')) {
            $this->line('<fg=red>❌ Lệnh này chỉ chạy ở môi trường local, môi trường hiện tại là "'.app()->environment().'".</>');

            return CommandAlias::FAILURE;
        }

        $den = $this->option('den');

        if (! in_array($den, ['ca', 'ban', 'goi-mon', 'gui-bep', 'huy-mon', 'thu-tien'], true)) {
            $this->line("<fg=red>❌ Chưa hỗ trợ --den={$den}. Bước này chỉ hỗ trợ --den=ca, --den=ban, --den=goi-mon, --den=gui-bep, --den=huy-mon hoặc --den=thu-tien.</>");

            return CommandAlias::FAILURE;
        }

        $this->newLine();

        DB::beginTransaction();

        try {
            $thuNgan = $this->dienTapMoCa($moCa);

            if (in_array($den, ['ban', 'goi-mon', 'gui-bep', 'huy-mon', 'thu-tien'], true)) {
                $luotKhach = $this->dienTapMoBan($moBan);
            }

            if (in_array($den, ['goi-mon', 'gui-bep', 'huy-mon', 'thu-tien'], true)) {
                $phieux = $this->dienTapGoiMon($goiMon, $luotKhach);
            }

            if (in_array($den, ['gui-bep', 'huy-mon', 'thu-tien'], true)) {
                $this->dienTapGuiBepVaBaoXong($guiBep, $capNhatTrangThaiMon, $phieux);
            }

            if (in_array($den, ['huy-mon', 'thu-tien'], true)) {
                $this->dienTapHuyMon($huyMon, $luotKhach, $thuNgan, $phieux[0]);
            }

            if ($den === 'thu-tien') {
                $this->dienTapThuTien($thuTien, $luotKhach, $thuNgan);
            }

            $ketQua = CommandAlias::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->line("<fg=red>❌ DỪNG Ở MỐC: {$this->mocHienTai}</>");
            $this->line('<fg=red>   Lý do: '.$e->getMessage().'</>');
            $ketQua = CommandAlias::FAILURE;
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        if ($ketQua === CommandAlias::SUCCESS) {
            $this->line('<fg=green;options=bold>✅ TOÀN BỘ LƯỢT BÁN CHẠY ĐÚNG</>');
        }
        $this->line('<fg=yellow>Đã dọn sạch toàn bộ dữ liệu diễn tập (rollback, không có gì được ghi thật vào database).</>');

        return $ketQua;
    }

    private function dienTapMoCa(OpenShift $moCa): User
    {
        $this->mocHienTai = 'MỞ CA';
        $this->line('<fg=cyan;options=bold>MỞ CA</>');

        $thuNgan = User::factory()->create([
            'name' => 'Thu ngân diễn tập',
            'role' => UserRole::Cashier,
            'password' => Hash::make('password'),
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $tienDauCa = Money::fromInt(500_000);

        $ca = $moCa->handle(new OpenShiftData(
            openingCash: $tienDauCa,
            openedByUserId: $thuNgan->id,
        ));

        $this->line("   Ca {$ca->code} mở bởi {$thuNgan->name}, tiền lẻ đầu ca {$tienDauCa->format()}");

        return $thuNgan;
    }

    private function dienTapMoBan(OpenTableSession $moBan): TableSession
    {
        $this->mocHienTai = 'MỞ BÀN';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>MỞ BÀN</>');

        $phucVu = User::factory()->create([
            'name' => 'Phục vụ diễn tập',
            'role' => UserRole::Staff,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $banChinh = DiningTable::factory()->create(['code' => 'DEMO-1', 'name' => 'Bàn diễn tập 1']);
        $banGhep = DiningTable::factory()->create(['code' => 'DEMO-2', 'name' => 'Bàn diễn tập 2']);

        $luotKhach = $moBan->handle(new OpenTableSessionData(
            diningTableIds: [$banChinh->id, $banGhep->id],
            primaryDiningTableId: $banChinh->id,
            guestCount: 6,
            openedByUserId: $phucVu->id,
        ));

        $this->line("   Lượt khách {$luotKhach->code} mở bởi {$phucVu->name} tại bàn {$banChinh->code} (ghép thêm bàn {$banGhep->code}), {$luotKhach->guest_count} khách");

        return $luotKhach;
    }

    /** @return array{0: Order, 1: Order} [phiếu bếp, phiếu quầy] */
    private function dienTapGoiMon(PlaceOrder $goiMon, TableSession $luotKhach): array
    {
        $this->mocHienTai = 'GỌI MÓN';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>GỌI MÓN</>');

        $phucVu = User::factory()->create([
            'name' => 'Phục vụ diễn tập 2',
            'role' => UserRole::Staff,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $nhomBep = Category::factory()->create(['name' => 'Đồ nhắm diễn tập', 'station' => Station::Kitchen]);
        $nhomQuay = Category::factory()->create(['name' => 'Nước diễn tập', 'station' => Station::Bar]);

        $monBep = Product::factory()->for($nhomBep)->create(['code' => 'DEMO-GA', 'name' => 'Gà nướng diễn tập']);
        $bienTheBep = ProductVariant::factory()->for($monBep)->create(['name' => 'Phần', 'price' => 120_000]);

        $monQuay = Product::factory()->for($nhomQuay)->create(['code' => 'DEMO-BIA', 'name' => 'Bia diễn tập']);
        $bienTheQuay = ProductVariant::factory()->for($monQuay)->create(['name' => 'Lon', 'price' => 25_000]);

        $phieuBep = $goiMon->handle(new PlaceOrderData(
            uuid: (string) Str::uuid(),
            tableSessionId: $luotKhach->id,
            items: [new PlaceOrderItemData($monBep->id, $bienTheBep->id, 3, null, [])],
            note: null,
            createdByUserId: $phucVu->id,
        ));
        $this->inDongMon($phieuBep, $monBep->name, 3, $bienTheBep->price, 'bếp');

        $phieuQuay = $goiMon->handle(new PlaceOrderData(
            uuid: (string) Str::uuid(),
            tableSessionId: $luotKhach->id,
            items: [new PlaceOrderItemData($monQuay->id, $bienTheQuay->id, 4, null, [])],
            note: null,
            createdByUserId: $phucVu->id,
        ));
        $this->inDongMon($phieuQuay, $monQuay->name, 4, $bienTheQuay->price, 'quầy');

        $luotKhach->refresh();
        $tamTinh = Money::fromInt($luotKhach->subtotal_amount);
        $this->line("   Tạm tính: {$tamTinh->format()}");

        return [$phieuBep, $phieuQuay];
    }

    private function inDongMon(Order $phieu, string $tenMon, int $soLuong, int $donGia, string $noiLam): void
    {
        $thanhTien = Money::fromInt($donGia)->times($soLuong);
        $this->line("   [{$noiLam}] {$soLuong} x {$tenMon} — {$thanhTien->format()} (phiếu {$phieu->uuid})");
    }

    /** @param array{0: Order, 1: Order} $phieux */
    private function dienTapGuiBepVaBaoXong(SendToKitchen $guiBep, UpdateOrderItemStatus $capNhatTrangThaiMon, array $phieux): void
    {
        $this->mocHienTai = 'GỬI BẾP';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>GỬI BẾP</>');

        foreach ($phieux as $phieu) {
            $daGui = $guiBep->handle(new SendToKitchenData(orderId: $phieu->id));
            $this->line("   Đã gửi phiếu {$daGui->uuid} ({$daGui->station->value}) xuống nơi làm, trạng thái: {$daGui->status->value}");
        }

        $this->mocHienTai = 'BẾP BÁO XONG';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>BẾP BÁO XONG</>');

        foreach ($phieux as $phieu) {
            foreach ($phieu->items as $dongMon) {
                $capNhatTrangThaiMon->handle(new UpdateOrderItemStatusData(orderItemId: $dongMon->id));
            }

            $phieu->refresh();
            $this->line("   Phiếu {$phieu->uuid} ({$phieu->station->value}) — {$dongMon->product_name} đã xong, trạng thái phiếu: {$phieu->status->value}");
        }
    }

    private function dienTapHuyMon(CancelOrderItem $huyMon, TableSession $luotKhach, User $thuNgan, Order $phieuBep): void
    {
        $this->mocHienTai = 'HỦY MÓN';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>HỦY MÓN</>');

        $dongMon = $phieuBep->items()->sole();
        $tamTinhTruoc = Money::fromInt($luotKhach->refresh()->subtotal_amount);
        $this->line("   Tạm tính trước khi huỷ: {$tamTinhTruoc->format()}");
        $this->line("   Món {$dongMon->product_name} đang có {$dongMon->quantity} phần, đã phục vụ — huỷ bớt 1 phần, cần PIN duyệt của {$thuNgan->name}");

        $dongDaHuy = $huyMon->handle(new CancelOrderItemData(
            orderId: $phieuBep->id,
            orderItemId: $dongMon->id,
            quantity: 1,
            reason: 'Khách trả bớt, gọi nhầm',
            cancelledByUserId: $thuNgan->id,
            approverUserId: $thuNgan->id,
            approverPin: '1234',
        ));

        $dongMonGoc = $dongMon->refresh();
        $this->line("   Tách thành 2 dòng: dòng gốc còn {$dongMonGoc->quantity} phần (giữ nguyên), dòng mới huỷ {$dongDaHuy->quantity} phần (id {$dongDaHuy->id}, tách từ id {$dongMonGoc->id})");

        $tamTinhSau = Money::fromInt($luotKhach->refresh()->subtotal_amount);
        $this->line("   Tạm tính sau khi huỷ: {$tamTinhSau->format()}");
    }

    private function dienTapThuTien(RecordPayment $thuTien, TableSession $luotKhach, User $thuNgan): void
    {
        $this->mocHienTai = 'THU TIỀN';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>THU TIỀN</>');

        $luotKhach->refresh();
        $tongPhaiThu = Money::fromInt($luotKhach->total_amount);
        $khachDua = $tongPhaiThu->plus(Money::fromInt(50_000));

        $this->line("   Tổng phải thu: {$tongPhaiThu->format()}");

        $phieuThu = $thuTien->handle(new RecordPaymentData(
            uuid: (string) Str::uuid(),
            tableSessionId: $luotKhach->id,
            method: PaymentMethod::Cash,
            amount: $tongPhaiThu,
            tenderedAmount: $khachDua,
            reference: null,
            receivedByUserId: $thuNgan->id,
        ));

        $this->line("   Khách đưa: {$khachDua->format()}");
        $this->line('   Thối lại: '.Money::fromInt($phieuThu->change_amount)->format());

        $luotKhach->refresh();
        $this->line("   Trạng thái lượt khách sau khi thu: {$luotKhach->status->value}");
    }
}
