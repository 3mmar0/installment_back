<?php

namespace App\Services\CreditScore;

use App\Models\Installment;
use App\Models\CreditScoreAuditLog;
use App\Models\CreditScoreModelVersion;
use App\Models\Customer;
use App\Models\CustomerCreditScore;
use App\Models\User;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CreditScoreResult;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates model-version resolution, data collection, calculation,
 * explanation, snapshot persistence, and optional alerts.
 */
class CreditScoreService
{
    public function __construct(
        private readonly CustomerCreditDataCollector $collector,
        private readonly CreditScoreExplanationService $explanation,
        private readonly CreditScoreAlertService $alerts,
    ) {
    }

    public function ensureActiveModelVersion(): CreditScoreModelVersion
    {
        $versionKey = (string) config('credit_score.active_version', 'V1');

        $existing = CreditScoreModelVersion::query()
            ->where('version', $versionKey)
            ->first();

        if ($existing !== null) {
            if (! $existing->is_active) {
                CreditScoreModelVersion::query()->where('is_active', true)->update(['is_active' => false]);
                $existing->update(['is_active' => true, 'effective_at' => $existing->effective_at ?? now()]);
            }

            return $existing;
        }

        return DB::transaction(function () use ($versionKey) {
            CreditScoreModelVersion::query()->where('is_active', true)->update(['is_active' => false]);

            return CreditScoreModelVersion::create([
                'version' => $versionKey,
                'label' => 'Internal Credit Score '.$versionKey,
                'description' => 'Internal scoring model — not an official I-Score or bureau report.',
                'configuration' => (array) config('credit_score'),
                'is_active' => true,
                'effective_at' => now(),
            ]);
        });
    }

    public function recalculate(
        Customer $customer,
        ?User $actor = null,
        bool $manual = false,
        string $source = 'actual'
    ): ?CustomerCreditScore {
        if (! Installment::query()->where('customer_id', $customer->id)->exists()) {
            $this->clearCurrentScore($customer);

            return null;
        }

        $modelVersion = $this->ensureActiveModelVersion();
        $config = CreditScoreConfig::fromArray($modelVersion->configuration ?? (array) config('credit_score'));

        $data = $this->collector->collect($customer, $config);
        $calculator = new CreditScoreCalculator($config);
        $result = $calculator->calculate($data);

        $previous = CustomerCreditScore::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('calculated_at')
            ->orderByDesc('id')
            ->first();

        $result->scoreChange = $previous !== null ? $result->score - (int) $previous->score : 0;
        $result->positiveFactors = $this->explanation->positiveFactors($result, $data);
        $result->negativeFactors = $this->explanation->negativeFactors($result, $data);
        $result->changeReasons = $this->explanation->changeReasons($result, $previous);

        $snapshot = DB::transaction(function () use (
            $customer,
            $modelVersion,
            $result,
            $config,
            $source
        ) {
            $snapshot = CustomerCreditScore::create([
                'customer_id' => $customer->id,
                'user_id' => $customer->user_id,
                'score' => $result->score,
                'raw_score' => round($result->rawScore, 3),
                'risk_level' => $result->riskLevel,
                'confidence_level' => $result->confidenceLevel,
                'score_change' => $result->scoreChange,
                'payment_history_score' => $result->components['payment_history'],
                'financial_burden_score' => $result->components['financial_burden'],
                'credit_history_score' => $result->components['credit_history'],
                'credit_activity_score' => $result->components['credit_activity'],
                'payment_consistency_score' => $result->components['payment_consistency'],
                'other_risk_score' => $result->components['other'],
                'current_outstanding' => $result->currentOutstanding,
                'current_overdue_amount' => $result->currentOverdueAmount,
                'current_overdue_count' => $result->currentOverdueCount,
                'max_dpd' => $result->maxDpd,
                'current_max_dpd' => $result->currentMaxDpd,
                'active_contracts' => $result->activeContracts,
                'completed_contracts' => $result->completedContracts,
                'history_months' => max(0, $result->historyMonths),
                'thin_file' => $result->thinFile,
                'metrics' => $result->metrics,
                'positive_factors' => $result->positiveFactors,
                'negative_factors' => $result->negativeFactors,
                'change_reasons' => $result->changeReasons,
                'configuration_snapshot' => $config->toArray(),
                'model_version_id' => $modelVersion->id,
                'source' => $source,
                'calculated_at' => now(),
            ]);

            $customer->forceFill([
                'current_credit_score_id' => $snapshot->id,
                'credit_score_dirty_at' => null,
            ])->saveQuietly();

            return $snapshot;
        });

        if ($manual && $actor !== null) {
            CreditScoreAuditLog::create([
                'actor_user_id' => $actor->id,
                'customer_id' => $customer->id,
                'action' => CreditScoreAuditLog::ACTION_MANUAL_RECALCULATION,
                'context' => [
                    'snapshot_id' => $snapshot->id,
                    'score' => $snapshot->score,
                ],
            ]);
        }

        if ($customer->user_id !== null) {
            $this->alerts->maybeNotify($customer, $snapshot, $previous);
        }

        return $snapshot->fresh(['modelVersion']);
    }

    public function clearCurrentScore(Customer $customer): void
    {
        if ($customer->current_credit_score_id === null && $customer->credit_score_dirty_at === null) {
            return;
        }

        $customer->forceFill([
            'current_credit_score_id' => null,
            'credit_score_dirty_at' => null,
        ])->saveQuietly();
    }

    public function preview(Customer $customer): ?CreditScoreResult
    {
        if (! Installment::query()->where('customer_id', $customer->id)->exists()) {
            return null;
        }

        $modelVersion = $this->ensureActiveModelVersion();
        $config = CreditScoreConfig::fromArray($modelVersion->configuration ?? (array) config('credit_score'));
        $data = $this->collector->collect($customer, $config);
        $result = (new CreditScoreCalculator($config))->calculate($data);
        $result->positiveFactors = $this->explanation->positiveFactors($result, $data);
        $result->negativeFactors = $this->explanation->negativeFactors($result, $data);

        return $result;
    }
}
