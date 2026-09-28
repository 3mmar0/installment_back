<?php

namespace App\Services\CreditScore\Analyzers;

use App\Services\CreditScore\Support\ComponentResult;
use App\Services\CreditScore\Support\CreditItemRecord;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CustomerCreditData;
use App\Services\CreditScore\Support\Math;

/**
 * Payment History (35%).
 *
 * Owns delinquency SEVERITY and RECENCY. Every installment that has come due
 * contributes a recency-weighted penalty; the score is 100 minus the averaged
 * penalty, plus a small capped early-settlement bonus. Regularity/streaks are
 * left to the Payment Consistency component to avoid double counting.
 */
class PaymentHistoryAnalyzer
{
    public function __construct(private readonly CreditScoreConfig $config)
    {
    }

    public function analyze(CustomerCreditData $data): ComponentResult
    {
        $due = $data->dueRecords();
        $metrics = $this->baseMetrics($data);

        // No installment has ever come due => genuinely no payment history.
        if ($due === []) {
            return ComponentResult::unavailable($metrics);
        }

        $lambda = $this->config->float('payment_history.recency_lambda', 0.05);
        $penalties = $this->config->array('payment_history.penalties');

        $weightedPenaltySum = 0.0;
        foreach ($due as $record) {
            $bucket = $record->scored->bucket;
            if ($bucket === null) {
                continue;
            }
            $base = (float) ($penalties[$bucket] ?? 0.0);
            $weight = exp(-$lambda * $record->scored->monthsAgo);
            $weightedPenaltySum += $base * $weight;
        }

        $avgWeightedPenalty = $weightedPenaltySum / max(1, count($due));
        $penalty = $this->config->float('payment_history.penalty_multiplier', 24.0) * $avgWeightedPenalty;

        $earlyCount = (int) $metrics['paid_early'];
        $earlyBonus = min(
            $this->config->float('payment_history.early_payment_bonus_cap', 5.0),
            $earlyCount * $this->config->float('payment_history.early_payment_bonus_per_item', 0.5)
        );

        $start = $this->config->float('payment_history.start', 100.0);
        $score = Math::clamp(
            $start - $penalty + $earlyBonus,
            $this->config->float('payment_history.min', 0.0),
            $this->config->float('payment_history.max', 100.0)
        );

        return ComponentResult::score($score, $metrics);
    }

    /**
     * Full payment-history metric set (used for reporting and factor mining).
     *
     * @return array<string, mixed>
     */
    private function baseMetrics(CustomerCreditData $data): array
    {
        $all = $data->records;
        $due = $data->dueRecords();

        $totalInstallments = count($all);
        $totalPaid = $this->count($all, fn (CreditItemRecord $r) => $r->isPaid);
        $totalOverdue = $this->count($all, fn (CreditItemRecord $r) => $r->scored->isOverdueNow);

        $bucketKeys = ['late_1_7', 'late_8_30', 'late_31_60', 'late_61_90', 'late_90_plus'];
        $buckets = array_fill_keys($bucketKeys, 0);

        $dpdLateValues = [];
        $onTime = 0;
        $early = 0;
        $late = 0;
        $maxDpd = 0;
        $currentMaxDpd = 0;
        $historicalOverdueAmount = 0.0;
        $currentOverdueAmount = 0.0;
        $lastLateDate = null;
        $lastSuccessfulDate = null;

        foreach ($due as $record) {
            $s = $record->scored;

            if ($s->dpd > 0) {
                $late++;
                $dpdLateValues[] = $s->dpd;
                $historicalOverdueAmount += $record->amount;
                if ($s->bucket !== null) {
                    $buckets[$s->bucket]++;
                }
                $maxDpd = max($maxDpd, $s->dpd);
                if ($s->isOverdueNow) {
                    $currentMaxDpd = max($currentMaxDpd, $s->dpd);
                    $currentOverdueAmount += $record->amount;
                }
                if ($s->closureDate !== null) {
                    $lastLateDate = $this->latest($lastLateDate, $s->closureDate->toDateString());
                }
            } else {
                $onTime++;
                if ($s->isEarly) {
                    $early++;
                }
            }

            if ($record->isPaid && $s->closureDate !== null) {
                $lastSuccessfulDate = $this->latest($lastSuccessfulDate, $s->closureDate->toDateString());
            }
        }

        $dueCount = count($due);
        $onTimeRatio = $dueCount > 0 ? $onTime / $dueCount : null;
        $lateRatio = $dueCount > 0 ? $late / $dueCount : null;

        [$consecutiveOnTime, $consecutiveLate] = $this->trailingStreaks($due);

        return [
            'total_installments' => $totalInstallments,
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalInstallments - $totalPaid,
            'total_overdue' => $totalOverdue,
            'due_installments' => $dueCount,
            'paid_on_time' => $onTime - $early,
            'paid_early' => $early,
            'paid_late' => $this->count($due, fn (CreditItemRecord $r) => $r->isPaid && $r->scored->dpd > 0),
            'late_1_7' => $buckets['late_1_7'],
            'late_8_30' => $buckets['late_8_30'],
            'late_31_60' => $buckets['late_31_60'],
            'late_61_90' => $buckets['late_61_90'],
            'late_90_plus' => $buckets['late_90_plus'],
            'average_days_late' => $dpdLateValues !== [] ? round(array_sum($dpdLateValues) / count($dpdLateValues), 2) : 0.0,
            'max_dpd' => $maxDpd,
            'current_dpd' => $currentMaxDpd,
            'historical_overdue_amount' => round($historicalOverdueAmount, 2),
            'current_overdue_amount' => round($currentOverdueAmount, 2),
            'current_overdue_count' => $this->count($due, fn (CreditItemRecord $r) => $r->scored->isOverdueNow),
            'on_time_ratio' => $onTimeRatio !== null ? round($onTimeRatio, 4) : null,
            'late_ratio' => $lateRatio !== null ? round($lateRatio, 4) : null,
            'consecutive_on_time' => $consecutiveOnTime,
            'consecutive_late' => $consecutiveLate,
            'last_late_payment_date' => $lastLateDate,
            'last_successful_payment_date' => $lastSuccessfulDate,
        ];
    }

    /**
     * Trailing (most-recent-first) consecutive streaks of on-time / late items.
     *
     * @param  CreditItemRecord[]  $due
     * @return array{0: int, 1: int}
     */
    private function trailingStreaks(array $due): array
    {
        usort($due, fn (CreditItemRecord $a, CreditItemRecord $b) => $a->scored->dueDate <=> $b->scored->dueDate);
        $ordered = array_reverse($due);

        $onTime = 0;
        foreach ($ordered as $record) {
            if ($record->scored->dpd === 0) {
                $onTime++;
            } else {
                break;
            }
        }

        $late = 0;
        foreach ($ordered as $record) {
            if ($record->scored->dpd > 0) {
                $late++;
            } else {
                break;
            }
        }

        return [$onTime, $late];
    }

    /**
     * @param  CreditItemRecord[]  $records
     */
    private function count(array $records, callable $predicate): int
    {
        $count = 0;
        foreach ($records as $record) {
            if ($predicate($record)) {
                $count++;
            }
        }

        return $count;
    }

    private function latest(?string $current, string $candidate): string
    {
        if ($current === null) {
            return $candidate;
        }

        return $candidate > $current ? $candidate : $current;
    }
}
