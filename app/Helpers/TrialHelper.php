<?php

namespace App\Helpers;

use App\Models\AppSetting;
use App\Models\Subscription;
use App\Models\User;

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
     * Apply the free plan on registration.
     */
    public static function applyRegistrationPlan(User $user, ?Subscription $subscription): void
    {
        unset($subscription);

        $subscription = Subscription::active()->where('slug', 'free')->first();

        if (!$subscription) {
            LimitsHelper::createOrUpdateUserLimits($user->id, [
                'subscription_name' => 'الخطه المجانية',
                'subscription_slug' => 'free',
            ]);

            return;
        }

        LimitsHelper::applySubscriptionToUser($user->id, $subscription);
    }

    public static function isTrialFeatures(?array $features): bool
    {
        return is_array($features) && !empty($features['is_trial']);
    }
}
