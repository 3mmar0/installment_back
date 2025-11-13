<?php

namespace App\Http\Controllers\Api;

use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserLimitResource;
use App\Http\Traits\ApiResponse;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    use ApiResponse;

    /**
     * Public listing of active subscription plans.
     */
    public function publicIndex(): JsonResponse
    {
        $subscriptions = Subscription::active()
            ->orderBy('price')
            ->get();

        return $this->successResponse(
            SubscriptionResource::collection($subscriptions),
            'تم جلب خطط الاشتراك بنجاح'
        );
    }

    /**
     * Owner listing of all subscription plans.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $subscriptions = Subscription::query()
            ->with('creator')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->successResponse(
            SubscriptionResource::collection($subscriptions),
            'تم جلب خطط الاشتراك بنجاح'
        );
    }

    /**
     * Store a new subscription plan.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->generateUniqueSlug($data['slug'] ?? Str::slug($data['name']));
        $data['created_by'] = $request->user()->id;

        $subscription = Subscription::create($data);

        return $this->createdResponse(
            new SubscriptionResource($subscription),
            'تم إنشاء خطة الاشتراك بنجاح'
        );
    }

    /**
     * Display a subscription plan.
     */
    public function show(Subscription $subscription): JsonResponse
    {
        return $this->successResponse(
            new SubscriptionResource($subscription),
            'تم جلب خطة الاشتراك بنجاح'
        );
    }

    /**
     * Update a subscription plan.
     */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['slug'], $subscription->id);
        }

        $subscription->update($data);

        return $this->successResponse(
            new SubscriptionResource($subscription->fresh()),
            'تم تحديث خطة الاشتراك بنجاح'
        );
    }

    /**
     * Delete a subscription plan.
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        $subscription->delete();

        return $this->deletedResponse('تم حذف خطة الاشتراك بنجاح');
    }

    /**
     * Assign a subscription plan to a user.
     */
    public function assign(Request $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,paused,canceled'],
            'features' => ['sometimes', 'nullable', 'array'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $overrides = Arr::only($data, ['start_date', 'end_date', 'status', 'features']);
        $userLimit = LimitsHelper::applySubscriptionToUser($user->id, $subscription, $overrides);

        return $this->successResponse(
            new UserLimitResource($userLimit),
            'تم تعيين الاشتراك بنجاح'
        );
    }

    /**
     * Change user's own subscription (upgrade/downgrade).
     */
    public function changeSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $user = $request->user();

        // Ensure the subscription is active
        if (!$subscription->is_active) {
            return $this->errorResponse('الخطة المحددة غير متاحة حالياً', 400);
        }

        // Apply subscription to the authenticated user
        $overrides = $request->validate([
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,paused,canceled'],
            'features' => ['sometimes', 'nullable', 'array'],
        ]);

        $userLimit = LimitsHelper::applySubscriptionToUser($user->id, $subscription, $overrides);

        return $this->successResponse(
            new UserLimitResource($userLimit),
            'تم تغيير الاشتراك بنجاح'
        );
    }

    /**
     * Ensure slug uniqueness.
     */
    protected function generateUniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($slug);
        $uniqueSlug = $baseSlug;
        $counter = 1;

        while (
            Subscription::where('slug', $uniqueSlug)
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $uniqueSlug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $uniqueSlug;
    }
}
