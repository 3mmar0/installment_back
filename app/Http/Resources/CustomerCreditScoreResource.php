<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCreditScoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'score' => (int) $this->score,
            'score_max' => (int) config('credit_score.display.max', 850),
            'score_min' => (int) config('credit_score.display.min', 300),
            'raw_score' => (float) $this->raw_score,
            'risk_level' => $this->risk_level,
            'risk_label' => $this->riskLabel(),
            'confidence_level' => $this->confidence_level,
            'score_change' => (int) $this->score_change,
            'thin_file' => (bool) $this->thin_file,
            'components' => [
                'payment_history' => $this->payment_history_score,
                'financial_burden' => $this->financial_burden_score,
                'credit_history' => $this->credit_history_score,
                'credit_activity' => $this->credit_activity_score,
                'payment_consistency' => $this->payment_consistency_score,
                'other' => $this->other_risk_score,
            ],
            'current_outstanding' => (float) $this->current_outstanding,
            'current_overdue_amount' => (float) $this->current_overdue_amount,
            'current_overdue_count' => (int) $this->current_overdue_count,
            'max_dpd' => (int) $this->max_dpd,
            'current_max_dpd' => (int) $this->current_max_dpd,
            'active_contracts' => (int) $this->active_contracts,
            'completed_contracts' => (int) $this->completed_contracts,
            'history_months' => (int) $this->history_months,
            'metrics' => $this->metrics,
            'positive_factors' => $this->positive_factors ?? [],
            'negative_factors' => $this->negative_factors ?? [],
            'change_reasons' => $this->change_reasons ?? [],
            'model_version' => $this->whenLoaded('modelVersion', fn () => [
                'id' => $this->modelVersion->id,
                'version' => $this->modelVersion->version,
                'label' => $this->modelVersion->label,
            ]),
            'source' => $this->source,
            'calculated_at' => $this->calculated_at?->toISOString(),
            'disclaimer' => 'تقييم ائتماني داخلي — ليس I-Score رسميًا ولا تقريرًا من الشركة المصرية للاستعلام الائتماني.',
        ];
    }

    private function riskLabel(): string
    {
        foreach ((array) config('credit_score.risk_bands', []) as $band) {
            if ($this->risk_level === ($band['level'] ?? null)) {
                return (string) ($band['label'] ?? $this->risk_level);
            }
        }

        return (string) $this->risk_level;
    }
}
