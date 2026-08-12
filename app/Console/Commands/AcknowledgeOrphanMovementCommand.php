<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\Actions\AcknowledgeOrphanMovement;
use App\Domain\Inventory\DTO\AcknowledgeOrphanMovementData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Console\Command;

/**
 * Xác nhận một dòng sổ cái mồ côi mà lệnh stock:doi-soat báo đỏ — Phase 3
 * Bước 10, review mục 8.2-K.
 *
 * Chưa có màn hình Filament cho việc này (xem docs/viec-ton.md): đây là thao
 * tác hiếm, làm vài lần một năm, nên một lệnh gõ tay là đủ.
 */
final class AcknowledgeOrphanMovementCommand extends Command
{
    protected $signature = 'stock:ghi-chu-mo-coi
        {movement : Số hiệu dòng sổ cái, lấy ở mục 3 của lệnh stock:doi-soat}
        {--ghi-chu= : Đã xem thấy gì}
        {--ly-do= : Vì sao dòng này không phải lỗi sổ sách}
        {--nguoi= : Số hiệu tài khoản người xác nhận, bỏ trống thì lấy chủ quán đầu tiên}';

    protected $description = 'Xác nhận một dòng sổ cái mồ côi là đã xem, để lệnh đối soát thôi báo đỏ';

    public function handle(AcknowledgeOrphanMovement $action): int
    {
        $nguoiId = $this->option('nguoi') !== null
            ? (int) $this->option('nguoi')
            : User::query()->where('role', UserRole::Owner)->value('id');

        if ($nguoiId === null) {
            $this->error('Không tìm thấy tài khoản chủ quán nào. Dùng --nguoi= để chỉ rõ người xác nhận.');

            return self::FAILURE;
        }

        try {
            $ghiChu = $action->handle(new AcknowledgeOrphanMovementData(
                stockMovementId: (int) $this->argument('movement'),
                note: (string) ($this->option('ghi-chu') ?? ''),
                reason: (string) ($this->option('ly-do') ?? ''),
                acknowledgedByUserId: $nguoiId,
            ));
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("<fg=green;options=bold>✅ Đã ghi chú cho dòng sổ cái #{$ghiChu->stock_movement_id}.</>");
        $this->line("   Ghi chú: {$ghiChu->note}");
        $this->line("   Lý do:   {$ghiChu->reason}");
        $this->line('   Người xác nhận: '.$ghiChu->acknowledgedBy->name.' lúc '.$ghiChu->acknowledged_at->toDateTimeString());
        $this->newLine();
        $this->line('<fg=gray>Dòng này vẫn hiện trong bản đối soát, nhưng thôi tính vào số lỗi.</>');

        return self::SUCCESS;
    }
}
