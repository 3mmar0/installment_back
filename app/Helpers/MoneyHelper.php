<?php

namespace App\Helpers;

class MoneyHelper
{
    public const CURRENCY_LABEL = 'جنيه';

    public static function format(float|int|string|null $amount): string
    {
        $num = (float) ($amount ?? 0);

        if (fmod($num, 1.0) === 0.0) {
            return number_format($num, 0, '.', ',') . ' ' . self::CURRENCY_LABEL;
        }

        return number_format($num, 2, '.', ',') . ' ' . self::CURRENCY_LABEL;
    }
}
