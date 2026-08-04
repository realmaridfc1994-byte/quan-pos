<?php

declare(strict_types=1);

namespace App\Domain\Sync\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class ResolveSyncConflictData
{
    /**
     * @param  bool  $dismiss  true = "Bỏ qua" (xem rồi quyết định không làm gì) — không áp
     *                         dụng hiệu ứng nào. false = chọn một trong các phương án của
     *                         chính xung đột này, ghi ở $resolution.
     * @param  ?string  $resolution  Khoá phương án (trùng với một trong các "options" đã lưu
     *                               ở sync_conflicts.options), bắt buộc khi $dismiss = false.
     * @param  list<int>  $diningTableIds  Chỉ dùng cho phương án "Tách" (hai máy cùng mở bàn)
     *                                     hoặc "Tạo lượt khách mới" — bàn người quyết chọn thay.
     * @param  ?int  $approverUserId  Người duyệt bằng PIN — bắt buộc khi xung đột dính tiền
     *                                (thu trùng, thu vượt sau giảm giá), xem ResolveSyncConflict.
     * @param  ?string  $approverPin  Mã PIN của $approverUserId.
     */
    public function __construct(
        public int $conflictId,
        public bool $dismiss,
        public ?string $resolution,
        public string $note,
        public int $resolvedByUserId,
        public array $diningTableIds = [],
        public ?int $approverUserId = null,
        public ?string $approverPin = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            conflictId: (int) $request->route('conflict')->id,
            dismiss: $request->boolean('dismiss'),
            resolution: $request->input('resolution'),
            note: $request->string('note')->toString(),
            resolvedByUserId: (int) $request->user()->id,
            diningTableIds: array_map('intval', $request->input('dining_table_ids', [])),
            approverUserId: $request->filled('approver_user_id') ? $request->integer('approver_user_id') : null,
            approverPin: $request->input('approver_pin'),
        );
    }
}
