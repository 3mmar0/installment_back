<?php

namespace App\Helpers;

class NationalIdHelper
{
    public const LENGTH = 14;

    /**
     * Keep digits only so formatted ids still match.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized !== null && strlen($normalized) === self::LENGTH;
    }
}
