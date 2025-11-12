<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\AuthServiceInterface;
use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Models\Subscription;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Login user and generate token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->authService->login($credentials);

            return $this->successResponse([
                'user' => new UserResource($result['user']->load(['userLimit'])),
                'token' => $result['token'],
                'token_type' => $result['token_type'],
            ], 'تم تسجيل الدخول بنجاح');
        } catch (ValidationException $e) {
            return $this->errorResponse('بيانات الاعتماد غير صحيحة', 401);
        }
    }

    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
        ]);

        $result = $this->authService->register($data);
        $newUser = $result['user'];

        // Notify owners about the new user
        $owners = User::where('role', UserRole::Owner)->get();
        foreach ($owners as $owner) {
            $this->notificationService->notifyNewUserRegistered($owner, $newUser);
        }

        $subscriptionId = $data['subscription_id'] ?? null;
        $subscription = $subscriptionId
            ? Subscription::active()->find($subscriptionId)
            : Subscription::active()->where('slug', 'free')->first();

        if ($subscription) {
            LimitsHelper::applySubscriptionToUser($result['user']->id, $subscription);
                    } else {
            LimitsHelper::createOrUpdateUserLimits($result['user']->id);
        }

        return $this->createdResponse([
            'user' => new UserResource($result['user']->load(['userLimit'])),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], 'تم إنشاء الحساب بنجاح');
    }

    /**
     * Logout user and revoke tokens.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'تم تسجيل الخروج بنجاح');
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()->load(['userLimit'])),
            'تم جلب بيانات المستخدم بنجاح'
        );
    }

    /**
     * Refresh user token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $this->authService->refreshToken($request->user());

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'تم تحديث الرمز بنجاح');
    }
}
