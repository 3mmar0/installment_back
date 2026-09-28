<?php

namespace App\Services\CreditScore\Analyzers;

use App\Services\CreditScore\Support\ComponentResult;
use App\Services\CreditScore\Support\CreditContract;
use App\Services\CreditScore\Support\CreditItemRecord;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CustomerCreditData;
use App\Services\CreditScore\Support\Math;

/**
 * Current Financial Burden (25%).
 *
 * Proportional to how much of the outstanding exposure is currently overdue,
 * plus a mild penalty for carrying many active contracts. Income (DTI) and
 * credit limit (utilisation) do not exist in this system, so they are reported
 * as null / not_available and only lower confidence elsewhere.
 *
 * Money is summed in integer piastres to avoid float drift.
 */
class FinancialBurdenAnalyzer
{
    public function __construct(private readonly CreditScoreConfig $config)
    {
    }

    public function analyze(CustomerCreditData $data): ComponentResult
    {
        $outstandingCents = 0;
        $overdueCents = 0;
        $upcomingCents = 0;

        foreach ($data->records as $record) {
            if ($record->isPaid) {
                continue;
            }
            if (! $this->isActivePlan($record)) {
                continue;
            }

            $cents = (int) round($record->amount * 100);
            $outstandingCents += $cents;

            if ($record->scored->isOverdueNow) {
                $overdueCents += $cents;
            } else {
                $upcomingCents += $cents;
            }
        }

        $outstanding = $outstandingCents / 100;
        $overdue = $overdueCents / 100;
        $overdueRatio = $outstandingCents > 0 ? $overdueCents / $outstandingCents : 0.0;

        $activeContracts = 0;
        foreach ($data->contracts as $contract) {
            if ($contract->isActive()) {
                $activeContracts++;
            }
        }

        $overduePenalty = min(
            $this->config->float('financial_burden.overdue_ratio_penalty_cap', 70.0),
            $overdueRatio * $this->config->float('financial_burden.overdue_ratio_factor', 70.0)
        );

        $excessContracts = max(0, $activeContracts - $this->config->int('financial_burden.active_contracts_threshold', 3));
        $contractPenalty = min(
            $this->config->float('financial_burden.active_contracts_penalty_cap', 20.0),
            $excessContracts * $this->config->float('financial_burden.active_contract_penalty', 4.0)
        );

        $monthlyIncome = $data->monthlySalary;
        $monthlyObligation = $this->estimatedMonthlyObligation($data, $outstanding, $overdue);
        $dtiPenalty = $this->dtiPenalty($monthlyIncome, $monthlyObligation);

        $score = Math::clamp(
            $this->config->float('financial_burden.start', 100.0) - $overduePenalty - $contractPenalty - $dtiPenalty,
            $this->config->float('financial_burden.min', 0.0),
            $this->config->float('financial_burden.max', 100.0)
        );

        $dti = ($monthlyIncome !== null && $monthlyIncome > 0)
            ? round($monthlyObligation / $monthlyIncome, 4)
            : null;

        $metrics = [
            'current_outstanding' => round($outstanding, 2),
            'current_overdue_amount' => round($overdue, 2),
            'upcoming_amount' => round($upcomingCents / 100, 2),
            'overdue_ratio' => round($overdueRatio, 4),
            'active_contracts' => $activeContracts,
            'dti' => $dti,
            'estimated_monthly_obligation' => round($monthlyObligation, 2),
            'utilization' => 'not_available',
            'monthly_income' => $monthlyIncome !== null ? round($monthlyIncome, 2) : null,
            'credit_limit' => null,
        ];

        return ComponentResult::score($score, $metrics);
    }

    private function estimatedMonthlyObligation(CustomerCreditData $data, float $outstanding, float $overdue): float
    {
        if ($outstanding <= 0) {
            return 0.0;
        }

        $horizon = $data->today->addDays(30);
        $dueSoon = 0.0;
        $unpaidCount = 0;

        foreach ($data->records as $record) {
            if ($record->isPaid || ! $this->isActivePlan($record)) {
                continue;
            }
            $unpaidCount++;
            if ($record->scored->isOverdueNow) {
                continue;
            }
            $due = $record->scored->dueDate ?? null;
            if ($due !== null && $due->lessThanOrEqualTo($horizon)) {
                $dueSoon += $record->amount;
            }
        }

        $monthly = $overdue + $dueSoon;
        if ($monthly > 0) {
            return $monthly;
        }

        return $unpaidCount > 0 ? $outstanding / $unpaidCount : $outstanding;
    }

    private function dtiPenalty(?float $monthlyIncome, float $monthlyObligation): float
    {
        if ($monthlyIncome === null || $monthlyIncome <= 0 || $monthlyObligation <= 0) {
            return 0.0;
        }

        $dti = $monthlyObligation / $monthlyIncome;
        $max = (float) config('credit_score.affordability.max_dti_ratio', 0.40);
        if ($dti <= $max) {
            return 0.0;
        }

        return min(12.0, ($dti - $max) * 40.0);
    }

    private function isActivePlan(CreditItemRecord $record): bool
    {
        // Outstanding only accrues on live plans; completed/cancelled owe nothing.
        return $record->installmentStatus === 'active';
    }
}
