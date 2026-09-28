<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only Internal Credit Score snapshot. Rows are never updated once
 * written; every recalculation inserts a new snapshot so the score history is
 * always historically accurate.
 */
class CustomerCreditScore extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'score',
        'raw_score',
        'risk_level',
        'confidence_level',
        'score_change',
        'payment_history_score',
        'financial_burden_score',
        'credit_history_score',
        'credit_activity_score',
        'payment_consistency_score',
        'other_risk_score',
        'current_outstanding',
        'current_overdue_amount',
        'current_overdue_count',
        'max_dpd',
        'current_max_dpd',
        'active_contracts',
        'completed_contracts',
        'history_months',
        'thin_file',
        'metrics',
        'positive_factors',
        'negative_factors',
        'change_reasons',
        'configuration_snapshot',
        'model_version_id',
        'source',
        'calculated_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'raw_score' => 'float',
        'score_change' => 'integer',
        'payment_history_score' => 'float',
        'financial_burden_score' => 'float',
        'credit_history_score' => 'float',
        'credit_activity_score' => 'float',
        'payment_consistency_score' => 'float',
        'other_risk_score' => 'float',
        'current_outstanding' => 'decimal:2',
        'current_overdue_amount' => 'decimal:2',
        'current_overdue_count' => 'integer',
        'max_dpd' => 'integer',
        'current_max_dpd' => 'integer',
        'active_contracts' => 'integer',
        'completed_contracts' => 'integer',
        'history_months' => 'integer',
        'thin_file' => 'boolean',
        'metrics' => 'array',
        'positive_factors' => 'array',
        'negative_factors' => 'array',
        'change_reasons' => 'array',
        'configuration_snapshot' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(CreditScoreModelVersion::class, 'model_version_id');
    }
}
