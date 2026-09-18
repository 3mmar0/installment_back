<?php

namespace App\Support;

use Carbon\CarbonInterface;

class OverdueDigestSchedule
{
    public static function weekday(): int
    {
        return max(1, min(7, (int) config('notifications.overdue_digest_weekday', 5)));
    }

    public static function isDue(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $now->isoWeekday() === self::weekday();
    }
}
