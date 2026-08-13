<?php

declare(strict_types=1);

use App\Domain\Ordering\Actions\OpenTableSession;
use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;

/**
 * M3 (docs/schema.md PHẦN M.2): đặt bàn KHÔNG khoá cứng dining_tables — quán
 * vẫn phải bán được cho khách vãng lai ngồi đúng bàn đã có người đặt trước.
 */
it('có một đặt bàn treo trên bàn X không chặn khách vãng lai mở phiên trên chính bàn X', function () {
    Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $ban = DiningTable::factory()->create();

    Reservation::factory()->confirmed()->create([
        'dining_table_id' => $ban->id,
        'reserved_at' => now()->addHours(2),
    ]);

    $luot = app(OpenTableSession::class)->handle(new OpenTableSessionData(
        uuid: (string) Str::uuid(),
        diningTableIds: [$ban->id],
        primaryDiningTableId: $ban->id,
        guestCount: 4,
        openedByUserId: $staff->id,
    ));

    expect($luot->id)->not->toBeNull();
});
