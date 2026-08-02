<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\MoveOrderItemData;
use App\Domain\Ordering\DTO\SplitTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Tách một lượt khách đang mở thành hai: một số bàn + một số dòng món chuyển
 * sang một lượt khách MỚI, phần còn lại giữ nguyên ở lượt gốc.
 *
 * B1/B6: nhả bàn khỏi lượt gốc và gán cho lượt mới trong CÙNG một giao dịch.
 * B2/B5: cả hai lượt sau khi tách đều phải còn ít nhất một bàn — không tách
 * hết bàn của lượt gốc sang lượt mới.
 * H4-tương-tự: dòng món chỉ CHUYỂN chủ sở hữu (order_id đổi sang phiếu của
 * lượt mới), không xoá không tạo trùng — tổng tạm tính hai bên sau tách luôn
 * bằng tạm tính trước khi tách vì cùng là tổng các order_items.line_amount đó,
 * chỉ đổi chỗ quy về lượt khách nào.
 * Không tách được lượt khách đã thu tiền một phần (paid_amount > 0) — chia
 * tiền đã thu là quyết định của con người, không phải của máy.
 *
 * Việc chuyển dòng món thực sự nằm trong MoveOrderItem — Action này chỉ lo
 * phần tạo lượt mới + chuyển bàn, rồi gọi lại MoveOrderItem để khỏi lặp logic
 * gom món theo trạm bếp/quầy.
 */
final class SplitTableSession
{
    public function __construct(
        private readonly MoveOrderItem $moveOrderItem,
    ) {}

    /**
     * @return array{source: TableSession, new: TableSession}
     */
    public function handle(SplitTableSessionData $data): array
    {
        if ($data->orderItemIds === []) {
            throw new DomainException('Phải chọn ít nhất một dòng món để tách.');
        }

        if ($data->diningTableIds === []) {
            throw new DomainException('Phải gán ít nhất một bàn cho lượt khách mới.');
        }

        return DB::transaction(function () use ($data): array {
            // Luật CLAUDE.md mục 11: Shift → TableSession → (Bàn/Món).
            $shift = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();

            if ($shift === null) {
                throw new DomainException('Chưa mở ca. Phải mở ca trước khi tách bàn.');
            }

            $luotGoc = TableSession::query()->lockForUpdate()->findOrFail($data->sourceTableSessionId);

            if ($luotGoc->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách này không còn mở, không tách được nữa.');
            }

            if ($luotGoc->paid_amount > 0) {
                throw new DomainException('Lượt khách này đã thu một phần tiền, không tự tách được. Đây là quyết định của con người, không phải của máy.');
            }

            $idBanTheoThuTu = collect($data->diningTableIds)->unique()->sort()->values();

            $banDuocChon = DiningTable::query()
                ->whereIn('id', $idBanTheoThuTu)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($banDuocChon->count() !== $idBanTheoThuTu->count()) {
                throw new DomainException('Có bàn không tồn tại trong danh sách đã chọn.');
            }

            $banDangChiemCuaLuotGoc = TableSessionTable::query()
                ->where('table_session_id', $luotGoc->id)
                ->whereNull('detached_at')
                ->get();

            $phanChiemDuocChon = collect();
            foreach ($banDuocChon as $ban) {
                $phanChiem = $banDangChiemCuaLuotGoc->firstWhere('dining_table_id', $ban->id);

                if ($phanChiem === null) {
                    throw new DomainException("Bàn {$ban->code} không thuộc lượt khách này, không tách được.");
                }

                $phanChiemDuocChon->push($phanChiem);
            }

            if ($phanChiemDuocChon->count() >= $banDangChiemCuaLuotGoc->count()) {
                throw new DomainException('Phải giữ lại ít nhất một bàn cho lượt khách gốc.');
            }

            $luotMoi = TableSession::query()->create([
                'code' => $this->sinhMaLuotKhach(),
                'shift_id' => $shift->id,
                'guest_count' => $data->guestCount,
                'status' => TableSessionStatus::Open,
                'opened_by_user_id' => $data->actorUserId,
                'opened_at' => now(),
            ]);

            $conLaiCuaLuotGoc = $banDangChiemCuaLuotGoc->reject(
                fn (TableSessionTable $t) => $phanChiemDuocChon->contains('id', $t->id)
            );

            // Phải NHẢ bàn khỏi lượt gốc TRƯỚC khi gán cho lượt mới — cùng một
            // bàn không thể "đang chiếm" ở hai dòng table_session_tables cùng
            // lúc (uq_tst_one_session_per_table), kể cả trong cùng transaction.
            foreach ($phanChiemDuocChon as $phanChiem) {
                $phanChiem->update(['detached_at' => now()]);
            }

            $banChinhMoiId = $idBanTheoThuTu->first();
            foreach ($banDuocChon as $ban) {
                TableSessionTable::query()->create([
                    'table_session_id' => $luotMoi->id,
                    'dining_table_id' => $ban->id,
                    'is_primary' => $ban->id === $banChinhMoiId,
                    'attached_at' => now(),
                    'attached_by_user_id' => $data->actorUserId,
                ]);
            }

            // B3: nếu bàn chính của lượt gốc nằm trong số bàn bị tách đi, đôn
            // bàn chính mới cho lượt gốc — cùng ba mức ưu tiên như DetachTable.
            $banChinhCuBiTach = $phanChiemDuocChon->firstWhere('is_primary', true);
            if ($banChinhCuBiTach !== null) {
                $banChinhMoiCuaLuotGoc = $conLaiCuaLuotGoc->sortBy(['attached_at', 'id'])->first();
                $banChinhMoiCuaLuotGoc->update(['is_primary' => true]);
            }

            $ketQuaChuyenMon = $this->moveOrderItem->handle(new MoveOrderItemData(
                sourceTableSessionId: $luotGoc->id,
                targetTableSessionId: $luotMoi->id,
                orderItemIds: $data->orderItemIds,
                actorUserId: $data->actorUserId,
            ));

            return ['source' => $ketQuaChuyenMon['source'], 'new' => $ketQuaChuyenMon['target']];
        });
    }

    private function sinhMaLuotKhach(): string
    {
        $homNay = now()->format('Ymd');
        $soThuTu = TableSession::query()->whereDate('opened_at', now()->toDateString())->count() + 1;

        return "PH-{$homNay}-".str_pad((string) $soThuTu, 4, '0', STR_PAD_LEFT);
    }
}
