<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;
use App\Support\CauHinhQuan;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Đổi ngưỡng là nới lỏng hoặc siết chặt một lá chắn của quán. Việc đó phải để
 * lại dấu vết: ai đổi, lúc nào, từ bao nhiêu sang bao nhiêu. Ba tháng sau nhìn
 * lại một lô hao hụt lớn mà không ai phải duyệt PIN, chủ quán cần trả lời được
 * câu "ngưỡng lúc đó là bao nhiêu, và ai hạ nó xuống".
 */
beforeEach(function () {
    $this->chuQuan = User::factory()->owner()->create(['name' => 'Chủ quán Tư']);
    $this->actingAs($this->chuQuan);
});

it('đổi ngưỡng hao hụt cần PIN thì ghi một dòng nhật ký đủ ai, khi nào, từ gì sang gì', function () {
    (new CauHinhQuan)->datNguongHaoHutCanPin(50_000);

    $dong = Activity::query()
        ->where('log_name', CauHinhQuan::LOG_NAME)
        ->where('event', CauHinhQuan::KHOA_HAO_HUT_PIN)
        ->sole();

    expect($dong->causer_id)->toBe($this->chuQuan->id)
        ->and($dong->properties['gia_tri_cu'])->toBe(200_000)
        ->and($dong->properties['gia_tri_moi'])->toBe(50_000)
        ->and($dong->properties['nguon_gia_tri_cu'])->toBe('mac_dinh')
        ->and($dong->created_at)->not->toBeNull();
});

it('đổi ngưỡng lãi thấp cũng ghi nhật ký, và lần đổi thứ hai ghi đúng số cũ là số vừa đặt', function () {
    $cauHinh = new CauHinhQuan;

    $cauHinh->datNguongLaiThapPhanTram(40);
    (new CauHinhQuan)->datNguongLaiThapPhanTram(35);

    $dong = Activity::query()
        ->where('log_name', CauHinhQuan::LOG_NAME)
        ->where('event', CauHinhQuan::KHOA_LAI_THAP)
        ->orderBy('id')
        ->get();

    expect($dong)->toHaveCount(2)
        ->and($dong[0]->properties['gia_tri_cu'])->toBe(25)
        ->and($dong[0]->properties['gia_tri_moi'])->toBe(40)
        ->and($dong[0]->properties['nguon_gia_tri_cu'])->toBe('mac_dinh')
        ->and($dong[1]->properties['gia_tri_cu'])->toBe(40)
        ->and($dong[1]->properties['gia_tri_moi'])->toBe(35)
        ->and($dong[1]->properties['nguon_gia_tri_cu'])->toBe('nguoi_doi');
});

it('hạ mốc đã kiểm thiếu giá vốn cũng để lại dấu vết', function () {
    (new CauHinhQuan)->haMocNgayKiemThieuGiaVon(Carbon::parse('2026-06-01'));

    $dong = Activity::query()
        ->where('log_name', CauHinhQuan::LOG_NAME)
        ->where('event', CauHinhQuan::KHOA_MOC_KIEM_THIEU_GIA_VON)
        ->sole();

    expect($dong->causer_id)->toBe($this->chuQuan->id)
        ->and($dong->properties['gia_tri_cu'])->toBe('2026-08-10')
        ->and($dong->properties['gia_tri_moi'])->toBe('2026-06-01');
});

it('đặt lại về mặc định cũng là một lần đổi, cũng phải ghi nhật ký', function () {
    $cauHinh = new CauHinhQuan;
    $cauHinh->datNguongHaoHutCanPin(50_000);

    (new CauHinhQuan)->datLaiMacDinh();

    $dong = Activity::query()
        ->where('log_name', CauHinhQuan::LOG_NAME)
        ->where('event', CauHinhQuan::KHOA_HAO_HUT_PIN)
        ->orderBy('id')
        ->get();

    expect($dong)->toHaveCount(2)
        ->and($dong[1]->properties['gia_tri_cu'])->toBe(50_000)
        ->and($dong[1]->properties['gia_tri_moi'])->toBe(200_000);
});

it('vào chỉnh mà giữ nguyên số cũ vẫn ghi nhật ký — "đã có người vào đây" cũng là điều đáng biết', function () {
    (new CauHinhQuan)->datNguongHaoHutCanPin(200_000);

    $dong = Activity::query()
        ->where('log_name', CauHinhQuan::LOG_NAME)
        ->where('event', CauHinhQuan::KHOA_HAO_HUT_PIN)
        ->sole();

    expect($dong->properties['gia_tri_cu'])->toBe(200_000)
        ->and($dong->properties['gia_tri_moi'])->toBe(200_000);
});
