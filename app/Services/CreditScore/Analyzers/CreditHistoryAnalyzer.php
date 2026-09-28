<?php

namespace App\Services\CreditScore\Analyzers;

use App\Services\CreditScore\Support\ComponentResult;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CustomerCreditData;
use App\Services\CreditScore\Support\Math;

/**
 * Customer Credit History Length (15%).
 *
 * score = base + (100 - base) * saturate(history_months / full_months).
 * A thin file still earns `base` (never a harsh zero); a long positive history
 * earns the full 100. New customers are not punished, only limited.
 */
class CreditHistoryAnalyzer
{
    public function __construct(private readonly CreditScoreConfig $config)
    {
    }

    public function analyze(CustomerCreditData $data): ComponentResult
    {
        // No contract at all => there is no credit relationship to measure.
        if (! $data->hasAnyContract()) {
            return ComponentResult::unavailable([
                'history_months' => 0,
                'has_history' => false,
            ]);
        }

        $months = $data->historyMonths();
        $base = $this->config->float('credit_history.base', 40.0);
        $fullMonths = max(1, $this->config->int('credit_history.full_months', 36));

        $score = Math::clamp(
            $base + (100.0 - $base) * Math::saturate($months / $fullMonths),
            $this->config->float('credit_history.min', 0.0),
            $this->config->float('credit_history.max', 100.0)
        );

        return ComponentResult::score($score, [
            'history_months' => $months,
            'has_history' => true,
            'total_contracts' => count($data->contracts),
        ]);
    }
}
