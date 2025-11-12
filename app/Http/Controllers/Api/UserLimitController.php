<?php

namespace App\Http\Controllers\Api;

use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserLimitRequest;
use App\Http\Requests\UpdateUserLimitRequest;
use App\Http\Resources\UserLimitResource;
use App\Http\Traits\ApiResponse;
use App\Models\UserLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLimitController extends Controller
{
    use ApiResponse;

    /**
     * List all user limit profiles.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $limits = UserLimit::with('user')->orderByDesc('id')->paginate($perPage);

        return $this->successResponse(
            UserLimitResource::collection($limits),
            'تم جلب ملفات حدود المستخدمين بنجاح'
        );
    }

    /**
     * Persist a new or existing user limit profile.
     */
    public function store(StoreUserLimitRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $validated['user_id'];
        unset($validated['user_id']);

        $userLimit = LimitsHelper::createOrUpdateUserLimits($userId, $validated);

        return $this->createdResponse(
            new UserLimitResource($userLimit),
            'تم حفظ حدود المستخدم بنجاح'
        );
    }

    /**
     * Display an individual user limit profile.
     */
    public function show(UserLimit $userLimit): JsonResponse
    {
        $userLimit->loadMissing('user');

        return $this->successResponse(
            new UserLimitResource($userLimit),
            'تم جلب ملف حدود المستخدم بنجاح'
        );
    }

    /**
     * Update an existing user limit profile.
     */
    public function update(UpdateUserLimitRequest $request, UserLimit $userLimit): JsonResponse
    {
        $userLimit->update($request->validated());
        LimitsHelper::updateUsageCounts($userLimit->user_id);

        return $this->successResponse(
            new UserLimitResource($userLimit->fresh('user')),
            'تم تحديث حدود المستخدم بنجاح'
        );
    }

    /**
     * Delete a user limit profile.
     */
    public function destroy(UserLimit $userLimit): JsonResponse
    {
        $userLimit->delete();

        return $this->deletedResponse('تم حذف حدود المستخدم بنجاح');
    }

    /**
     * Retrieve the authenticated user's current limits.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $info = LimitsHelper::getSubscriptionInfo($user->id);

        if (!$info) {
            return $this->notFoundResponse('لا توجد حدود مضبوطة لهذا المستخدم');
        }

        return $this->successResponse($info, 'تم جلب الحدود الحالية بنجاح');
    }

    /**
     * Determine whether the user can create a resource.
     */
    public function canCreate(Request $request, string $resourceType): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $canCreate = LimitsHelper::canCreate($user->id, $resourceType);
        $remaining = LimitsHelper::getRemainingCount($user->id, $resourceType);
        $subscriptionInfo = LimitsHelper::getSubscriptionInfo($user->id);

        return $this->successResponse([
            'resource_type' => $resourceType,
            'can_create' => $canCreate,
            'remaining' => $remaining,
            'subscription' => $subscriptionInfo['subscription'] ?? null,
            'message' => $canCreate
                ? 'يمكنك إنشاء موارد إضافية.'
                : LimitsHelper::getLimitExceededMessage($resourceType),
        ]);
    }

    /**
     * Force refresh counters for the authenticated user.
     */
    public function refreshUsage(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        LimitsHelper::updateUsageCounts($user->id);
        $info = LimitsHelper::getSubscriptionInfo($user->id);

        return $this->successResponse($info, 'تم تحديث الاستخدام بنجاح');
    }

    /**
     * Increment resource usage.
     */
    public function increment(Request $request, string $resourceType): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $count = (int) $request->input('count', 1);
        $succeeded = LimitsHelper::incrementUsage($user->id, $resourceType, $count);
        $subscriptionInfo = LimitsHelper::getSubscriptionInfo($user->id);

        if (!$succeeded) {
            return $this->errorResponse('تعذّر زيادة الاستهلاك للمورد المحدد', 422);
        }

        return $this->successResponse([
            'resource_type' => $resourceType,
            'incremented_by' => $count,
            'remaining' => LimitsHelper::getRemainingCount($user->id, $resourceType),
            'subscription' => $subscriptionInfo['subscription'] ?? null,
        ], 'تمت زيادة استهلاك المورد بنجاح');
    }

    /**
     * Decrement resource usage.
     */
    public function decrement(Request $request, string $resourceType): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $count = (int) $request->input('count', 1);
        $succeeded = LimitsHelper::decrementUsage($user->id, $resourceType, $count);
        $subscriptionInfo = LimitsHelper::getSubscriptionInfo($user->id);

        if (!$succeeded) {
            return $this->errorResponse('تعذّر تقليل الاستهلاك للمورد المحدد', 422);
        }

        return $this->successResponse([
            'resource_type' => $resourceType,
            'decremented_by' => $count,
            'remaining' => LimitsHelper::getRemainingCount($user->id, $resourceType),
            'subscription' => $subscriptionInfo['subscription'] ?? null,
        ], 'تم تقليل استهلاك المورد بنجاح');
    }

    /**
     * Determine if a feature is accessible to the authenticated user.
     */
    public function feature(Request $request, string $feature): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $canAccess = LimitsHelper::canAccessFeature($user->id, $feature);
        $subscriptionInfo = LimitsHelper::getSubscriptionInfo($user->id);

        return $this->successResponse([
            'feature' => $feature,
            'can_access' => $canAccess,
            'subscription' => $subscriptionInfo['subscription'] ?? null,
        ], 'تم التحقق من إمكانية الوصول إلى الميزة بنجاح');
    }
}
