<?php

namespace App\Services\CreditScore\Analyzers;

use App\Services\CreditScore\Support\ComponentResult;
use App\Services\CreditScore\Support\CreditContract;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CustomerCreditData;
use App\Services\CreditScore\Support\Math;

/**
 * Recent Credit Activity (10%).
 *
 * Stable activity scores high. A burst of new contracts in a short window is a
 * risk indicator (taking on several obligations quickly). Inactivity earns no
 * bonus; the component simply stays at its start value.
 *
 * The penalty uses the strongest single window (max, not sum) so one contract
 * is never counted against the customer three times.
 */
class CreditActivityAnalyzer
{
    public function __construct(private readonly CreditScoreConfig $config)
    {
    }

    public function analyze(CustomerCreditData $data): ComponentResult
    {
        if (! $data->hasAnyContract()) {
            return ComponentResult::unavailable(['has_activity' => false]);
        }

        $windows = $this->config->get('credit_activity.windows_days', [30, 90, 180]);
        $thresholds = $this->config->array('credit_activity.thresholds');
        $perExcess = $this->config->float('credit_activity.penalty_per_excess_contract', 8.0);

        $counts = [];
        $amounts = [];
        $maxPenalty = 0.0;

        foreach ($windows as $days) {
            $cutoff = $data->today->subDays((int) $days);
            $count = 0;
            $amountCents = 0;

            foreach ($data->contracts as $contract) {
                if ($contract->createdAt !== null && $contract->createdAt->greaterThanOrEqualTo($cutoff)) {
                    $count++;
                    $amountCents += (int) round($contract->totalAmount * 100);
                }
            }

            $counts[(int) $days] = $count;
            $amounts[(int) $days] = round($amountCents / 100, 2);

            $threshold = (int) ($thresholds[(int) $days] ?? PHP_INT_MAX);
            $excess = max(0, $count - $threshold);
            $maxPenalty = max($maxPenalty, $excess * $perExcess);
        }

        $penalty = min($this->config->float('credit_activity.penalty_cap', 60.0), $maxPenalty);

        $score = Math::clamp(
            $this->config->float('credit_activity.start', 100.0) - $penalty,
            $this->config->float('credit_activity.min', 0.0),
            $this->config->float('credit_activity.max', 100.0)
        );

        return ComponentResult::score($score, [
            'has_activity' => true,
            'new_contracts_30d' => $counts[30] ?? 0,
            'new_contracts_90d' => $counts[90] ?? 0,
            'new_contracts_180d' => $counts[180] ?? 0,
            'new_financing_amount_30d' => $amounts[30] ?? 0.0,
            'new_financing_amount_90d' => $amounts[90] ?? 0.0,
            'new_financing_amount_180d' => $amounts[180] ?? 0.0,
        ]);
    }
}
