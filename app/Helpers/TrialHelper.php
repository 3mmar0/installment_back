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
     * Assign either the selected eligible trial or the free plan on registration.
     */
    public static function applyRegistrationPlan(User $user, ?Subscription $subscription): void
    {
        $settings = self::settings();
        if ($subscription && $subscription->is_active && (float) $subscription->price > 0 && $settings['enabled'] && ! $user->trial_used_at) {
            app(\App\Services\SubscriptionEntitlementService::class)->activate(
                $subscription,
                $user->id,
                'trial',
                $user->id,
                null,
                now()->addDays($settings['days'])->endOfDay(),
                ['is_trial' => true]
            );
            $user->update(['trial_used_at' => now()]);
            return;
        }

        $subscription = Subscription::active()->where('slug', 'free')->first();

        if (!$subscription) {
            LimitsHelper::createOrUpdateUserLimits($user->id, [
                'subscription_name' => 'الخطه المجانية',
                'subscription_slug' => 'free',
            ]);

            return;
        }

        app(\App\Services\SubscriptionEntitlementService::class)->activate($subscription, $user->id, 'free', $user->id);
    }

    public static function isTrialFeatures(?array $features): bool
    {
        return is_array($features) && !empty($features['is_trial']);
    }
}
