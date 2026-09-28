<?php

/*
|--------------------------------------------------------------------------
| Internal Credit Score configuration
|--------------------------------------------------------------------------
|
| This is the single source of truth for the Internal Credit Score engine.
| Nothing in this file describes the official Egyptian I-Score; every band,
| weight and penalty below is an INTERNAL policy derived only from the
| installment data this system already stores.
|
| The values here seed the published model version row on install. Once a
| model version is published its configuration is copied verbatim into
| `credit_score_model_versions.configuration` and every snapshot points at
| that frozen row, so changing this file never rewrites historical scores.
|
*/

return [

    // Identifier of the model version that new calculations should use.
    'active_version' => env('CREDIT_SCORE_VERSION', 'V1'),

    /*
    |--------------------------------------------------------------------------
    | Display scale
    |--------------------------------------------------------------------------
    | Raw score lives on 0..100. Displayed score is projected onto 300..850.
    | Displayed = round(min + raw / 100 * range), clamped to [min, max].
    */
    'display' => [
        'min' => 300,
        'max' => 850,
        'range' => 550, // max - min
    ],

    /*
    |--------------------------------------------------------------------------
    | Component weights (must describe 100% of the score)
    |--------------------------------------------------------------------------
    | When a component is unavailable (null) for a customer its weight is
    | redistributed proportionally across the remaining available components.
    */
    'weights' => [
        'payment_history' => 0.35,
        'financial_burden' => 0.25,
        'credit_history' => 0.15,
        'credit_activity' => 0.10,
        'payment_consistency' => 0.10,
        'other' => 0.05,
    ],

    /*
    |--------------------------------------------------------------------------
    | Neutral baseline & credit-file depth
    |--------------------------------------------------------------------------
    | A thin file is blended toward a neutral baseline so a brand new customer
    | is neither punished to 300 nor rewarded to 850:
    |   raw = baseline_raw + (observed - baseline_raw) * depth
    | Depth grows from 0..1 as the customer accumulates due installments and
    | months of history (average of the two saturations).
    |
    | baseline_raw 58.1818 projects to a displayed score of 620.
    */
    'baseline' => [
        'raw' => 58.1818, // -> displayed 620
        'depth' => [
            'target_due_installments' => 12,
            'target_history_months' => 12,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment History component (35%)
    |--------------------------------------------------------------------------
    | Owns delinquency SEVERITY and RECENCY. Starts at 100 and subtracts a
    | recency-weighted average penalty across every installment that has come
    | due. Consistency (streaks / variance) is intentionally handled by a
    | different component to avoid double counting.
    */
    'payment_history' => [
        'start' => 100.0,
        // Base penalty points per delinquency bucket (before recency weighting).
        'penalties' => [
            'late_1_7' => 2.0,
            'late_8_30' => 8.0,
            'late_31_60' => 20.0,
            'late_61_90' => 35.0,
            'late_90_plus' => 60.0,
        ],
        // Recency: weight = exp(-lambda * months_ago).
        'recency_lambda' => 0.05,
        // Scales the averaged, recency-weighted penalty into score points.
        'penalty_multiplier' => 24.0,
        // Early settlement is a mild positive, strictly capped.
        'early_payment_bonus_per_item' => 0.5,
        'early_payment_bonus_cap' => 5.0,
        'min' => 0.0,
        'max' => 100.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Delinquency day-past-due (DPD) buckets
    |--------------------------------------------------------------------------
    */
    'dpd_buckets' => [
        'late_1_7' => [1, 7],
        'late_8_30' => [8, 30],
        'late_31_60' => [31, 60],
        'late_61_90' => [61, 90],
        'late_90_plus' => [91, null],
    ],

    // Current DPD at or above this is treated as a serious delinquency.
    'serious_delinquency_dpd' => 90,

    /*
    |--------------------------------------------------------------------------
    | Financial Burden component (25%)
    |--------------------------------------------------------------------------
    | Proportional to how much of the outstanding exposure is currently
    | overdue, plus a mild penalty for carrying many active contracts.
    | Income (DTI) and credit limit (utilisation) are NOT available in this
    | system, so they stay null and only lower confidence.
    */
    'financial_burden' => [
        'start' => 100.0,
        // overdue_ratio (0..1) * factor => penalty points.
        'overdue_ratio_factor' => 70.0,
        'overdue_ratio_penalty_cap' => 70.0,
        // Active contracts above threshold add a small penalty each.
        'active_contracts_threshold' => 3,
        'active_contract_penalty' => 4.0,
        'active_contracts_penalty_cap' => 20.0,
        'min' => 0.0,
        'max' => 100.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit History component (15%)
    |--------------------------------------------------------------------------
    | score = base + (100 - base) * saturate(history_months / full_months).
    | A thin file still earns `base`, never a harsh zero.
    */
    'credit_history' => [
        'base' => 40.0,
        'full_months' => 36,
        'min' => 0.0,
        'max' => 100.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Activity component (10%)
    |--------------------------------------------------------------------------
    | Stable activity scores high; a burst of new contracts in a short window
    | is a risk indicator. Inactivity earns no bonus (stays at start).
    */
    'credit_activity' => [
        'start' => 100.0,
        'windows_days' => [30, 90, 180],
        // New contracts within a window above the threshold cost points each.
        'thresholds' => [
            30 => 2,
            90 => 4,
            180 => 6,
        ],
        'penalty_per_excess_contract' => 8.0,
        'penalty_cap' => 60.0,
        'min' => 0.0,
        'max' => 100.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Consistency component (10%)
    |--------------------------------------------------------------------------
    | Owns REGULARITY: on-time ratio and DPD variance. Deliberately does NOT
    | reuse the payment-history penalty table.
    | score = 100 * (on_time_weight * on_time_ratio
    |               + variance_weight * (1 - saturate(dpd_stddev / scale))).
    */
    'payment_consistency' => [
        'on_time_weight' => 0.6,
        'variance_weight' => 0.4,
        'dpd_stddev_scale' => 30.0,
        'min' => 0.0,
        'max' => 100.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Other Risk Indicators component (5%)
    |--------------------------------------------------------------------------
    | Profile completeness and duplicate detection only. A missing email is
    | NOT treated as fraud and never lowers the score on its own.
    */
    'other_risk' => [
        'start' => 100.0,
        'missing_national_id_penalty' => 15.0,
        'missing_phone_penalty' => 10.0,
        'missing_guarantor_penalty' => 5.0,
        'duplicate_identity_penalty' => 25.0,
        'min' => 0.0,
        'max' => 100.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data confidence thresholds
    |--------------------------------------------------------------------------
    */
    'confidence' => [
        'high' => [
            'due_installments' => 12,
            'history_months' => 12,
        ],
        'medium' => [
            'due_installments' => 4,
            'history_months' => 3,
        ],
        // Below medium => LOW (thin credit file).
    ],

    // A customer with fewer due installments than this is flagged thin-file.
    'thin_file_due_installments' => 4,

    /*
    |--------------------------------------------------------------------------
    | Internal risk bands (NOT official I-Score classifications)
    |--------------------------------------------------------------------------
    | Ordered high -> low. `min`/`max` are inclusive on the displayed score.
    */
    'risk_bands' => [
        ['level' => 'excellent', 'label' => 'ملف داخلي ممتاز', 'min' => 800, 'max' => 850],
        ['level' => 'very_good', 'label' => 'جيد جدًا', 'min' => 750, 'max' => 799],
        ['level' => 'good', 'label' => 'جيد', 'min' => 700, 'max' => 749],
        ['level' => 'fair', 'label' => 'مقبول', 'min' => 650, 'max' => 699],
        ['level' => 'moderate', 'label' => 'مخاطرة متوسطة', 'min' => 600, 'max' => 649],
        ['level' => 'high', 'label' => 'مخاطرة عالية', 'min' => 500, 'max' => 599],
        ['level' => 'very_high', 'label' => 'مخاطرة عالية جدًا', 'min' => 300, 'max' => 499],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting thresholds (used by the notification layer)
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'significant_change_points' => 30,
        'high_risk_levels' => ['high', 'very_high'],
        'dpd_milestones' => [30, 60, 90],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recalculation
    |--------------------------------------------------------------------------
    */
    'recalculation' => [
        'queue' => env('CREDIT_SCORE_QUEUE', 'default'),
        'backfill_chunk' => 200,
        'daily_at' => '08:30',
    ],
];
