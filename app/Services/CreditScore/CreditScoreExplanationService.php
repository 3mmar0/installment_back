<?php

namespace App\Services\CreditScore;

use App\Models\CustomerCreditScore;
use App\Services\CreditScore\Support\CreditScoreResult;
use App\Services\CreditScore\Support\CustomerCreditData;

/**
 * Builds human-readable positive/negative factors and score-change reasons from
 * real metrics. Never invents reasons that the data does not support.
 */
class CreditScoreExplanationService
{
    /**
     * @return string[]
     */
    public function positiveFactors(CreditScoreResult $result, CustomerCreditData $data): array
    {
        $factors = [];
        $payment = $result->metrics['payment_history'] ?? [];

        $onTimeRatio = $payment['on_time_ratio'] ?? null;
        if ($onTimeRatio !== null && $onTimeRatio >= 0.9) {
            $pct = (int) round($onTimeRatio * 100);
            $factors[] = "{$pct}% of installments paid on time.";
        }

        if (($payment['current_overdue_count'] ?? 0) === 0 && ($data->dueCount() ?? 0) > 0) {
            $factors[] = 'No current overdue installments.';
        }

        $consecutive = (int) ($payment['consecutive_on_time'] ?? 0);
        if ($consecutive >= 3) {
            $factors[] = "{$consecutive} consecutive on-time payments.";
        }

        if ($result->completedContracts >= 1) {
            $factors[] = $result->completedContracts === 1
                ? '1 successfully completed contract.'
                : "{$result->completedContracts} successfully completed contracts.";
        }

        if ($result->historyMonths >= 24 && $onTimeRatio !== null && $onTimeRatio >= 0.85) {
            $factors[] = 'Long positive payment history.';
        }

        if (($payment['late_90_plus'] ?? 0) === 0 && ($payment['paid_late'] ?? 0) > 0) {
            $factors[] = 'No serious (90+ day) delinquencies on record.';
        }

        return array_values(array_unique($factors));
    }

    /**
     * @return string[]
     */
    public function negativeFactors(CreditScoreResult $result, CustomerCreditData $data): array
    {
        $factors = [];
        $payment = $result->metrics['payment_history'] ?? [];
        $activity = $result->metrics['credit_activity'] ?? [];

        $lateRecent = (int) ($payment['late_8_30'] ?? 0) + (int) ($payment['late_1_7'] ?? 0);
        if ($lateRecent >= 1 && ($payment['late_ratio'] ?? 0) > 0) {
            $factors[] = 'Recent late payments on record.';
        }

        if (($payment['current_overdue_count'] ?? 0) > 0) {
            $factors[] = 'Current overdue balance exists.';
        }

        if ($result->activeContracts >= 4) {
            $factors[] = 'High number of active installment plans.';
        }

        if (($activity['new_contracts_90d'] ?? 0) >= 3) {
            $factors[] = 'Recent increase in financing activity.';
        }

        if ($result->thinFile) {
            $factors[] = 'Limited credit history (thin file).';
        }

        if (($payment['late_90_plus'] ?? 0) >= 1) {
            $factors[] = 'Serious delinquency (90+ days past due) on record.';
        }

        $other = $result->metrics['other'] ?? [];
        if (($other['duplicate_identity'] ?? false) === true) {
            $factors[] = 'Possible duplicate identity indicators within merchant records.';
        }

        return array_values(array_unique($factors));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function changeReasons(
        CreditScoreResult $current,
        ?CustomerCreditScore $previous
    ): array {
        if ($previous === null) {
            return [];
        }

        $reasons = [];
        $delta = $current->score - (int) $previous->score;
        if ($delta === 0) {
            return [];
        }

        $prevMetrics = is_array($previous->metrics) ? $previous->metrics : [];
        $prevPayment = $prevMetrics['payment_history'] ?? [];
        $currPayment = $current->metrics['payment_history'] ?? [];

        $prevOnTime = (float) ($prevPayment['on_time_ratio'] ?? 0);
        $currOnTime = (float) ($currPayment['on_time_ratio'] ?? 0);
        if ($currOnTime > $prevOnTime + 0.01) {
            $reasons[] = ['type' => 'positive', 'message' => 'More installments paid on time.'];
        }

        $prevOverdue = (float) ($previous->current_overdue_amount ?? 0);
        if ($prevOverdue > 0 && $current->currentOverdueAmount <= 0) {
            $reasons[] = ['type' => 'positive', 'message' => 'Overdue balance cleared.'];
        }

        if ($current->currentOverdueAmount > $prevOverdue + 0.01) {
            $reasons[] = ['type' => 'negative', 'message' => 'Outstanding overdue amount increased.'];
        }

        if ($current->currentMaxDpd > (int) $previous->current_max_dpd) {
            $reasons[] = [
                'type' => 'negative',
                'message' => "Current days past due increased to {$current->currentMaxDpd}.",
            ];
        }

        if ($current->activeContracts > (int) $previous->active_contracts) {
            $reasons[] = ['type' => 'negative', 'message' => 'New financing plan added.'];
        }

        if ($prevPayment !== [] && ($prevPayment['late_90_plus'] ?? 0) > ($currPayment['late_90_plus'] ?? 0)) {
            $reasons[] = ['type' => 'positive', 'message' => 'Older delinquency records aged out of recent weighting.'];
        }

        return $reasons;
    }
}
