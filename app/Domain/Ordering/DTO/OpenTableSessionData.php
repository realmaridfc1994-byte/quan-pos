<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class OpenTableSessionData
{
    /** @param list<int> $diningTableIds */
    public function __construct(
        public string $uuid,
        public array $diningTableIds,
        public int $primaryDiningTableId,
        public int $guestCount,
        public int $openedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            uuid: $request->string('uuid')->toString(),
            diningTableIds: array_map('intval', $request->input('dining_table_ids', [])),
            primaryDiningTableId: $request->integer('primary_dining_table_id'),
            guestCount: $request->integer('guest_count'),
            openedByUserId: (int) $request->user()->id,
        );
    }
}
