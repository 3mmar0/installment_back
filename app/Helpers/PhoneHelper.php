<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Normalize a phone number for matching.
     * Strips non-digits, drops leading country/zero codes, keeps the last 9 digits.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Keep last 9 digits (Egyptian local mobile length after country/trunk prefix).
        if (strlen($digits) > 9) {
            $digits = substr($digits, -9);
        }

        return $digits !== '' ? $digits : null;
    }
}
