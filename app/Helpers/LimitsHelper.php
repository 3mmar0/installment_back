<?php

namespace App\Helpers;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\SubscriptionAssignment;
use App\Models\User;
use App\Models\UserLimit;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LimitsHelper
{
    /**
     * Default configuration for limits when no plan data is supplied.
     *
     * @var array<string, mixed>
     */
    protected const DEFAULT_LIMITS = [
        'customers' => ['from' => 0, 'to' => 50],
        'installments' => ['from' => 0, 'to' => 50],
        'notifications' => ['from' => 0, 'to' => 200],
        'reports' => true,
        'features' => [],
    ];

    /**
     * Create or update user limits using provided attributes.
     */
    public static function createOrUpdateUserLimits(int $userId, array $attributes = []): UserLimit
    {
        // Get existing user limit to preserve current usage
        $existingLimit = UserLimit::where('user_id', $userId)->first();
        
        $payload = self::buildPayloadFromAttributes($attributes);
        $payload['subscription_name'] ??= 'Custom Plan';
        $payload['subscription_slug'] ??= Str::slug($payload['subscription_name']);
        $payload['status'] ??= 'active';

        $payload['start_date'] = self::resolveDate($payload['start_date'] ?? now());
        $payload['end_date'] = isset($payload['end_date']) && $payload['end_date'] !== null
            ? self::resolveDate($payload['end_date'])
            : null;

        $payload['customers'] = self::normalizeLimitConfig('customers', $payload['customers'] ?? null);
        $payload['installments'] = self::normalizeLimitConfig('installments', $payload['installments'] ?? null);
        $payload['notifications'] = self::normalizeLimitConfig('notifications', $payload['notifications'] ?? null);
        $payload['reports'] = $payload['reports'] ?? self::DEFAULT_LIMITS['reports'];
        $payload['features'] = $payload['features'] ?? self::DEFAULT_LIMITS['features'];

        // Preserve current usage counts if they exist
        if ($existingLimit) {
            $payload['customers_used'] = $existingLimit->customers_used ?? 0;
            $payload['installments_used'] = $existingLimit->installments_used ?? 0;
            $payload['notifications_used'] = $existingLimit->notifications_used ?? 0;
        }

        $userLimit = UserLimit::updateOrCreate(
            ['user_id' => $userId],
            $payload
        );

        // Update usage counts from actual database records to ensure accuracy
        self::updateUsageCounts($userId);

        return $userLimit->fresh();
    }

    /**
     * Apply a subscription plan to a user and sync user limits accordingly.
     */
    public static function applySubscriptionToUser(int $userId, Subscription $subscription, array $overrides = []): UserLimit
    {
        $startDate = self::resolveDate($overrides['start_date'] ?? now());
        $endDate = self::calculateEndDate($subscription->duration, $startDate, $overrides['end_date'] ?? null);

        $assignment = SubscriptionAssignment::create([
            'user_id' => $userId,
            'subscription_id' => $subscription->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $overrides['status'] ?? 'active',
            'features' => $overrides['features'] ?? $subscription->features,
        ]);

        $payload = [
            'subscription_id' => $subscription->id,
            'subscription_assignment_id' => $assignment->id,
            'subscription_name' => $subscription->name,
            'subscription_slug' => $subscription->slug,
            'currency' => $subscription->currency,
            'price' => $subscription->price,
            'duration' => $subscription->duration,
            'description' => $subscription->description,
            'customers' => $subscription->customers,
            'installments' => $subscription->installments,
            'notifications' => $subscription->notifications,
            'reports' => $subscription->reports,
            'features' => $assignment->features,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $assignment->status,
        ];

        $overrides = Arr::except($overrides, ['start_date', 'end_date']);

        return self::createOrUpdateUserLimits($userId, array_replace($payload, $overrides));
    }

    /**
     * Update usage counts for a user.
     */
    public static function updateUsageCounts(int $userId): void
    {
        $userLimit = UserLimit::where('user_id', $userId)->first();

        if (!$userLimit) {
            return;
        }

        $userLimit->update([
            'customers_used' => Customer::where('user_id', $userId)->count(),
            'installments_used' => Installment::where('user_id', $userId)->count(),
            'notifications_used' => Notification::where('user_id', $userId)->count(),
        ]);
    }

    /**
     * Retrieve the active user limits for a user.
     */
    public static function getUserLimits(int $userId): ?UserLimit
    {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        if ($user->role === UserRole::Owner) {
            return $user->userLimit ?: self::createOrUpdateUserLimits($userId, [
                'subscription_name' => 'Owner Unlimited',
                'subscription_slug' => 'owner-unlimited',
                'customers' => ['from' => 0, 'to' => null],
                'installments' => ['from' => 0, 'to' => null],
                'notifications' => ['from' => 0, 'to' => null],
                'reports' => true,
                'features' => ['all' => true],
            ]);
        }

        return UserLimit::forUser($userId)->active()->first();
    }

    /**
     * Determine whether a user can create a specific resource.
     */
    public static function canCreate(int $userId, string $resourceType): bool
    {
        $user = User::find($userId);
        if ($user && $user->role === UserRole::Owner) {
            return true;
        }

        $userLimit = self::getUserLimits($userId);

        return $userLimit ? $userLimit->canCreate($resourceType) : false;
    }

    /**
     * Get remaining count for a resource.
     */
    public static function getRemainingCount(int $userId, string $resourceType): int
    {
        $user = User::find($userId);
        if ($user && $user->role === UserRole::Owner) {
            return PHP_INT_MAX;
        }

        $userLimit = self::getUserLimits($userId);

        return $userLimit ? $userLimit->getRemainingCount($resourceType) : 0;
    }

    /**
     * Increment usage for a resource.
     */
    public static function incrementUsage(int $userId, string $resourceType, int $count = 1): bool
    {
        $userLimit = self::getUserLimits($userId);

        if (!$userLimit) {
            return false;
        }

        return $userLimit->incrementUsage($resourceType, $count);
    }

    /**
     * Decrement usage for a resource.
     */
    public static function decrementUsage(int $userId, string $resourceType, int $count = 1): bool
    {
        $userLimit = self::getUserLimits($userId);

        if (!$userLimit) {
            return false;
        }

        return $userLimit->decrementUsage($resourceType, $count);
    }

    /**
     * Determine whether a user can access a feature.
     */
    public static function canAccessFeature(int $userId, string $feature): bool
    {
        $user = User::find($userId);
        if ($user && $user->role === UserRole::Owner) {
            return true;
        }

        $userLimit = self::getUserLimits($userId);

        return $userLimit ? $userLimit->canAccessFeature($feature) : false;
    }

    /**
     * Friendly limit exceeded messages per resource.
     */
    public static function getLimitExceededMessage(string $resourceType): string
    {
        $messages = [
            'customers' => 'لقد وصلت إلى الحد الأقصى لعدد العملاء المسموح به في خطتك.',
            'installments' => 'لقد وصلت إلى الحد الأقصى لعدد الأقساط المسموح بها في خطتك.',
            'notifications' => 'لقد وصلت إلى الحد الأقصى لعدد الإشعارات المسموح بها في خطتك.',
            'reports' => 'ميزة التقارير غير متاحة في خطتك الحالية.',
        ];

        return $messages[$resourceType] ?? 'لقد وصلت إلى الحد المسموح لهذا المورد.';
    }

    /**
     * Snapshot of the subscription and limits for the user.
     */
    public static function getSubscriptionInfo(int $userId): ?array
    {
        $userLimit = self::getUserLimits($userId);

        if (!$userLimit) {
            return null;
        }

        return [
            'subscription' => [
                'name' => $userLimit->subscription_name,
                'slug' => $userLimit->subscription_slug,
                'price' => $userLimit->price,
                'currency' => $userLimit->currency,
                'duration' => $userLimit->duration,
                'description' => $userLimit->description,
                'start_date' => optional($userLimit->start_date)->toDateString(),
                'end_date' => optional($userLimit->end_date)->toDateString(),
                'status' => $userLimit->status,
            ],
            'limits' => [
                'customers' => $userLimit->customers,
                'installments' => $userLimit->installments,
                'notifications' => $userLimit->notifications,
                'features' => $userLimit->features,
                'reports' => $userLimit->reports,
            ],
            'usage' => [
                'customers_used' => $userLimit->customers_used,
                'installments_used' => $userLimit->installments_used,
                'notifications_used' => $userLimit->notifications_used,
            ],
            'remaining' => [
                'customers' => $userLimit->getRemainingCount('customers'),
                'installments' => $userLimit->getRemainingCount('installments'),
                'notifications' => $userLimit->getRemainingCount('notifications'),
            ],
        ];
    }

    /**
     * Determine if the user's subscription is active.
     */
    public static function isSubscriptionActive(int $userId): bool
    {
        $user = User::find($userId);
        if ($user && $user->role === UserRole::Owner) {
            return true;
        }

        $userLimit = self::getUserLimits($userId);

        return $userLimit ? $userLimit->isActive() : false;
    }

    /**
     * Build a normalized payload from arbitrary attributes.
     *
     * @return array<string, mixed>
     */
    protected static function buildPayloadFromAttributes(array $attributes): array
    {
        $allowed = [
            'subscription_id',
            'subscription_assignment_id',
            'subscription_name',
            'subscription_slug',
            'currency',
            'price',
            'duration',
            'description',
            'customers',
            'installments',
            'notifications',
            'reports',
            'features',
            'start_date',
            'end_date',
            'status',
        ];

        return Arr::only($attributes, $allowed) + [
            'currency' => $attributes['currency'] ?? 'EGP',
            'price' => $attributes['price'] ?? 0,
            'duration' => $attributes['duration'] ?? 'monthly',
        ];
    }

    /**
     * Normalize limit configuration values.
     */
    protected static function normalizeLimitConfig(string $key, mixed $value): ?array
    {
        if ($value === null) {
            return self::DEFAULT_LIMITS[$key] ?? null;
        }

        if (is_array($value)) {
            $from = isset($value['from']) ? max((int) $value['from'], 0) : 0;
            $to = isset($value['to']) ? max((int) $value['to'], 0) : null;
            $max = isset($value['max']) ? max((int) $value['max'], 0) : null;

            if ($max !== null) {
                $to = $max;
            }

            if ($to !== null && $to < $from) {
                $to = $from;
            }

            return [
                'from' => $from,
                'to' => $to,
            ];
        }

        if (is_numeric($value)) {
            $limit = max((int) $value, 0);

            return [
                'from' => 0,
                'to' => $limit,
            ];
        }

        return self::DEFAULT_LIMITS[$key] ?? null;
    }

    /**
     * Resolve various date inputs to Carbon instances or null.
     *
     * @param  mixed  $value
     */
    protected static function resolveDate(mixed $value): Carbon
    {
        if ($value === null) {
            return Carbon::now();
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        return Carbon::parse((string) $value);
    }

    /**
     * Calculate subscription end date based on duration.
     */
    protected static function calculateEndDate(string $duration, Carbon $startDate, mixed $override = null): ?Carbon
    {
        if ($override !== null) {
            return self::resolveDate($override);
        }

        return match ($duration) {
            'monthly' => (clone $startDate)->addMonth(),
            'yearly' => (clone $startDate)->addYear(),
            default => null,
        };
    }
}
