<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientAccountResource;
use App\Http\Traits\ApiResponse;
use App\Services\ClientAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ClientAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ClientAuthService $clientAuthService
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:client_accounts,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $result = $this->clientAuthService->register($data);

        return $this->createdResponse([
            'client' => new ClientAccountResource($result['client']),
            'requires_verification' => true,
        ], 'تم إنشاء الحساب، يرجى تأكيد البريد الإلكتروني بالرمز المرسل');
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:5', 'regex:/^\d{5}$/'],
        ]);

        try {
            $result = $this->clientAuthService->verifyOtp($data['email'], $data['code']);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'فشل التحقق';

            return $this->errorResponse($message, 422, $e->errors());
        }

        return $this->successResponse([
            'client' => new ClientAccountResource($result['client']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'linked_customers' => $result['linked_customers'],
        ], 'تم تأكيد البريد الإلكتروني بنجاح');
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $client = $this->clientAuthService->findByEmail($data['email']);

        if (! $client) {
            // Do not reveal whether the email exists.
            return $this->successResponse(null, 'إذا كان البريد مسجلاً سيتم إرسال رمز التحقق');
        }

        if ($client->isEmailVerified()) {
            return $this->errorResponse('البريد الإلكتروني مؤكد مسبقاً', 422);
        }

        $this->clientAuthService->sendOtp($client);

        return $this->successResponse(null, 'تم إرسال رمز التحقق');
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->clientAuthService->login($credentials);
        } catch (ValidationException $e) {
            return $this->errorResponse('بيانات الاعتماد غير صحيحة', 401);
        }

        if (! empty($result['requires_verification'])) {
            return $this->successResponse([
                'client' => new ClientAccountResource($result['client']),
                'requires_verification' => true,
            ], 'يرجى تأكيد البريد الإلكتروني أولاً، تم إرسال رمز جديد');
        }

        return $this->successResponse([
            'client' => new ClientAccountResource($result['client']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], 'تم تسجيل الدخول بنجاح');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->clientAuthService->logout($request->user());

        return $this->successResponse(null, 'تم تسجيل الخروج بنجاح');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new ClientAccountResource($request->user()),
            'تم جلب بيانات العميل بنجاح'
        );
    }

    public function refresh(Request $request): JsonResponse
    {
        $token = $this->clientAuthService->refreshToken($request->user());

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'تم تحديث الرمز بنجاح');
    }
}
