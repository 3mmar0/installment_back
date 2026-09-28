<?php

namespace App\Services\CreditScore\Support;

final class Math
{
    public static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /** Ratio saturated to the [0, 1] range. */
    public static function saturate(float $value): float
    {
        return self::clamp($value, 0.0, 1.0);
    }

    /**
     * Population standard deviation of a list of numbers (0 for < 2 values).
     *
     * @param  array<int, float|int>  $values
     */
    public static function stddev(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        return sqrt($variance / $count);
    }
}
