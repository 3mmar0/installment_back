<?php

namespace App\Helpers;

use Carbon\Carbon;

class InstallmentDateHelper
{
    /**
     * Whole calendar days from today until due date (0 if due today or past).
     */
    public static function daysUntilDue(mixed $dueDate): int
    {
        $due = Carbon::parse($dueDate)->startOfDay();
        $today = now()->startOfDay();

        if ($due->lte($today)) {
            return 0;
        }

        return (int) $today->diffInDays($due);
    }

    /**
     * Whole calendar days the due date is in the past (0 if due today or future).
     */
    public static function daysOverdue(mixed $dueDate): int
    {
        $due = Carbon::parse($dueDate)->startOfDay();
        $today = now()->startOfDay();

        if ($due->gte($today)) {
            return 0;
        }

        return (int) $due->diffInDays($today);
    }

    /**
     * Whole calendar days since a date (e.g. payment date).
     */
    public static function daysSince(mixed $date): int
    {
        $then = Carbon::parse($date)->startOfDay();
        $today = now()->startOfDay();

        if ($then->gt($today)) {
            return 0;
        }

        return (int) $then->diffInDays($today);
    }
}
