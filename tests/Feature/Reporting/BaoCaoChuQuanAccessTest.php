<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;

it('chủ quán vào được trang báo cáo', function () {
    actingAsUser(User::factory()->owner()->create());

    $this->get('/admin/bao-cao-chu-quan')->assertOk();
});

it('thu ngân KHÔNG vào được trang báo cáo dù vào được panel', function () {
    actingAsUser(User::factory()->cashier()->create());

    $this->get('/admin/bao-cao-chu-quan')->assertForbidden();
});

it('nhân viên phục vụ không vào được trang báo cáo', function () {
    actingAsUser(User::factory()->staff()->create());

    $this->get('/admin/bao-cao-chu-quan')->assertForbidden();
});
