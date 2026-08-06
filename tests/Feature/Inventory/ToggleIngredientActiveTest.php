<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\ToggleIngredientActive;
use App\Domain\Inventory\Models\Ingredient;

it('bật lại một nguyên liệu đã ngừng dùng', function () {
    $ingredient = Ingredient::factory()->inactive()->create();

    app(ToggleIngredientActive::class)->handle($ingredient);

    expect($ingredient->refresh()->is_active)->toBeTrue();
});

it('ngừng dùng một nguyên liệu đang hoạt động', function () {
    $ingredient = Ingredient::factory()->create();

    app(ToggleIngredientActive::class)->handle($ingredient);

    expect($ingredient->refresh()->is_active)->toBeFalse();
});
