<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientAccountResource;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Services\UnifiedAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UnifiedAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UnifiedAuthService $unifiedAuthService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->unifiedAuthService->login($credentials);
        } catch (ValidationException $e) {
            return $this->errorResponse('بيانات الاعتماد غير صحيحة', 401);
        }

        if ($result['account_type'] === 'vendor') {
            return $this->successResponse([
                'account_type' => 'vendor',
                'user' => new UserResource($result['user']->load(['userLimit'])),
                'token' => $result['token'],
                'token_type' => $result['token_type'],
            ], 'تم تسجيل الدخول بنجاح');
        }

        if (! empty($result['requires_verification'])) {
            return $this->successResponse([
                'account_type' => 'client',
                'requires_verification' => true,
                'client' => new ClientAccountResource($result['client']),
            ], 'يرجى تأكيد البريد الإلكتروني');
        }

        return $this->successResponse([
            'account_type' => 'client',
            'client' => new ClientAccountResource($result['client']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], 'تم تسجيل الدخول بنجاح');
    }
}
