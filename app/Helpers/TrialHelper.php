<?php

namespace App\Helpers;

use App\Models\AppSetting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

class TrialHelper
{
    public const SETTING_ENABLED = 'trial_enabled';

    public const SETTING_DAYS = 'trial_days';

    public static function settings(): array
    {
        return [
            'enabled' => AppSetting::getBool(self::SETTING_ENABLED, true),
            'days' => max(1, AppSetting::getInt(self::SETTING_DAYS, 7)),
        ];
    }

    public static function updateSettings(bool $enabled, int $days): array
    {
        AppSetting::set(self::SETTING_ENABLED, $enabled);
        AppSetting::set(self::SETTING_DAYS, max(1, min(90, $days)));

        return self::settings();
    }

    /**
     * Apply subscription on registration: free plan is perpetual; paid plans get a trial when enabled.
     */
    public static function applyRegistrationPlan(User $user, ?Subscription $subscription): void
    {
        if (!$subscription) {
            $subscription = Subscription::active()->where('slug', 'free')->first();
        }

        if (!$subscription) {
            LimitsHelper::createOrUpdateUserLimits($user->id);

            return;
        }

        $price = (float) $subscription->price;
        $trial = self::settings();

        if ($price <= 0) {
            LimitsHelper::applySubscriptionToUser($user->id, $subscription);

            return;
        }

        if ($trial['enabled'] && $user->trial_used_at === null) {
            $endDate = Carbon::now()->addDays($trial['days']);
            $features = is_array($subscription->features) ? $subscription->features : [];
            $features['is_trial'] = true;

            LimitsHelper::applySubscriptionToUser($user->id, $subscription, [
                'end_date' => $endDate,
                'features' => $features,
            ]);
            $user->forceFill(['trial_used_at' => now()])->save();

            return;
        }

        LimitsHelper::applySubscriptionToUser($user->id, $subscription);
    }

    public static function isTrialFeatures(?array $features): bool
    {
        return is_array($features) && !empty($features['is_trial']);
    }
}
