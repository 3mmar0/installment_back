<?php

namespace App\Services\CreditScore\Analyzers;

use App\Services\CreditScore\Support\ComponentResult;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CustomerCreditData;
use App\Services\CreditScore\Support\Math;

/**
 * Other Internal Risk Indicators (5%).
 *
 * Profile completeness and duplicate-identity detection only. A missing email
 * is NOT treated as fraud and never lowers the score on its own. No sensitive
 * attribute unrelated to repayment risk is used.
 */
class OtherRiskAnalyzer
{
    public function __construct(private readonly CreditScoreConfig $config)
    {
    }

    public function analyze(CustomerCreditData $data): ComponentResult
    {
        $score = $this->config->float('other_risk.start', 100.0);

        $hasNationalId = $this->filled($data->nationalId);
        $hasPhone = $this->filled($data->phone);
        $hasEmail = $this->filled($data->email);

        if (! $hasNationalId) {
            $score -= $this->config->float('other_risk.missing_national_id_penalty', 15.0);
        }
        if (! $hasPhone) {
            $score -= $this->config->float('other_risk.missing_phone_penalty', 10.0);
        }
        if (! $data->guarantorPresent) {
            $score -= $this->config->float('other_risk.missing_guarantor_penalty', 5.0);
        }
        if ($data->duplicateIdentity) {
            $score -= $this->config->float('other_risk.duplicate_identity_penalty', 25.0);
        }

        $score = Math::clamp(
            $score,
            $this->config->float('other_risk.min', 0.0),
            $this->config->float('other_risk.max', 100.0)
        );

        return ComponentResult::score($score, [
            'identity_verified' => $hasNationalId,
            'phone_present' => $hasPhone,
            'email_present' => $hasEmail,
            'guarantor_present' => $data->guarantorPresent,
            'has_client_account' => $data->hasClientAccount,
            'duplicate_identity' => $data->duplicateIdentity,
        ]);
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
