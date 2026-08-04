<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\User;

beforeEach(function () {
    config([
        'vietqr.bank_bin' => '970436',
        'vietqr.account_number' => '0123456789',
        'vietqr.account_name' => 'Quan Nhau ABC',
    ]);

    $this->nhanVien = User::factory()->cashier()->create();
});

it('số tiền trong mã QR khớp đúng số còn thiếu, nội dung có mã lượt khách', function () {
    $session = TableSession::factory()->withTable()->create([
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 500_000,
        'total_amount' => 500_000,
        'paid_amount' => 120_000,
    ]);

    $this->getJson("/api/v1/table-sessions/{$session->id}/vietqr", authHeaderFor($this->nhanVien))
        ->assertOk()
        ->assertJsonPath('data.amount', 380_000)
        ->assertJsonPath('data.amount_text', '380.000 đ')
        ->assertJsonPath('data.table_session_code', $session->code)
        ->assertJsonPath('data.account_name', 'Quan Nhau ABC')
        ->assertJsonPath('data.bank_bin', '970436')
        ->assertJson(fn ($json) => $json->has('data.payload')
            ->where('data.payload', fn ($payload) => str_contains($payload, $session->code) && str_contains($payload, '380000'))
        );
});

it('lượt khách đã thu đủ tiền thì không sinh mã QR', function () {
    $session = TableSession::factory()->withTable()->create([
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
        'paid_amount' => 380_000,
    ]);

    $this->getJson("/api/v1/table-sessions/{$session->id}/vietqr", authHeaderFor($this->nhanVien))
        ->assertUnprocessable();
});

it('lượt khách đã đóng thì không sinh mã QR', function () {
    $session = TableSession::factory()->closed()->withTable()->create([
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
        'paid_amount' => 380_000,
    ]);

    $this->getJson("/api/v1/table-sessions/{$session->id}/vietqr", authHeaderFor($this->nhanVien))
        ->assertUnprocessable();
});

it('lượt khách đã huỷ thì không sinh mã QR', function () {
    $session = TableSession::factory()->withTable()->create([
        'status' => TableSessionStatus::Void,
        'voided_at' => now(),
        'void_reason' => 'Khách bỏ về',
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
    ]);

    $this->getJson("/api/v1/table-sessions/{$session->id}/vietqr", authHeaderFor($this->nhanVien))
        ->assertUnprocessable();
});

it('chưa cấu hình tài khoản ngân hàng thì báo rõ, không sinh mã QR trống', function () {
    config(['vietqr.bank_bin' => null, 'vietqr.account_number' => null, 'vietqr.account_name' => null]);

    $session = TableSession::factory()->withTable()->create([
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
        'paid_amount' => 0,
    ]);

    $this->getJson("/api/v1/table-sessions/{$session->id}/vietqr", authHeaderFor($this->nhanVien))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Chưa cấu hình tài khoản ngân hàng của quán (VIETQR_BANK_BIN/VIETQR_ACCOUNT_NUMBER/VIETQR_ACCOUNT_NAME trong .env).']);
});

it('không hỏi được nếu chưa đăng nhập', function () {
    $session = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Billing]);

    $this->getJson("/api/v1/table-sessions/{$session->id}/vietqr")->assertUnauthorized();
});
