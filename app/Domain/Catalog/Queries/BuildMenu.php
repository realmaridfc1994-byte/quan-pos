<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Dựng cây thực đơn cho máy POS: nhóm món → món → biến thể → nhóm tùy chọn → tùy chọn.
 * Chỉ lấy bản ghi is_active = 1 ở mọi tầng.
 *
 * Lọc `updated_since` theo TỪNG NHÓM MÓN, không theo từng món: nhóm nào có bất
 * kỳ thay đổi gì bên trong (chính nhóm, món, biến thể, nhóm tùy chọn, tùy chọn)
 * kể từ mốc đó thì trả về NGUYÊN nhóm; nhóm không đổi gì thì bỏ qua hẳn. Đơn
 * giản hơn lọc từng món lẻ (không phải ghép lại nhóm tùy chọn theo nhóm món
 * vào từng món con khi lọc riêng), phù hợp quy mô một quán 5-15 bàn.
 */
final class BuildMenu
{
    /** @return Collection<int, Category> */
    public function handle(?CarbonInterface $updatedSince): Collection
    {
        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')->get();

        $nhomTuyChonTheoNhom = OptionGroup::query()
            ->whereNotNull('category_id')
            ->where('is_active', true)
            ->with(['options' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category_id');

        $monTheoNhom = Product::query()
            ->where('is_active', true)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'optionGroups' => fn ($q) => $q->where('is_active', true)
                    ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')])
                    ->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category_id');

        return $categories
            ->filter(fn (Category $category) => $updatedSince === null
                || $this->nhomMonCoThayDoi($category, $monTheoNhom, $nhomTuyChonTheoNhom, $updatedSince))
            ->map(function (Category $category) use ($monTheoNhom, $nhomTuyChonTheoNhom) {
                $monCuaNhom = $monTheoNhom->get($category->id, collect())
                    ->map(function (Product $product) use ($category, $nhomTuyChonTheoNhom) {
                        $product->setRelation(
                            'optionGroups',
                            $product->optionGroups
                                ->concat($nhomTuyChonTheoNhom->get($category->id, collect()))
                                ->sortBy('sort_order')
                                ->values()
                        );

                        return $product;
                    })
                    ->values();

                $category->setRelation('products', $monCuaNhom);

                return $category;
            })
            ->values();
    }

    /**
     * @param  Collection<int, Collection<int, Product>>  $monTheoNhom
     * @param  Collection<int, Collection<int, OptionGroup>>  $nhomTuyChonTheoNhom
     */
    private function nhomMonCoThayDoi(
        Category $category,
        Collection $monTheoNhom,
        Collection $nhomTuyChonTheoNhom,
        CarbonInterface $updatedSince,
    ): bool {
        if ($category->updated_at->greaterThanOrEqualTo($updatedSince)) {
            return true;
        }

        foreach ($nhomTuyChonTheoNhom->get($category->id, collect()) as $nhom) {
            if ($nhom->updated_at->greaterThanOrEqualTo($updatedSince)) {
                return true;
            }
            foreach ($nhom->options as $tuyChon) {
                if ($tuyChon->updated_at->greaterThanOrEqualTo($updatedSince)) {
                    return true;
                }
            }
        }

        foreach ($monTheoNhom->get($category->id, collect()) as $mon) {
            if ($mon->updated_at->greaterThanOrEqualTo($updatedSince)) {
                return true;
            }
            foreach ($mon->variants as $bienThe) {
                if ($bienThe->updated_at->greaterThanOrEqualTo($updatedSince)) {
                    return true;
                }
            }
            foreach ($mon->optionGroups as $nhom) {
                if ($nhom->updated_at->greaterThanOrEqualTo($updatedSince)) {
                    return true;
                }
                foreach ($nhom->options as $tuyChon) {
                    if ($tuyChon->updated_at->greaterThanOrEqualTo($updatedSince)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
