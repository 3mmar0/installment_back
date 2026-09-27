<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Engagement
{
    /** Accounts unused this long stop receiving operational email and push. */
    public const INACTIVE_AFTER_MONTHS = 5;

    public static function cutoff(): CarbonInterface
    {
        return now()->subMonths(self::INACTIVE_AFTER_MONTHS);
    }

    public static function isActive(?CarbonInterface $lastActiveAt, ?CarbonInterface $createdAt): bool
    {
        $anchor = $lastActiveAt ?? $createdAt;

        return $anchor !== null && $anchor->gte(self::cutoff());
    }
}
