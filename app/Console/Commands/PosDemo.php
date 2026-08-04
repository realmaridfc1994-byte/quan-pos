<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Actions\ApplyPromotion;
use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTO\ApplyPromotionData;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Promotion;
use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Actions\CancelOrderItem;
use App\Domain\Ordering\Actions\OpenTableSession;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\SendToKitchen;
use App\Domain\Ordering\Actions\SplitTableSession;
use App\Domain\Ordering\Actions\UpdateOrderItemStatus;
use App\Domain\Ordering\Actions\VoidTableSession;
use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\PlaceOrderItemData;
use App\Domain\Ordering\DTO\SendToKitchenData;
use App\Domain\Ordering\DTO\SplitTableSessionData;
use App\Domain\Ordering\DTO\UpdateOrderItemStatusData;
use App\Domain\Ordering\DTO\VoidTableSessionData;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Actions\CloseShift;
use App\Domain\Staffing\Actions\OpenShift;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\DTO\OpenShiftData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Actions\SyncBatch;
use App\Domain\Sync\DTO\SyncBatchData;
use App\Domain\Sync\DTO\SyncOperationData;
use App\Domain\Sync\Enums\OperationType;
use App\Exceptions\DomainException;
use App\Support\CashVariance;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
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
    protected $signature = 'pos:demo {--den=dong-ca : Mốc dừng lại — "ca", "ban", "goi-mon", "gui-bep", "tach-ban", "huy-mon", "thu-tien", "khuyen-mai", "sync" hoặc "dong-ca" (mặc định — chạy trọn vẹn)}';

    protected $description = 'Diễn tập một ca bán hàng mẫu, dùng để tự kiểm tra bằng mắt (chỉ chạy ở môi trường local)';

    /** Tên mốc hiện tại — dùng để in "DỪNG Ở MỐC" đúng chỗ khi có lỗi. */
    private string $mocHienTai = '';

    public function handle(
        OpenShift $moCa,
        OpenTableSession $moBan,
        PlaceOrder $goiMon,
        SendToKitchen $guiBep,
        UpdateOrderItemStatus $capNhatTrangThaiMon,
        SplitTableSession $tachBan,
        CancelOrderItem $huyMon,
        RecordPayment $thuTien,
        CloseShift $dongCa,
        SyncBatch $dongBo,
        ApplyPromotion $apDungKhuyenMai,
    ): int {
        if (! app()->environment('local')) {
            $this->line('<fg=red>❌ Lệnh này chỉ chạy ở môi trường local, môi trường hiện tại là "'.app()->environment().'".</>');

            return CommandAlias::FAILURE;
        }

        $den = $this->option('den');
        $cacMoc = ['ca', 'ban', 'goi-mon', 'gui-bep', 'tach-ban', 'huy-mon', 'thu-tien', 'khuyen-mai', 'sync', 'dong-ca'];

        if (! in_array($den, $cacMoc, true)) {
            $this->line("<fg=red>❌ Chưa hỗ trợ --den={$den}. Bước này chỉ hỗ trợ --den=".implode(', --den=', $cacMoc).'.</>');

            return CommandAlias::FAILURE;
        }

        $this->newLine();

        DB::beginTransaction();

        try {
            [$thuNgan, $ca] = $this->dienTapMoCa($moCa);

            if ($den === 'sync') {
                $this->dienTapDongBo($dongBo, $thuNgan);

                $this->newLine();
                $this->line('<fg=green;options=bold>✅ ĐỒNG BỘ CHẠY ĐÚNG</>');
                $this->line('<fg=yellow>Đã dọn sạch toàn bộ dữ liệu diễn tập (rollback, không có gì được ghi thật vào database).</>');

                DB::rollBack();

                return CommandAlias::SUCCESS;
            }

            if ($den === 'khuyen-mai') {
                $this->dienTapKhuyenMai($moBan, $goiMon, $apDungKhuyenMai, $thuNgan);

                $this->newLine();
                $this->line('<fg=green;options=bold>✅ KHUYẾN MÃI CHẠY ĐÚNG</>');
                $this->line('<fg=yellow>Đã dọn sạch toàn bộ dữ liệu diễn tập (rollback, không có gì được ghi thật vào database).</>');

                DB::rollBack();

                return CommandAlias::SUCCESS;
            }

            if (in_array($den, ['ban', 'goi-mon', 'gui-bep', 'tach-ban', 'huy-mon', 'thu-tien', 'dong-ca'], true)) {
                $luotKhach = $this->dienTapMoBan($moBan);
            }

            if (in_array($den, ['goi-mon', 'gui-bep', 'tach-ban', 'huy-mon', 'thu-tien', 'dong-ca'], true)) {
                $phieux = $this->dienTapGoiMon($goiMon, $luotKhach);
            }

            if (in_array($den, ['gui-bep', 'tach-ban', 'huy-mon', 'thu-tien', 'dong-ca'], true)) {
                $this->dienTapGuiBepVaBaoXong($guiBep, $capNhatTrangThaiMon, $phieux);
            }

            if (in_array($den, ['tach-ban', 'huy-mon', 'thu-tien', 'dong-ca'], true)) {
                $this->dienTapTachBan($tachBan, $luotKhach, $phieux[1], $thuNgan);
            }

            if (in_array($den, ['huy-mon', 'thu-tien', 'dong-ca'], true)) {
                $this->dienTapHuyMon($huyMon, $luotKhach, $thuNgan, $phieux[0]);
            }

            if (in_array($den, ['thu-tien', 'dong-ca'], true)) {
                $tienMatDaThu = $this->dienTapThuTien($thuTien, $luotKhach, $thuNgan);
            }

            if ($den === 'dong-ca') {
                $this->dienTapDongCa($dongCa, $ca, $thuNgan, $tienMatDaThu);
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

    /** @return array{0: User, 1: Shift} */
    private function dienTapMoCa(OpenShift $moCa): array
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

        return [$thuNgan, $ca];
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
            uuid: (string) Str::uuid(),
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
            items: [new PlaceOrderItemData((string) Str::uuid(), $monBep->id, $bienTheBep->id, 3, null, [])],
            note: null,
            createdByUserId: $phucVu->id,
        ));
        $this->inDongMon($phieuBep, $monBep->name, 3, $bienTheBep->price, 'bếp');

        $phieuQuay = $goiMon->handle(new PlaceOrderData(
            uuid: (string) Str::uuid(),
            tableSessionId: $luotKhach->id,
            items: [new PlaceOrderItemData((string) Str::uuid(), $monQuay->id, $bienTheQuay->id, 4, null, [])],
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

    /** Chuyển bàn ghép + món quầy sang một lượt khách mới — món đã gửi bếp trước đó vẫn tách được bình thường. */
    private function dienTapTachBan(SplitTableSession $tachBan, TableSession $luotKhach, Order $phieuQuay, User $thuNgan): void
    {
        $this->mocHienTai = 'TÁCH BÀN';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>TÁCH BÀN</>');

        $banGhep = $luotKhach->tables()->whereNull('detached_at')->where('is_primary', false)->sole()->diningTable;
        $dongBia = $phieuQuay->items()->sole();

        $tamTinhTruoc = Money::fromInt($luotKhach->refresh()->subtotal_amount);
        $this->line("   Tạm tính trước khi tách: {$tamTinhTruoc->format()}");
        $this->line("   Tách bàn {$banGhep->code} và món {$dongBia->product_name} (đã gửi bếp/quầy) sang lượt khách mới");

        $ketQua = $tachBan->handle(new SplitTableSessionData(
            sourceTableSessionId: $luotKhach->id,
            orderItemIds: [$dongBia->id],
            diningTableIds: [$banGhep->id],
            guestCount: 2,
            actorUserId: $thuNgan->id,
            uuid: (string) Str::uuid(),
        ));

        $tamTinhCu = Money::fromInt($ketQua['source']->subtotal_amount);
        $tamTinhMoi = Money::fromInt($ketQua['new']->subtotal_amount);
        $this->line("   Sau khi tách — lượt cũ {$ketQua['source']->code}: {$tamTinhCu->format()}, lượt mới {$ketQua['new']->code}: {$tamTinhMoi->format()}");
        $this->line('   Tổng hai bên: '.$tamTinhCu->plus($tamTinhMoi)->format().' (phải bằng tạm tính trước khi tách)');

        // Dọn lượt khách mới để ca có thể đóng ở bước cuối cùng của bài diễn tập
        // (C3: không đóng được ca khi còn lượt khách đang mở). Trong thực tế
        // lượt khách này sẽ bán tiếp và thu tiền bình thường như mọi lượt khác.
        app(VoidTableSession::class)->handle(new VoidTableSessionData(
            tableSessionId: $ketQua['new']->id,
            reason: 'Dọn dữ liệu diễn tập — chỉ minh hoạ thao tác tách bàn',
            voidedByUserId: $thuNgan->id,
        ));
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
            newItemUuid: (string) Str::uuid(),
            optionUuids: [],
        ));

        $dongMonGoc = $dongMon->refresh();
        $this->line("   Tách thành 2 dòng: dòng gốc còn {$dongMonGoc->quantity} phần (giữ nguyên), dòng mới huỷ {$dongDaHuy->quantity} phần (id {$dongDaHuy->id}, tách từ id {$dongMonGoc->id})");

        $tamTinhSau = Money::fromInt($luotKhach->refresh()->subtotal_amount);
        $this->line("   Tạm tính sau khi huỷ: {$tamTinhSau->format()}");
    }

    private function dienTapThuTien(RecordPayment $thuTien, TableSession $luotKhach, User $thuNgan): Money
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

        return $tongPhaiThu;
    }

    private function dienTapDongCa(CloseShift $dongCa, Shift $ca, User $thuNgan, Money $tienMatDaThu): void
    {
        $this->mocHienTai = 'ĐÓNG CA';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>ĐÓNG CA</>');

        // C4: đầu ca + tiền mặt thu được − tiền thối (thối cố định 50.000 ở mốc
        // THU TIỀN, không có khoản thu chi vặt nào trong diễn tập).
        $tienLeRaPhaiCo = Money::fromInt($ca->refresh()->opening_cash)
            ->plus($tienMatDaThu)
            ->minus(Money::fromInt(50_000));

        // Đếm thực tế thiếu 20.000 so với lẽ ra phải có — để chứng minh đóng ca
        // vẫn thành công dù két không khớp tuyệt đối, C4/C5 vẫn chốt đúng số.
        $demDuoc = $tienLeRaPhaiCo->minus(Money::fromInt(20_000));

        $caDaDong = $dongCa->handle(new CloseShiftData(
            shiftId: $ca->id,
            countedCash: $demDuoc,
            note: 'Ca diễn tập',
            closedByUserId: $thuNgan->id,
        ));

        $chenhLech = CashVariance::between(
            Money::fromInt($caDaDong->counted_cash),
            Money::fromInt($caDaDong->expected_cash)
        );

        $this->line('   Tiền mặt lẽ ra phải có: '.Money::fromInt($caDaDong->expected_cash)->format());
        $this->line("   Đếm thực tế trong két: {$demDuoc->format()}");
        $this->line("   Chênh lệch: {$chenhLech->format()}");
        $this->line("   Trạng thái ca: {$caDaDong->status->value}");
    }

    /**
     * Diễn lại MỘT LẦN MẤT MẠNG RỒI CÓ MẠNG LẠI (Phase 2 Bước 4):
     * máy 1 mở bàn lúc offline rồi gửi lên khi có mạng — áp dụng được ngay;
     * gửi lại đúng gói đó lần nữa (mạng lag) — không tạo trùng;
     * máy 2 cũng offline, cùng lúc mở đúng bàn đó cho nhóm khách khác — va
     * chạm dòng 2 của ma trận, chờ chủ quán quyết ở màn hình Bước 5.
     */
    private function dienTapDongBo(SyncBatch $dongBo, User $thuNgan): void
    {
        $this->mocHienTai = 'ĐỒNG BỘ (mất mạng rồi có mạng lại)';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>ĐỒNG BỘ — DIỄN LẠI MỘT LẦN MẤT MẠNG RỒI CÓ MẠNG LẠI</>');

        $ban = DiningTable::factory()->create(['code' => 'DEMO-SYNC', 'name' => 'Bàn diễn tập đồng bộ']);

        $luot1 = (string) Str::uuid();
        $goi1 = new SyncBatchData(
            batchUuid: (string) Str::uuid(),
            deviceId: 'pos-01',
            clientTime: CarbonImmutable::now(),
            operations: [
                new SyncOperationData(
                    opUuid: (string) Str::uuid(),
                    type: OperationType::OpenSession,
                    occurredAt: CarbonImmutable::now()->subMinutes(10),
                    dependsOn: [],
                    payload: ['uuid' => $luot1, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 4],
                    viTriGoc: 0,
                ),
            ],
            receivedByUserId: $thuNgan->id,
        );

        $this->inKetQuaDongBo('Máy 1 — mất mạng lúc 19h50, có mạng lại, gửi mở bàn lên', $dongBo->handle($goi1));
        $this->inKetQuaDongBo('Máy 1 — mạng lag, gửi lại ĐÚNG gói cũ (kiểm tra không tạo trùng)', $dongBo->handle($goi1));

        $luot2 = (string) Str::uuid();
        $goi2 = new SyncBatchData(
            batchUuid: (string) Str::uuid(),
            deviceId: 'pos-02',
            clientTime: CarbonImmutable::now(),
            operations: [
                new SyncOperationData(
                    opUuid: (string) Str::uuid(),
                    type: OperationType::OpenSession,
                    occurredAt: CarbonImmutable::now()->subMinutes(8),
                    dependsOn: [],
                    payload: ['uuid' => $luot2, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 2],
                    viTriGoc: 0,
                ),
            ],
            receivedByUserId: $thuNgan->id,
        );

        $this->inKetQuaDongBo('Máy 2 — cũng mất mạng, cùng lúc mở ĐÚNG bàn đó cho nhóm khách khác (va chạm)', $dongBo->handle($goi2));
    }

    /** @param list<array<string, mixed>> $ketQua */
    private function inKetQuaDongBo(string $tieuDe, array $ketQua): void
    {
        $this->line("   {$tieuDe}:");
        foreach ($ketQua as $kq) {
            $them = isset($kq['conflict_id']) ? " — CHỜ CHỦ QUÁN QUYẾT (mã xung đột #{$kq['conflict_id']})" : '';
            $this->line("     • {$kq['op_uuid']}: {$kq['status']}{$them}");
        }
    }

    /**
     * Diễn lại khuyến mãi (Phase 2 Bước 6):
     *  - Giờ vàng ĐÚNG khung giờ hiện tại → áp được.
     *  - Chồng khuyến mãi thứ hai lên cùng lượt khách → bị chặn.
     *  - Giờ vàng SAI khung giờ, khuyến mãi ĐÃ HẾT HẠN, khuyến mãi ĐÃ HẾT LƯỢT
     *    dùng → cả ba đều bị chặn.
     *
     * Khung giờ của khuyến mãi giờ vàng lấy quanh GIỜ THẬT lúc chạy lệnh này
     * (now() ± 1 giờ / lệch hẳn +5 giờ) thay vì cố định "17:00-19:00" — để
     * lệnh chạy đúng bất kể chạy vào giờ nào trong ngày, không phụ thuộc đồng
     * hồ máy dev đang là mấy giờ.
     */
    private function dienTapKhuyenMai(OpenTableSession $moBan, PlaceOrder $goiMon, ApplyPromotion $apDungKhuyenMai, User $thuNgan): void
    {
        $this->mocHienTai = 'KHUYẾN MÃI';
        $this->newLine();
        $this->line('<fg=cyan;options=bold>KHUYẾN MÃI</>');

        $nhomBia = Category::factory()->create(['name' => 'Bia diễn tập khuyến mãi', 'station' => Station::Bar]);
        $bia = Product::factory()->for($nhomBia)->create(['code' => 'DEMO-KM-BIA', 'name' => 'Bia diễn tập khuyến mãi']);
        $bienTheBia = ProductVariant::factory()->for($bia)->create(['name' => 'Lon', 'price' => 25_000]);

        // ── Lượt khách 1: giờ vàng ĐANG áp dụng ngay lúc này ─────────────
        $luot1 = $this->dienTapMoBanRieng($moBan, 'DEMO-KM-1');
        $goiMon->handle(new PlaceOrderData(
            uuid: (string) Str::uuid(),
            tableSessionId: $luot1->id,
            items: [new PlaceOrderItemData((string) Str::uuid(), $bia->id, $bienTheBia->id, 4, null, [])],
            note: null,
            createdByUserId: $thuNgan->id,
        ));
        $luot1->refresh();
        $this->line('   Lượt 1 gọi 4 lon bia — tạm tính '.Money::fromInt($luot1->subtotal_amount)->format());

        $gioVang = Promotion::factory()
            ->happyHour(20, ['from' => now()->subHour()->format('H:i'), 'to' => now()->addHour()->format('H:i')])
            ->choDanhMuc($nhomBia->id)
            ->create(['code' => 'DEMO-GIOVANG', 'name' => 'Giờ vàng bia diễn tập']);

        $luot1 = $apDungKhuyenMai->handle(new ApplyPromotionData($luot1->id, $gioVang->code, $thuNgan->id));
        $this->line("   Áp \"{$gioVang->name}\" đúng giờ — giảm ".Money::fromInt($luot1->discount_amount)->format().', tổng còn '.Money::fromInt($luot1->total_amount)->format());

        $this->kyVongBiChan(
            fn () => $apDungKhuyenMai->handle(new ApplyPromotionData($luot1->id, $gioVang->code, $thuNgan->id)),
            'Áp khuyến mãi THỨ HAI vào lượt khách vừa áp xong'
        );

        // ── Lượt khách 2: sai giờ, hết hạn, hết lượt ─────────────────────
        $luot2 = $this->dienTapMoBanRieng($moBan, 'DEMO-KM-2');
        $goiMon->handle(new PlaceOrderData(
            uuid: (string) Str::uuid(),
            tableSessionId: $luot2->id,
            items: [new PlaceOrderItemData((string) Str::uuid(), $bia->id, $bienTheBia->id, 2, null, [])],
            note: null,
            createdByUserId: $thuNgan->id,
        ));
        $this->line('   Lượt 2 gọi 2 lon bia');

        $saiGio = Promotion::factory()
            ->happyHour(20, ['from' => now()->addHours(5)->format('H:i'), 'to' => now()->addHours(6)->format('H:i')])
            ->create(['code' => 'DEMO-SAIGIO', 'name' => 'Giờ vàng sai giờ diễn tập']);
        $this->kyVongBiChan(
            fn () => $apDungKhuyenMai->handle(new ApplyPromotionData($luot2->id, $saiGio->code, $thuNgan->id)),
            'Áp giờ vàng NGOÀI khung giờ'
        );

        $hetHan = Promotion::factory()->percent(10)->create([
            'code' => 'DEMO-HETHAN', 'name' => 'Khuyến mãi hết hạn diễn tập', 'ends_at' => now()->subDay(),
        ]);
        $this->kyVongBiChan(
            fn () => $apDungKhuyenMai->handle(new ApplyPromotionData($luot2->id, $hetHan->code, $thuNgan->id)),
            'Áp khuyến mãi ĐÃ HẾT HẠN'
        );

        $hetLuot = Promotion::factory()->percent(10)->create([
            'code' => 'DEMO-HETLUOT', 'name' => 'Khuyến mãi hết lượt diễn tập', 'max_usage' => 1, 'used_count' => 1,
        ]);
        $this->kyVongBiChan(
            fn () => $apDungKhuyenMai->handle(new ApplyPromotionData($luot2->id, $hetLuot->code, $thuNgan->id)),
            'Áp khuyến mãi ĐÃ HẾT LƯỢT DÙNG'
        );
    }

    private function dienTapMoBanRieng(OpenTableSession $moBan, string $maBan): TableSession
    {
        $ban = DiningTable::factory()->create(['code' => $maBan, 'name' => "Bàn diễn tập {$maBan}"]);
        $phucVu = User::factory()->create([
            'name' => "Phục vụ diễn tập {$maBan}",
            'role' => UserRole::Staff,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        return $moBan->handle(new OpenTableSessionData(
            uuid: (string) Str::uuid(),
            diningTableIds: [$ban->id],
            primaryDiningTableId: $ban->id,
            guestCount: 2,
            openedByUserId: $phucVu->id,
        ));
    }

    /** Chạy $viec, kỳ vọng bị DomainException chặn — báo lỗi diễn tập nếu KHÔNG bị chặn. */
    private function kyVongBiChan(Closure $viec, string $moTa): void
    {
        try {
            $viec();

            throw new \RuntimeException("LỖI DIỄN TẬP: \"{$moTa}\" đáng lẽ phải bị chặn nhưng lại thành công.");
        } catch (DomainException $e) {
            $this->line("   {$moTa} — ĐÚNG LUẬT, BỊ CHẶN: {$e->getMessage()}");
        }
    }
}
