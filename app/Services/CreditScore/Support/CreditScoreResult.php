<?php

namespace App\Services\CreditScore\Support;

/**
 * The full, explainable output of one scoring run. Everything needed to persist
 * a reproducible snapshot lives here.
 */
final class CreditScoreResult
{
    /**
     * @param  array<string, float|null>  $components   normalized component scores (0..100 or null)
     * @param  array<string, float>  $weightsUsed        weights after redistribution
     * @param  array<string, mixed>  $metrics
     * @param  string[]  $positiveFactors
     * @param  string[]  $negativeFactors
     * @param  array<int, array<string, mixed>>  $changeReasons
     */
    public function __construct(
        public readonly int $score,
        public readonly float $rawScore,
        public readonly float $observedRaw,
        public readonly float $baselineRaw,
        public readonly float $depth,
        public readonly string $riskLevel,
        public readonly string $riskLabel,
        public readonly string $confidenceLevel,
        public readonly bool $thinFile,
        public readonly array $components,
        public readonly array $weightsUsed,
        public readonly array $metrics,
        public readonly float $currentOutstanding,
        public readonly float $currentOverdueAmount,
        public readonly int $currentOverdueCount,
        public readonly int $maxDpd,
        public readonly int $currentMaxDpd,
        public readonly int $activeContracts,
        public readonly int $completedContracts,
        public readonly int $historyMonths,
        public array $positiveFactors = [],
        public array $negativeFactors = [],
        public array $changeReasons = [],
        public int $scoreChange = 0,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'raw_score' => round($this->rawScore, 3),
            'observed_raw' => round($this->observedRaw, 3),
            'baseline_raw' => round($this->baselineRaw, 3),
            'depth' => round($this->depth, 4),
            'risk_level' => $this->riskLevel,
            'risk_label' => $this->riskLabel,
            'confidence_level' => $this->confidenceLevel,
            'thin_file' => $this->thinFile,
            'score_change' => $this->scoreChange,
            'components' => $this->components,
            'weights_used' => $this->weightsUsed,
            'current_outstanding' => $this->currentOutstanding,
            'current_overdue_amount' => $this->currentOverdueAmount,
            'current_overdue_count' => $this->currentOverdueCount,
            'max_dpd' => $this->maxDpd,
            'current_max_dpd' => $this->currentMaxDpd,
            'active_contracts' => $this->activeContracts,
            'completed_contracts' => $this->completedContracts,
            'history_months' => $this->historyMonths,
            'metrics' => $this->metrics,
            'positive_factors' => $this->positiveFactors,
            'negative_factors' => $this->negativeFactors,
            'change_reasons' => $this->changeReasons,
        ];
    }
}
