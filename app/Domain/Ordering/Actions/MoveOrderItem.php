<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\MoveOrderItemData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Exceptions\DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chuyển vài dòng món đã gọi sang một lượt khách ĐANG MỞ khác — dùng khi khách
 * đổi chỗ ngồi hoặc khi SplitTableSession cần dời món sang lượt khách vừa tách ra.
 *
 * Dòng món không đổi gì ngoài `order_id`: giữ nguyên trạng thái, giữ nguyên
 * served_at (Phase 3 cần cái này để tính đúng đã dùng nguyên liệu hay chưa,
 * xem docs/kiem-toan-offline.md mục 5), giữ nguyên tuỳ chọn đã chọn.
 *
 * Dòng món đã gửi bếp vẫn chuyển được — bếp không cần biết việc đổi bàn này.
 * Để không hiện lại thành một tem "vừa gửi" trên màn hình bếp/quầy, phiếu MỚI
 * dựng cho lượt khách đích chỉ mang trạng thái "served" khi TOÀN BỘ dòng
 * chuyển sang đã xong; còn món nào chưa xong thì đúng là còn việc phải làm
 * thật, phiếu vẫn hiện là "sent" là hợp lý.
 */
final class MoveOrderItem
{
    /**
     * @return array{source: TableSession, target: TableSession}
     */
    public function handle(MoveOrderItemData $data): array
    {
        if ($data->orderItemIds === []) {
            throw new DomainException('Phải chọn ít nhất một dòng món để chuyển.');
        }

        if ($data->sourceTableSessionId === $data->targetTableSessionId) {
            throw new DomainException('Lượt khách nguồn và lượt khách đích không được trùng nhau.');
        }

        return DB::transaction(function () use ($data): array {
            $idLuotTheoThuTu = collect([$data->sourceTableSessionId, $data->targetTableSessionId])->sort()->values();

            $luotTheoId = TableSession::query()
                ->whereIn('id', $idLuotTheoThuTu)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $nguon = $luotTheoId->get($data->sourceTableSessionId);
            $dich = $luotTheoId->get($data->targetTableSessionId);

            if ($nguon === null || $dich === null) {
                throw new DomainException('Có lượt khách không tồn tại.');
            }

            if ($nguon->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách nguồn không còn mở, không chuyển món được nữa.');
            }

            if ($dich->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách đích không còn mở, không chuyển món sang được.');
            }

            if ($nguon->paid_amount > 0 || $dich->paid_amount > 0) {
                throw new DomainException('Một trong hai lượt khách đã thu tiền một phần, không tự chuyển món được. Đây là quyết định của con người, không phải của máy.');
            }

            $idMonTheoThuTu = collect($data->orderItemIds)->unique()->sort()->values();

            $dongMonDuocChon = OrderItem::query()
                ->whereIn('id', $idMonTheoThuTu)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('order')
                ->get();

            if ($dongMonDuocChon->count() !== $idMonTheoThuTu->count()) {
                throw new DomainException('Có dòng món không tồn tại trong danh sách đã chọn.');
            }

            foreach ($dongMonDuocChon as $dong) {
                if ($dong->order->table_session_id !== $nguon->id) {
                    throw new DomainException('Có dòng món không thuộc lượt khách nguồn.');
                }

                if ($dong->status === OrderItemStatus::Cancelled) {
                    throw new DomainException('Không chuyển được dòng món đã huỷ.');
                }
            }

            $this->chuyenSangPhieuMoiTheoTram($dongMonDuocChon, $nguon, $dich, $data->actorUserId);

            app(RecalculateSessionSubtotal::class)->handle($nguon);
            app(RecalculateSessionSubtotal::class)->handle($dich);

            return ['source' => $nguon->refresh(), 'target' => $dich->refresh()];
        });
    }

    /** @param Collection<int, OrderItem> $dongMonDuocChon */
    private function chuyenSangPhieuMoiTheoTram(
        Collection $dongMonDuocChon,
        TableSession $nguon,
        TableSession $dich,
        int $actorUserId,
    ): void {
        $theoTram = $dongMonDuocChon->groupBy(fn (OrderItem $dong) => $dong->order->station->value);

        foreach ($theoTram as $tram => $nhomDong) {
            $daXongHet = $nhomDong->every(fn (OrderItem $dong) => $dong->status === OrderItemStatus::Served);

            $soThuTu = Order::query()->where('table_session_id', $dich->id)->max('sequence_no') + 1;

            $phieuMoi = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'table_session_id' => $dich->id,
                'sequence_no' => $soThuTu,
                'station' => $tram,
                'status' => $daXongHet ? OrderStatus::Served : OrderStatus::Sent,
                'created_by_user_id' => $actorUserId,
                'sent_at' => now(),
                'served_at' => $daXongHet ? now() : null,
                'note' => "Chuyển từ lượt khách {$nguon->code}",
            ]);

            OrderItem::query()->whereIn('id', $nhomDong->pluck('id'))->update(['order_id' => $phieuMoi->id]);
        }
    }
}
