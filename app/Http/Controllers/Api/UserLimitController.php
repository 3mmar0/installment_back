<?php

namespace App\Http\Controllers\Api;

use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserLimitRequest;
use App\Http\Requests\UpdateUserLimitRequest;
use App\Http\Resources\UserLimitResource;
use App\Http\Traits\ApiResponse;
use App\Models\MerchantGift;
use App\Models\User;
use App\Models\UserLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /**
     * Reset monthly AI/WhatsApp usage for a merchant.
     */
    public function resetUsage(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'clear_bonus' => ['sometimes', 'boolean'],
        ]);

        $limit = LimitsHelper::resetMonthlyUsage(
            $user->id,
            (bool) ($data['clear_bonus'] ?? false)
        );

        return $this->successResponse(
            new UserLimitResource($limit->loadMissing('user')),
            'تم إعادة ضبط استخدام الرسائل الشهري'
        );
    }

    /**
     * Grant a gift bonus to a merchant.
     */
    public function grantGift(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'resource' => ['required', 'string', Rule::in(MerchantGift::RESOURCES)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:500'],
            'source' => ['sometimes', 'string', Rule::in(['gift', 'admin_adjust'])],
        ]);

        $gift = LimitsHelper::grantGift(
            $user->id,
            $data['resource'],
            (int) $data['amount'],
            $request->user()?->id,
            $data['note'] ?? null,
            $data['source'] ?? 'gift'
        );

        $limit = LimitsHelper::getUserLimits($user->id);

        return $this->successResponse([
            'gift' => $gift,
            'limits' => $limit ? new UserLimitResource($limit) : null,
        ], 'تم منح الهدية بنجاح');
    }

    /**
     * List gifts granted to a merchant.
     */
    public function gifts(User $user): JsonResponse
    {
        $gifts = MerchantGift::query()
            ->with('granter:id,name,email')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->successResponse($gifts, 'تم جلب سجل الهدايا');
    }

    /**
     * Authenticated merchant gift history (no granter PII beyond note/amount).
     */
    public function myGifts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorizedResponse();
        }

        $gifts = MerchantGift::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'resource', 'amount', 'note', 'source', 'created_at']);

        return $this->successResponse($gifts, 'تم جلب سجل الهدايا');
    }
}
