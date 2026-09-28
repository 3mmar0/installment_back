<?php

namespace App\Services\CreditScore;

use App\Services\CreditScore\Analyzers\CreditActivityAnalyzer;
use App\Services\CreditScore\Analyzers\CreditHistoryAnalyzer;
use App\Services\CreditScore\Analyzers\FinancialBurdenAnalyzer;
use App\Services\CreditScore\Analyzers\OtherRiskAnalyzer;
use App\Services\CreditScore\Analyzers\PaymentConsistencyAnalyzer;
use App\Services\CreditScore\Analyzers\PaymentHistoryAnalyzer;
use App\Services\CreditScore\Support\ComponentResult;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CreditScoreResult;
use App\Services\CreditScore\Support\CustomerCreditData;
use App\Services\CreditScore\Support\Math;

/**
 * Pure scoring engine (V1).
 *
 * Runs every component, redistributes weight for unavailable components, blends
 * the observed score toward a neutral baseline according to credit-file depth,
 * projects the 0..100 raw score onto the 300..850 display scale, and derives
 * the internal risk band and data-confidence level.
 *
 * This class does NO database work; it is fully deterministic given the config
 * and the pre-loaded CustomerCreditData, which is what makes scores reproducible.
 */
class CreditScoreCalculator
{
    public function __construct(private readonly CreditScoreConfig $config)
    {
    }

    public function calculate(CustomerCreditData $data): CreditScoreResult
    {
        $delinquency = new DelinquencyCalculator($this->config);

        /** @var array<string, ComponentResult> $components */
        $components = [
            'payment_history' => (new PaymentHistoryAnalyzer($this->config))->analyze($data),
            'financial_burden' => (new FinancialBurdenAnalyzer($this->config))->analyze($data),
            'credit_history' => (new CreditHistoryAnalyzer($this->config))->analyze($data),
            'credit_activity' => (new CreditActivityAnalyzer($this->config))->analyze($data),
            'payment_consistency' => (new PaymentConsistencyAnalyzer($this->config))->analyze($data),
            'other' => (new OtherRiskAnalyzer($this->config))->analyze($data),
        ];

        $configuredWeights = $this->config->array('weights');

        // Redistribute weight across available components only.
        $availableWeightTotal = 0.0;
        foreach ($components as $key => $result) {
            if ($result->isAvailable()) {
                $availableWeightTotal += (float) ($configuredWeights[$key] ?? 0.0);
            }
        }

        $baselineRaw = $this->config->float('baseline.raw', 58.1818);
        $weightsUsed = [];
        $componentScores = [];
        $observed = $baselineRaw;

        if ($availableWeightTotal > 0) {
            $observed = 0.0;
            foreach ($components as $key => $result) {
                $componentScores[$key] = $result->isAvailable() ? round((float) $result->score, 3) : null;
                if (! $result->isAvailable()) {
                    $weightsUsed[$key] = 0.0;
                    continue;
                }
                $weight = (float) ($configuredWeights[$key] ?? 0.0) / $availableWeightTotal;
                $weightsUsed[$key] = round($weight, 6);
                $observed += (float) $result->score * $weight;
            }
        } else {
            foreach ($components as $key => $result) {
                $componentScores[$key] = null;
                $weightsUsed[$key] = 0.0;
            }
        }

        $depth = $this->depth($data);
        $rawScore = Math::clamp($baselineRaw + ($observed - $baselineRaw) * $depth, 0.0, 100.0);

        $displayed = $this->project($rawScore);
        [$riskLevel, $riskLabel] = $this->riskBand($displayed);
        $confidence = $this->confidence($data);
        $thinFile = $data->dueCount() < $this->config->int('thin_file_due_installments', 4);

        $contractStats = $this->contractStats($data);
        $paymentMetrics = $components['payment_history']->metrics;
        $burdenMetrics = $components['financial_burden']->metrics;

        $metrics = [
            'payment_history' => $paymentMetrics,
            'financial_burden' => $burdenMetrics,
            'credit_history' => $components['credit_history']->metrics,
            'credit_activity' => $components['credit_activity']->metrics,
            'payment_consistency' => $components['payment_consistency']->metrics,
            'other' => $components['other']->metrics,
            'summary' => [
                'due_installments' => $data->dueCount(),
                'depth' => round($depth, 4),
                'observed_raw' => round($observed, 3),
                'baseline_raw' => round($baselineRaw, 3),
            ],
        ];

        return new CreditScoreResult(
            score: $displayed,
            rawScore: $rawScore,
            observedRaw: $observed,
            baselineRaw: $baselineRaw,
            depth: $depth,
            riskLevel: $riskLevel,
            riskLabel: $riskLabel,
            confidenceLevel: $confidence,
            thinFile: $thinFile,
            components: $componentScores,
            weightsUsed: $weightsUsed,
            metrics: $metrics,
            currentOutstanding: (float) ($burdenMetrics['current_outstanding'] ?? 0.0),
            currentOverdueAmount: (float) ($burdenMetrics['current_overdue_amount'] ?? 0.0),
            currentOverdueCount: (int) ($paymentMetrics['current_overdue_count'] ?? 0),
            maxDpd: (int) ($paymentMetrics['max_dpd'] ?? 0),
            currentMaxDpd: (int) ($paymentMetrics['current_dpd'] ?? 0),
            activeContracts: $contractStats['active'],
            completedContracts: $contractStats['completed'],
            historyMonths: $data->historyMonths(),
        );
    }

