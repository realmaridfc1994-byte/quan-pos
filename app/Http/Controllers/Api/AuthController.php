<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Staffing\Actions\AuthenticateUser;
use App\Domain\Staffing\Actions\RevokeCurrentToken;
use App\Domain\Staffing\Actions\VerifyManagerPin;
use App\Domain\Staffing\DTO\LoginData;
use App\Domain\Staffing\DTO\LogoutData;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PinVerifyRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    /** POST /api/v1/auth/login */
    public function login(LoginRequest $request, AuthenticateUser $action): JsonResponse
    {
        $session = $action->handle(LoginData::fromRequest($request));

        return response()->json([
            'data' => [
                'token' => $session->token,
                'user' => UserResource::make($session->user),
            ],
        ]);
    }

    /** POST /api/v1/auth/logout */
    public function logout(Request $request, RevokeCurrentToken $action): JsonResponse
    {
        $action->handle(new LogoutData($request->user()));

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    /** POST /api/v1/auth/pin-verify */
    public function pinVerify(PinVerifyRequest $request, VerifyManagerPin $action): JsonResponse
    {
        $approver = $action->handle(PinVerifyData::fromRequest($request));

        return response()->json([
            'data' => [
                'approved' => true,
                'approver' => UserResource::make($approver),
            ],
        ]);
    }
}
