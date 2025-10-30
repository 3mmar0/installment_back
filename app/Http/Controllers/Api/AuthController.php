<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthServiceInterface $authService
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
                'user' => new UserResource($result['user']->load(['latestSubscription.plan'])),
                'token' => $result['token'],
                'token_type' => $result['token_type'],
            ], 'Login successful');
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid credentials', 401);
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
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'free_trial' => ['sometimes', 'boolean'],
        ]);

        $result = $this->authService->register($data);

        // Optionally create a subscription if plan_id provided (with optional free_trial)
        if (!empty($data['plan_id'])) {
            $plan = Plan::active()->find($data['plan_id']);
            if ($plan) {
                DB::transaction(function () use ($result, $plan, $data) {
                    $startsAt = now();
                    $useTrial = (bool) ($data['free_trial'] ?? false);
                    if ($useTrial) {
                        $trialDays = (int) ($plan->trial_days ?? 14);
                        $endsAt = (clone $startsAt)->addDays($trialDays);
                    } else {
                        $endsAt = $plan->interval === 'monthly'
                            ? (clone $startsAt)->addMonth()
                            : (clone $startsAt)->addYear();
                    }

                    Subscription::create([
                        'user_id' => $result['user']->id,
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'next_due_at' => $endsAt,
                        'amount_cents' => (int) $plan->price_cents,
                        'paid_cents' => 0,
                    ]);
                });
            }
        }

        return $this->createdResponse([
            'user' => new UserResource($result['user']->load(['latestSubscription.plan'])),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], 'Registration successful');
    }

    /**
     * Logout user and revoke tokens.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logout successful');
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()->load(['latestSubscription.plan'])),
            'User retrieved successfully'
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
        ], 'Token refreshed successfully');
    }
}
