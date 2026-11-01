<?php

namespace App\Helpers;

/**
 * Egyptian governorates and a coarse distance band used for installment risk.
 */
class GovernorateHelper
{
    public const SAME = 'same';

    public const NEARBY = 'nearby';

    public const ADJACENT = 'adjacent';

    public const DISTANT = 'distant';

    public const UNKNOWN = 'unknown';

    /**
     * @return array<string, array{label_ar: string, label_en: string, region: string}>
     */
    public static function all(): array
    {
        return [
            'cairo' => ['label_ar' => 'القاهرة', 'label_en' => 'Cairo', 'region' => 'greater_cairo'],
            'giza' => ['label_ar' => 'الجيزة', 'label_en' => 'Giza', 'region' => 'greater_cairo'],
            'qalyubia' => ['label_ar' => 'القليوبية', 'label_en' => 'Qalyubia', 'region' => 'greater_cairo'],
            'alexandria' => ['label_ar' => 'الإسكندرية', 'label_en' => 'Alexandria', 'region' => 'alex'],
            'beheira' => ['label_ar' => 'البحيرة', 'label_en' => 'Beheira', 'region' => 'delta'],
            'kafr_el_sheikh' => ['label_ar' => 'كفر الشيخ', 'label_en' => 'Kafr El Sheikh', 'region' => 'delta'],
            'dakahlia' => ['label_ar' => 'الدقهلية', 'label_en' => 'Dakahlia', 'region' => 'delta'],
            'sharqia' => ['label_ar' => 'الشرقية', 'label_en' => 'Sharqia', 'region' => 'delta'],
            'gharbia' => ['label_ar' => 'الغربية', 'label_en' => 'Gharbia', 'region' => 'delta'],
            'monufia' => ['label_ar' => 'المنوفية', 'label_en' => 'Monufia', 'region' => 'delta'],
            'damietta' => ['label_ar' => 'دمياط', 'label_en' => 'Damietta', 'region' => 'delta'],
            'port_said' => ['label_ar' => 'بورسعيد', 'label_en' => 'Port Said', 'region' => 'canal'],
            'ismailia' => ['label_ar' => 'الإسماعيلية', 'label_en' => 'Ismailia', 'region' => 'canal'],
            'suez' => ['label_ar' => 'السويس', 'label_en' => 'Suez', 'region' => 'canal'],
            'north_sinai' => ['label_ar' => 'شمال سيناء', 'label_en' => 'North Sinai', 'region' => 'sinai'],
            'south_sinai' => ['label_ar' => 'جنوب سيناء', 'label_en' => 'South Sinai', 'region' => 'sinai'],
            'beni_suef' => ['label_ar' => 'بني سويف', 'label_en' => 'Beni Suef', 'region' => 'north_upper'],
            'fayoum' => ['label_ar' => 'الفيوم', 'label_en' => 'Fayoum', 'region' => 'north_upper'],
            'minya' => ['label_ar' => 'المنيا', 'label_en' => 'Minya', 'region' => 'north_upper'],
            'asyut' => ['label_ar' => 'أسيوط', 'label_en' => 'Asyut', 'region' => 'mid_upper'],
            'sohag' => ['label_ar' => 'سوهاج', 'label_en' => 'Sohag', 'region' => 'mid_upper'],
            'qena' => ['label_ar' => 'قنا', 'label_en' => 'Qena', 'region' => 'south_upper'],
            'luxor' => ['label_ar' => 'الأقصر', 'label_en' => 'Luxor', 'region' => 'south_upper'],
            'aswan' => ['label_ar' => 'أسوان', 'label_en' => 'Aswan', 'region' => 'south_upper'],
            'red_sea' => ['label_ar' => 'البحر الأحمر', 'label_en' => 'Red Sea', 'region' => 'red_sea'],
            'new_valley' => ['label_ar' => 'الوادي الجديد', 'label_en' => 'New Valley', 'region' => 'frontier'],
            'matrouh' => ['label_ar' => 'مطروح', 'label_en' => 'Matrouh', 'region' => 'frontier'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function normalize(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = strtolower(trim($code));

        return $code !== '' && isset(self::all()[$code]) ? $code : null;
    }

    public static function region(?string $code): ?string
    {
        $code = self::normalize($code);

        return $code ? self::all()[$code]['region'] : null;
    }

    public static function label(?string $code, string $locale = 'ar'): ?string
    {
        $code = self::normalize($code);
        if ($code === null) {
            return null;
        }

        return $locale === 'en'
            ? self::all()[$code]['label_en']
            : self::all()[$code]['label_ar'];
    }

    /**
     * @return list<string>
     */
    public static function rule(): array
    {
        return ['nullable', 'string', 'in:'.implode(',', self::codes())];
    }

    public static function distanceBand(?string $from, ?string $to): string
    {
        $from = self::normalize($from);
        $to = self::normalize($to);

        if ($from === null || $to === null) {
            return self::UNKNOWN;
        }

        if ($from === $to) {
            return self::SAME;
        }

        $fromRegion = self::region($from);
        $toRegion = self::region($to);

        if ($fromRegion !== null && $fromRegion === $toRegion) {
            return self::NEARBY;
        }

        $neighbors = self::regionNeighbors();
        if ($fromRegion !== null && in_array($toRegion, $neighbors[$fromRegion] ?? [], true)) {
            return self::ADJACENT;
        }

        return self::DISTANT;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function regionNeighbors(): array
    {
        return [
            'greater_cairo' => ['delta', 'alex', 'canal', 'north_upper'],
            'delta' => ['greater_cairo', 'alex', 'canal'],
            'alex' => ['greater_cairo', 'delta', 'frontier'],
            'canal' => ['greater_cairo', 'delta', 'sinai', 'red_sea'],
            'sinai' => ['canal', 'red_sea'],
            'north_upper' => ['greater_cairo', 'mid_upper', 'red_sea'],
            'mid_upper' => ['north_upper', 'south_upper', 'red_sea', 'frontier'],
            'south_upper' => ['mid_upper', 'red_sea'],
            'red_sea' => ['canal', 'sinai', 'north_upper', 'mid_upper', 'south_upper'],
            'frontier' => ['mid_upper', 'alex'],
        ];
    }
}
