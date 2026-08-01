<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Catalog\Queries\BuildMenu;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuRequest;
use App\Http\Resources\MenuCategoryResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class MenuController extends Controller
{
    /** GET /api/v1/menu */
    public function index(MenuRequest $request, BuildMenu $query): JsonResponse
    {
        $updatedSince = $request->filled('updated_since')
            ? CarbonImmutable::parse($request->string('updated_since')->toString())
            : null;

        return response()->json([
            'data' => MenuCategoryResource::collection($query->handle($updatedSince)),
        ]);
    }
}
