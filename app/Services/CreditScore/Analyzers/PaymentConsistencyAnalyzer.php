<?php

namespace App\Services\CreditScore\Analyzers;

use App\Services\CreditScore\Support\ComponentResult;
use App\Services\CreditScore\Support\CreditItemRecord;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CustomerCreditData;
use App\Services\CreditScore\Support\Math;

/**
 * Payment Consistency (10%).
 *
 * Owns REGULARITY only: the on-time ratio and the variance of days-past-due.
 * It deliberately does NOT reuse the payment-history penalty table, so a late
 * payment is not punished twice for the same fact.
 *
 * score = 100 * (on_time_weight * on_time_ratio
 *              + variance_weight * (1 - saturate(dpd_stddev / scale)))
 */
class PaymentConsistencyAnalyzer
{
    public function __construct(private readonly CreditScoreConfig $config)
    {
    }

    public function analyze(CustomerCreditData $data): ComponentResult
    {
        $due = $data->dueRecords();
        if ($due === []) {
            return ComponentResult::unavailable(['due_installments' => 0]);
        }

        $dpdValues = array_map(fn (CreditItemRecord $r) => (float) $r->scored->dpd, $due);
        $onTime = 0;
        foreach ($due as $record) {
            if ($record->scored->dpd === 0) {
                $onTime++;
            }
        }

        $onTimeRatio = $onTime / count($due);
        $stddev = Math::stddev($dpdValues);
        $scale = $this->config->float('payment_consistency.dpd_stddev_scale', 30.0);
        $regularity = 1.0 - Math::saturate($scale > 0 ? $stddev / $scale : 0.0);

        $onTimeWeight = $this->config->float('payment_consistency.on_time_weight', 0.6);
        $varianceWeight = $this->config->float('payment_consistency.variance_weight', 0.4);

        $score = Math::clamp(
            100.0 * ($onTimeWeight * $onTimeRatio + $varianceWeight * $regularity),
            $this->config->float('payment_consistency.min', 0.0),
            $this->config->float('payment_consistency.max', 100.0)
        );

        return ComponentResult::score($score, [
            'due_installments' => count($due),
            'on_time_ratio' => round($onTimeRatio, 4),
            'dpd_stddev' => round($stddev, 3),
            'payment_variance' => round($stddev ** 2, 3),
        ]);
    }
}
