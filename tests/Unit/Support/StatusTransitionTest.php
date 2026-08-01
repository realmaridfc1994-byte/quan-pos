<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Support\StatusTransition;

const CHUOI_PHIEU = ['sent', 'preparing', 'served'];
const CHUOI_MON = ['ordered', 'served'];

it('cho đi đúng một bước tới hợp lệ: phiếu sent → preparing', function () {
    expect(fn () => StatusTransition::kiemTra(CHUOI_PHIEU, 'sent', 'preparing'))->not->toThrow(DomainException::class);
});

it('cho đi đúng một bước tới hợp lệ: phiếu preparing → served', function () {
    expect(fn () => StatusTransition::kiemTra(CHUOI_PHIEU, 'preparing', 'served'))->not->toThrow(DomainException::class);
});

it('chặn nhảy cóc: phiếu sent → served bỏ qua preparing', function () {
    expect(fn () => StatusTransition::kiemTra(CHUOI_PHIEU, 'sent', 'served'))
        ->toThrow(DomainException::class, 'Không thể chuyển trạng thái từ "sent" sang "served".');
});

it('chặn lùi: phiếu preparing → sent', function () {
    expect(fn () => StatusTransition::kiemTra(CHUOI_PHIEU, 'preparing', 'sent'))->toThrow(DomainException::class);
});

it('chặn lùi: phiếu served → preparing', function () {
    expect(fn () => StatusTransition::kiemTra(CHUOI_PHIEU, 'served', 'preparing'))->toThrow(DomainException::class);
});

it('cho đi đúng bước tới hợp lệ: dòng món ordered → served', function () {
    expect(fn () => StatusTransition::kiemTra(CHUOI_MON, 'ordered', 'served'))->not->toThrow(DomainException::class);
});

it('chặn lùi: dòng món served → ordered', function () {
    expect(fn () => StatusTransition::kiemTra(CHUOI_MON, 'served', 'ordered'))
        ->toThrow(DomainException::class, 'Không thể chuyển trạng thái từ "served" sang "ordered".');
});

it('chặn trạng thái không nằm trong chuỗi, kể cả khi vị trí tính ra trùng khớp', function () {
    // Bẫy lỗi PHP: array_search trả false cho "cancelled" (không có trong chuỗi),
    // false + 1 === 1 trùng đúng vị trí "served" — phải chặn tường minh, không được lọt.
    expect(fn () => StatusTransition::kiemTra(CHUOI_MON, 'cancelled', 'served'))->toThrow(DomainException::class);
});

it('chặn đứng yên: served → served', function () {
    expect(fn () => StatusTransition::kiemTra(CHUOI_MON, 'served', 'served'))->toThrow(DomainException::class);
});
