<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Compact credit summary for customer list cards. */
class CustomerCreditScoreSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'score' => (int) $this->score,
            'score_max' => (int) config('credit_score.display.max', 850),
            'risk_level' => $this->risk_level,
            'confidence_level' => $this->confidence_level,
            'score_change' => (int) $this->score_change,
            'current_overdue_amount' => (float) $this->current_overdue_amount,
            'calculated_at' => $this->calculated_at?->toISOString(),
        ];
    }
}