    /**
     * Credit-file depth in [0, 1]: the average of the due-installment and
     * history-month saturations. Depth 0 keeps a brand-new customer at the
     * neutral baseline instead of an extreme score.
     */
    private function depth(CustomerCreditData $data): float
    {
        $targetDue = max(1, $this->config->int('baseline.depth.target_due_installments', 12));
        $targetMonths = max(1, $this->config->int('baseline.depth.target_history_months', 12));

        $ratioDue = Math::saturate($data->dueCount() / $targetDue);
        $ratioMonths = Math::saturate($data->historyMonths() / $targetMonths);

        return Math::saturate(($ratioDue + $ratioMonths) / 2);
    }

    private function project(float $rawScore): int
    {
        $min = $this->config->int('display.min', 300);
        $range = $this->config->int('display.range', 550);
        $max = $this->config->int('display.max', 850);

        $displayed = (int) round($min + $rawScore / 100 * $range);

        return (int) Math::clamp((float) $displayed, (float) $min, (float) $max);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function riskBand(int $displayed): array
    {
        foreach ($this->config->array('risk_bands') as $band) {
            if ($displayed >= (int) $band['min'] && $displayed <= (int) $band['max']) {
                return [$band['level'], $band['label']];
            }
        }

        return ['very_high', 'مخاطرة عالية جدًا'];
    }

    private function confidence(CustomerCreditData $data): string
    {
        $due = $data->dueCount();
        $months = $data->historyMonths();

        $high = $this->config->array('confidence.high');
        if ($due >= (int) ($high['due_installments'] ?? 12) && $months >= (int) ($high['history_months'] ?? 12)) {
            return 'HIGH';
        }

        $medium = $this->config->array('confidence.medium');
        if ($due >= (int) ($medium['due_installments'] ?? 4) && $months >= (int) ($medium['history_months'] ?? 3)) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * @return array{active: int, completed: int, cancelled: int, total: int}
     */
    private function contractStats(CustomerCreditData $data): array
    {
        $active = 0;
        $completed = 0;
        $cancelled = 0;

        foreach ($data->contracts as $contract) {
            if ($contract->isCompleted()) {
                $completed++;
            } elseif ($contract->isCancelled()) {
                $cancelled++;
            } elseif ($contract->isActive()) {
                $active++;
            }
        }

        return [
            'active' => $active,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'total' => count($data->contracts),
        ];
    }
}
