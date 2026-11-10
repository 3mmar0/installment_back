<?php

namespace App\Services;

use App\Models\AppSetting;

class PlatformWhatsAppQuota
{
    public const LIMIT_KEY = 'platform_whatsapp_monthly_limit';

    public const USED_KEY = 'platform_whatsapp_used';

    public const PERIOD_KEY = 'platform_whatsapp_period';

    /**
     * @return array{limit: int, used: int, remaining: int, period: string}
     */
    public static function snapshot(): array
    {
        self::ensureCurrentPeriod();

        $limit = max(0, AppSetting::getInt(self::LIMIT_KEY, 0));
        $used = max(0, AppSetting::getInt(self::USED_KEY, 0));
        $remaining = $limit <= 0 ? PHP_INT_MAX : max(0, $limit - $used);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining === PHP_INT_MAX ? -1 : $remaining,
            'period' => (string) AppSetting::get(self::PERIOD_KEY, now()->format('Y-m')),
            'enforced' => $limit > 0,
        ];
    }

    public static function hasRemaining(): bool
    {
        $snap = self::snapshot();

        // limit 0 = platform gate not configured; allow sends.
        if ($snap['limit'] <= 0) {
            return true;
        }

        return $snap['remaining'] > 0;
    }

    public static function assertCanSend(): void
    {
        if (! self::hasRemaining()) {
            throw new \RuntimeException('رصيد واتس المنصة خلص أو غير مُعد.');
        }
    }

    public static function consume(int $amount = 1): void
    {
        if ($amount < 1) {
            return;
        }

        self::ensureCurrentPeriod();

        $used = max(0, AppSetting::getInt(self::USED_KEY, 0)) + $amount;
        AppSetting::set(self::USED_KEY, $used);
    }

    public static function setLimit(int $limit): array
    {
        AppSetting::set(self::LIMIT_KEY, max(0, $limit));
        self::ensureCurrentPeriod();

        return self::snapshot();
    }

    public static function setUsed(int $used): array
    {
        AppSetting::set(self::USED_KEY, max(0, $used));
        self::ensureCurrentPeriod();

        return self::snapshot();
    }

    private static function ensureCurrentPeriod(): void
    {
        $current = now()->format('Y-m');
        $stored = (string) AppSetting::get(self::PERIOD_KEY, '');

        if ($stored !== $current) {
            AppSetting::set(self::PERIOD_KEY, $current);
            AppSetting::set(self::USED_KEY, 0);
            if (AppSetting::get(self::LIMIT_KEY) === null) {
                AppSetting::set(self::LIMIT_KEY, 0);
            }
        }
    }
}
