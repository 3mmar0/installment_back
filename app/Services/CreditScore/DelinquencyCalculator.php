<?php

namespace App\Services\CreditScore;

use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\ScoredItem;
use Carbon\CarbonImmutable;

/**
 * Central Days-Past-Due (DPD) calculator.
 *
 * DPD rules (locked to the app's existing overdue definition):
 *  - Paid on/before the due date  -> DPD 0 (on time / early).
 *  - Paid after the due date       -> calendar days from due date to the full
 *                                     settlement date (paid_on of an approved
 *                                     payment request, else paid_at).
 *  - Unpaid and due date is in the past (strictly before start-of-today)
 *                                  -> calendar days from due date to today.
 *  - Unpaid and due today/future   -> DPD 0 (due today is NOT overdue, exactly
 *                                     matching InstallmentDateHelper).
 *
 * There is no partial-payment model in this system, so an item is either fully
 * settled or fully outstanding; we never invent a synthetic on-time payment.
 */
class DelinquencyCalculator
{
    public function __construct(private readonly CreditScoreConfig $config)
    {
    }

    /**
     * Annotate a raw item with its delinquency facts.
     *
     * @param  array{due_date: CarbonImmutable, amount: float, is_paid: bool, closure_date: ?CarbonImmutable}  $item
     */
    public function analyze(array $item, CarbonImmutable $today): ScoredItem
    {
        $today = $today->startOfDay();
        $due = $item['due_date']->startOfDay();
        $isPaid = $item['is_paid'];
        $closure = $item['closure_date']?->startOfDay();

        $hasComeDue = $isPaid || $due->lessThan($today) || $due->equalTo($today);

        $dpd = 0;
        $isOverdueNow = false;
        $isEarly = false;
        $isOnTime = false;

        if ($isPaid && $closure !== null) {
            if ($closure->greaterThan($due)) {
                $dpd = (int) $due->diffInDays($closure);
            } elseif ($closure->lessThan($due)) {
                $isEarly = true;
            } else {
                $isOnTime = true;
            }
            // Paid exactly on due date counts as on time (dpd 0).
            if (! $isEarly && $dpd === 0) {
                $isOnTime = true;
            }
            // Recency anchors on when the delinquency resolved (settlement).
            $recencyAnchor = $closure;
        } elseif (! $isPaid && $due->lessThan($today)) {
            $dpd = (int) $due->diffInDays($today);
            $isOverdueNow = true;
            // An open delinquency is happening now, so it is maximally recent.
            $recencyAnchor = $today;
        } else {
            // Unpaid but due today or in the future: nothing has gone wrong yet.
            $recencyAnchor = $today;
            if ($isPaid) {
                $isOnTime = true;
            }
        }

        $bucket = $this->bucketForDpd($dpd);
        $monthsAgo = max(0.0, $recencyAnchor->diffInDays($today) / 30.4375);

        return new ScoredItem(
            dueDate: $due,
            amount: $item['amount'],
            isPaid: $isPaid,
            closureDate: $closure,
            dpd: $dpd,
            bucket: $bucket,
            isEarly: $isEarly,
            isOnTime: $isOnTime,
            isOverdueNow: $isOverdueNow,
            monthsAgo: $monthsAgo,
            hasComeDue: $hasComeDue,
        );
    }

    /**
     * Map a DPD value onto its configured bucket key (null when not late).
     */
    public function bucketForDpd(int $dpd): ?string
    {
        if ($dpd <= 0) {
            return null;
        }

        foreach ($this->config->array('dpd_buckets') as $key => $range) {
            $min = $range[0] ?? null;
            $max = $range[1] ?? null;

            if ($min !== null && $dpd < $min) {
                continue;
            }
            if ($max !== null && $dpd > $max) {
                continue;
            }

            return $key;
        }

        return null;
    }
}
