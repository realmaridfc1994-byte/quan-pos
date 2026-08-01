<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Staffing\Models\User;
use Illuminate\Testing\TestResponse;

function xemMenu(User $user, array $query = []): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/menu?'.http_build_query($query));
}

it('trả về đúng cây nhóm món → món → biến thể → nhóm tùy chọn → tùy chọn', function () {
    $user = User::factory()->staff()->create();
    $category = Category::factory()->create(['name' => 'Bia & nước']);
    $product = Product::factory()->for($category)->create(['name' => 'Bia Tiger']);
    $variant = ProductVariant::factory()->for($product)->create(['name' => 'Lon', 'price' => 25_000]);
    $group = OptionGroup::factory()->forProduct($product)->create(['name' => 'Đá']);
    Option::factory()->for($group, 'optionGroup')->create(['name' => 'Ít đá', 'price_delta' => 0]);

    xemMenu($user)
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Bia & nước')
        ->assertJsonPath('data.0.products.0.name', 'Bia Tiger')
        ->assertJsonPath('data.0.products.0.variants.0.name', 'Lon')
        ->assertJsonPath('data.0.products.0.variants.0.price_text', '25.000 đ')
        ->assertJsonPath('data.0.products.0.option_groups.0.name', 'Đá')
        ->assertJsonPath('data.0.products.0.option_groups.0.options.0.name', 'Ít đá');
});

it('không trả về nhóm món, món, biến thể, tùy chọn đã tắt is_active', function () {
    $user = User::factory()->staff()->create();

    $categoryTat = Category::factory()->inactive()->create();
    Product::factory()->for($categoryTat)->create();

    $category = Category::factory()->create();
    $monTat = Product::factory()->for($category)->inactive()->create();

    $mon = Product::factory()->for($category)->create();
    ProductVariant::factory()->for($mon)->inactive()->create(['name' => 'Biến thể tắt']);
    $bienTheConLai = ProductVariant::factory()->for($mon)->create(['name' => 'Biến thể còn bán']);

    xemMenu($user)->assertOk();

    $response = xemMenu($user);
    $data = $response->json('data');

    expect(collect($data)->pluck('id'))->not->toContain($categoryTat->id)
        ->and(collect($data[0]['products'])->pluck('id'))->not->toContain($monTat->id)
        ->and(collect($data[0]['products'][0]['variants'])->pluck('name'))
        ->toContain('Biến thể còn bán')
        ->not->toContain('Biến thể tắt');
});

it('nhóm tùy chọn gắn theo nhóm món thì mọi món trong nhóm đều thấy', function () {
    $user = User::factory()->staff()->create();
    $category = Category::factory()->create();
    $monA = Product::factory()->for($category)->create();
    $monB = Product::factory()->for($category)->create();
    OptionGroup::factory()->forCategory($category)->create(['name' => 'Đá dùng chung']);

    $data = xemMenu($user)->json('data');
    $products = collect($data[0]['products'])->keyBy('id');

    expect($products[$monA->id]['option_groups'])->toHaveCount(1)
        ->and($products[$monA->id]['option_groups'][0]['name'])->toBe('Đá dùng chung')
        ->and($products[$monB->id]['option_groups'][0]['name'])->toBe('Đá dùng chung');
});

it('updated_since chỉ trả về nhóm món có thay đổi, bỏ qua nhóm không đổi gì', function () {
    $user = User::factory()->staff()->create();
    $khongDoi = Category::factory()->create(['updated_at' => now()->subDays(2)]);
    Product::factory()->for($khongDoi)->create(['updated_at' => now()->subDays(2)]);

    $moc = now()->subHour();

    $coDoi = Category::factory()->create(['updated_at' => now()->subDays(2)]);
    Product::factory()->for($coDoi)->create(['name' => 'Món mới sửa giá', 'updated_at' => now()]);

    $data = xemMenu($user, ['updated_since' => $moc->toIso8601String()])->json('data');

    expect(collect($data)->pluck('id'))
        ->toContain($coDoi->id)
        ->not->toContain($khongDoi->id);
});

it('chưa đăng nhập thì không xem được thực đơn', function () {
    test()->getJson('/api/v1/menu')->assertUnauthorized();
});
