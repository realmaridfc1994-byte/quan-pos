<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;

it('GET bill trả đúng tổng phải thu và còn thiếu bao nhiêu', function () {
    $ca = Shift::factory()->open()->create();
    $thuNgan = User::factory()->cashier()->create();
    $luot = TableSession::factory()->for($ca, 'shift')->create([
        'subtotal_amount' => 500_000,
        'discount_amount' => 50_000,
        'discount_reason' => 'Khách quen',
        'total_amount' => 450_000,
        'paid_amount' => 200_000,
        'status' => TableSessionStatus::Billing,
    ]);

    test()->getJson("/api/v1/table-sessions/{$luot->id}/bill", authHeaderFor($thuNgan))
        ->assertOk()
        ->assertJsonPath('data.subtotal_amount', 500_000)
        ->assertJsonPath('data.discount_amount', 50_000)
        ->assertJsonPath('data.total_amount', 450_000)
        ->assertJsonPath('data.paid_amount', 200_000)
        ->assertJsonPath('data.remaining_amount', 250_000);
});
