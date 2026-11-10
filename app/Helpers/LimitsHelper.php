<?php

namespace App\Helpers;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\MerchantGift;
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
        'customers' => ['from' => 0, 'to' => 1000],
        'installments' => ['from' => 0, 'to' => 1000],
        'notifications' => ['from' => 0, 'to' => 1000],
        'assistant_messages' => ['from' => 0, 'to' => 10],
        'whatsapp_messages' => ['from' => 0, 'to' => 10],
        'reports' => true,
        'features' => [
            'basic_reports' => true,
            'internal_credit_score' => false,
            'assistant' => true,
            'whatsapp' => false,
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function freePlanAttributes(): array
    {
        return [
            'name' => 'الخطه المجانية',
            'currency' => 'EGP',
            'price' => 0,
            'duration' => 'monthly',
            'description' => 'الخطة المجانية الحالية لجميع المستخدمين.',
            'customers' => self::DEFAULT_LIMITS['customers'],
            'installments' => self::DEFAULT_LIMITS['installments'],
            'notifications' => self::DEFAULT_LIMITS['notifications'],
            // Voice form filling stays available locally in the mobile app;
            // the hosted AI chat and WhatsApp automation require a paid plan.
            'assistant_messages' => ['from' => 0, 'to' => 0],
            'whatsapp_messages' => ['from' => 0, 'to' => 0],
            'reports' => self::DEFAULT_LIMITS['reports'],
            'features' => array_replace(self::DEFAULT_LIMITS['features'], [
                'assistant' => false,
                'whatsapp' => false,
            ]),
            'is_active' => true,
        ];
    }

    public static function ensureFreePlan(): Subscription
    {
        $attributes = self::freePlanAttributes();
        $attributes['created_by'] = $attributes['created_by']
            ?? User::query()->where('role', UserRole::Owner)->value('id')
            ?? User::query()->value('id');

        return Subscription::query()->updateOrCreate(
            ['slug' => 'free'],
            $attributes
        );
    }

    /**
     * Move all regular users to the free plan and refresh their limits.
     *
     * @return array{synced: int, plan: string}
     */
    public static function syncAllUsersToFreePlan(): array
    {
        $freePlan = self::ensureFreePlan();

        Subscription::query()
            ->where('slug', '!=', 'free')
            ->update(['is_active' => false]);

        $synced = 0;

        User::query()
            ->where('role', UserRole::User)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($freePlan, &$synced) {
                foreach ($users as $user) {
                    SubscriptionAssignment::query()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->update([
                            'status' => 'canceled',
                            'end_date' => now()->toDateString(),
                        ]);

                    self::applySubscriptionToUser($user->id, $freePlan);
                    self::updateUsageCounts($user->id);
                    $synced++;
                }
            });

        return [
            'synced' => $synced,
            'plan' => $freePlan->name,
        ];
    }

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
        $payload['assistant_messages'] = self::normalizeLimitConfig('assistant_messages', $payload['assistant_messages'] ?? null);
        $payload['whatsapp_messages'] = self::normalizeLimitConfig('whatsapp_messages', $payload['whatsapp_messages'] ?? null);
        $payload['reports'] = $payload['reports'] ?? self::DEFAULT_LIMITS['reports'];
        $payload['features'] = self::normalizeFeatures($payload['features'] ?? null);

        // Preserve current usage unless explicitly provided in attributes (tests / admin).
        if ($existingLimit) {
            $payload['customers_used'] = $existingLimit->customers_used ?? 0;
            $payload['installments_used'] = $existingLimit->installments_used ?? 0;
            $payload['notifications_used'] = $existingLimit->notifications_used ?? 0;
            $payload['assistant_messages_used'] = array_key_exists('assistant_messages_used', $attributes)
                ? (int) $attributes['assistant_messages_used']
                : ($existingLimit->assistant_messages_used ?? 0);
            $payload['whatsapp_messages_used'] = array_key_exists('whatsapp_messages_used', $attributes)
                ? (int) $attributes['whatsapp_messages_used']
                : ($existingLimit->whatsapp_messages_used ?? 0);
            $payload['assistant_messages_bonus'] = array_key_exists('assistant_messages_bonus', $attributes)
                ? (int) $attributes['assistant_messages_bonus']
                : ($existingLimit->assistant_messages_bonus ?? 0);
            $payload['whatsapp_messages_bonus'] = array_key_exists('whatsapp_messages_bonus', $attributes)
                ? (int) $attributes['whatsapp_messages_bonus']
                : ($existingLimit->whatsapp_messages_bonus ?? 0);
            $payload['usage_period'] = array_key_exists('usage_period', $attributes)
                ? $attributes['usage_period']
                : ($existingLimit->usage_period ?? now()->format('Y-m'));
        } else {
            $payload['assistant_messages_used'] = (int) ($attributes['assistant_messages_used'] ?? 0);
            $payload['whatsapp_messages_used'] = (int) ($attributes['whatsapp_messages_used'] ?? 0);
            $payload['assistant_messages_bonus'] = (int) ($attributes['assistant_messages_bonus'] ?? $payload['assistant_messages_bonus'] ?? 0);
            $payload['whatsapp_messages_bonus'] = (int) ($attributes['whatsapp_messages_bonus'] ?? $payload['whatsapp_messages_bonus'] ?? 0);
            $payload['usage_period'] = $attributes['usage_period'] ?? now()->format('Y-m');
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
     * Cancel the authenticated user's current subscription plan.
     */
    public static function cancelUserSubscription(int $userId): UserLimit
    {
        $user = User::findOrFail($userId);

        if ($user->role === UserRole::Owner) {
            throw new \InvalidArgumentException('لا يمكن إنهاء خطة المدير العام');
        }

        $userLimit = UserLimit::where('user_id', $userId)->first();

        if (!$userLimit) {
            throw new \RuntimeException('لا يوجد اشتراك نشط لإنهائه');
        }

        if ($userLimit->status === 'canceled') {
            return $userLimit;
        }

        $today = Carbon::today();

        $userLimit->update([
            'status' => 'canceled',
            'end_date' => $today,
        ]);

        if ($userLimit->subscription_assignment_id) {
            SubscriptionAssignment::where('id', $userLimit->subscription_assignment_id)->update([
                'status' => 'canceled',
                'end_date' => $today,
            ]);
        }

        SubscriptionAssignment::where('user_id', $userId)
            ->where('status', 'active')
            ->update([
                'status' => 'canceled',
                'end_date' => $today,
            ]);

        return $userLimit->fresh();
    }

    /**
     * Apply a subscription plan to a user and sync user limits accordingly.
     */
    public static function applySubscriptionToUser(int $userId, Subscription $subscription, array $overrides = []): UserLimit
    {
        $startDate = self::resolveDate($overrides['start_date'] ?? now());
        $endDate = self::calculateEndDate(
            $subscription->duration,
            $startDate,
            $overrides['end_date'] ?? null,
            (float) $subscription->price
        );

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
            'assistant_messages' => $subscription->assistant_messages ?? self::DEFAULT_LIMITS['assistant_messages'],
            'whatsapp_messages' => $subscription->whatsapp_messages ?? self::DEFAULT_LIMITS['whatsapp_messages'],
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
     * Re-apply plan resource caps/features to every user currently on this subscription.
     * Preserves usage counters, bonuses, and subscription dates.
     */
    public static function syncSubscriptionLimitsToSubscribers(Subscription $subscription): int
    {
        $updated = 0;

        UserLimit::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(100, function ($limits) use ($subscription, &$updated) {
                foreach ($limits as $limit) {
                    $limit->update([
                        'subscription_name' => $subscription->name,
                        'subscription_slug' => $subscription->slug,
                        'currency' => $subscription->currency,
                        'price' => $subscription->price,
                        'duration' => $subscription->duration,
                        'description' => $subscription->description,
                        'customers' => $subscription->customers,
                        'installments' => $subscription->installments,
                        'notifications' => $subscription->notifications,
                        'assistant_messages' => $subscription->assistant_messages ?? self::DEFAULT_LIMITS['assistant_messages'],
                        'whatsapp_messages' => $subscription->whatsapp_messages ?? self::DEFAULT_LIMITS['whatsapp_messages'],
                        'reports' => $subscription->reports,
                        'features' => self::normalizeFeatures($subscription->features),
                    ]);
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Assign one plan to many merchants.
     *
     * @param  array<int, int>  $userIds
     * @return array{assigned: int, limits: list<UserLimit>}
     */
    public static function bulkAssignSubscription(Subscription $subscription, array $userIds, array $overrides = []): array
    {
        $limits = [];
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId < 1) {
                continue;
            }
            $limits[] = self::applySubscriptionToUser($userId, $subscription, $overrides);
        }

        return [
            'assigned' => count($limits),
            'limits' => $limits,
        ];
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
                'assistant_messages' => ['from' => 0, 'to' => null],
                'whatsapp_messages' => ['from' => 0, 'to' => null],
                'reports' => true,
                'features' => [
                    'all' => true,
                    'assistant' => true,
                    'whatsapp' => true,
                    'internal_credit_score' => true,
                    'basic_reports' => true,
                ],
            ]);
        }

        $limit = UserLimit::forUser($userId)->active()->first();
        if ($limit) {
            $limit->refreshMonthlyPeriodIfNeeded();
        }

        return $limit;
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
            'assistant_messages' => 'وصلت للحد الشهري لرسائل المساعد الذكي في خطتك.',
            'whatsapp_messages' => 'وصلت للحد الشهري لرسائل الواتس في خطتك.',
            'reports' => 'ميزة التقارير غير متاحة في خطتك الحالية.',
            'assistant' => 'المساعد الذكي غير متاح في خطتك الحالية.',
            'whatsapp' => 'رسائل الواتس غير متاحة في خطتك الحالية.',
        ];

        return $messages[$resourceType] ?? 'لقد وصلت إلى الحد المسموح لهذا المورد.';
    }

    /**
     * Grant a gift bonus for a monthly messaging resource.
     */
    public static function grantGift(
        int $userId,
        string $resource,
        int $amount,
        ?int $grantedBy = null,
        ?string $note = null,
        string $source = 'gift'
    ): MerchantGift {
        if (! in_array($resource, MerchantGift::RESOURCES, true)) {
            throw new \InvalidArgumentException('نوع الهدية غير مدعوم.');
        }

        if ($amount < 1) {
            throw new \InvalidArgumentException('كمية الهدية لازم تكون أكبر من صفر.');
        }

        $userLimit = UserLimit::query()->where('user_id', $userId)->first()
            ?? self::createOrUpdateUserLimits($userId);

        $userLimit->addBonus($resource, $amount);

        return MerchantGift::query()->create([
            'user_id' => $userId,
            'granted_by' => $grantedBy,
            'resource' => $resource,
            'amount' => $amount,
            'source' => $source,
            'note' => $note,
        ]);
    }

    /**
     * Reset monthly messaging usage for a merchant (keeps gifts by default).
     */
    public static function resetMonthlyUsage(int $userId, bool $clearBonus = false): UserLimit
    {
        $userLimit = UserLimit::query()->where('user_id', $userId)->first()
            ?? self::createOrUpdateUserLimits($userId);

        $userLimit->resetMonthlyUsage($clearBonus);

        return $userLimit->fresh();
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
                'is_trial' => TrialHelper::isTrialFeatures($userLimit->features),
            ],
            'limits' => [
                'customers' => $userLimit->customers,
                'installments' => $userLimit->installments,
                'notifications' => $userLimit->notifications,
                'assistant_messages' => $userLimit->assistant_messages,
                'whatsapp_messages' => $userLimit->whatsapp_messages,
                'features' => $userLimit->features,
                'reports' => $userLimit->reports,
            ],
            'usage' => [
                'customers_used' => $userLimit->customers_used,
                'installments_used' => $userLimit->installments_used,
                'notifications_used' => $userLimit->notifications_used,
                'assistant_messages_used' => $userLimit->assistant_messages_used,
                'whatsapp_messages_used' => $userLimit->whatsapp_messages_used,
                'assistant_messages_bonus' => $userLimit->assistant_messages_bonus,
                'whatsapp_messages_bonus' => $userLimit->whatsapp_messages_bonus,
                'usage_period' => $userLimit->usage_period,
            ],
            'remaining' => [
                'customers' => $userLimit->getRemainingCount('customers'),
                'installments' => $userLimit->getRemainingCount('installments'),
                'notifications' => $userLimit->getRemainingCount('notifications'),
                'assistant_messages' => $userLimit->getRemainingCount('assistant_messages'),
                'whatsapp_messages' => $userLimit->getRemainingCount('whatsapp_messages'),
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
            'assistant_messages',
            'whatsapp_messages',
            'assistant_messages_used',
            'whatsapp_messages_used',
            'assistant_messages_bonus',
            'whatsapp_messages_bonus',
            'usage_period',
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
     * @return array<string, mixed>
     */
    protected static function normalizeFeatures(mixed $features): array
    {
        $defaults = self::DEFAULT_LIMITS['features'];
        if (! is_array($features)) {
            return $defaults;
        }

        return array_merge($defaults, $features);
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
    protected static function calculateEndDate(
        string $duration,
        Carbon $startDate,
        mixed $override = null,
        ?float $price = null
    ): ?Carbon {
        if ($override !== null) {
            return self::resolveDate($override);
        }

        if ($price !== null && $price <= 0) {
            return null;
        }

        return match ($duration) {
            'monthly' => (clone $startDate)->addMonth(),
            'yearly' => (clone $startDate)->addYear(),
            default => null,
        };
    }
}
