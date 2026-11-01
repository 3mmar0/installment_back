<?php

namespace App\Helpers;

class LocaleHelper
{
    /** @var array<string, string> */
    public const CURRENCIES_BY_COUNTRY = [
        'EG' => 'EGP',
        'SA' => 'SAR',
        'AE' => 'AED',
        'KW' => 'KWD',
        'QA' => 'QAR',
        'BH' => 'BHD',
        'OM' => 'OMR',
        'JO' => 'JOD',
        'LB' => 'LBP',
        'SY' => 'SYP',
        'IQ' => 'IQD',
        'PS' => 'ILS',
        'LY' => 'LYD',
        'TN' => 'TND',
        'DZ' => 'DZD',
        'MA' => 'MAD',
        'MR' => 'MRU',
        'SD' => 'SDG',
        'YE' => 'YER',
        'SO' => 'SOS',
        'DJ' => 'DJF',
        'KM' => 'KMF',
        'TR' => 'TRY',
        'US' => 'USD',
        'GB' => 'GBP',
    ];

    public static function currencyForCountry(?string $country): string
    {
        $code = strtoupper(trim((string) $country));

        return self::CURRENCIES_BY_COUNTRY[$code] ?? 'EGP';
    }

    /** @return list<string> */
    public static function countryCodes(): array
    {
        return array_keys(self::CURRENCIES_BY_COUNTRY);
    }
}
