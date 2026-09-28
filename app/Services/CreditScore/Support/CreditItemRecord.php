<?php

namespace App\Services\CreditScore\Support;

use Carbon\CarbonImmutable;

/**
 * One installment item enriched with its delinquency analysis and a few
 * facts about its parent installment that the analyzers need.
 */
final class CreditItemRecord
{
    public function __construct(
        public readonly ScoredItem $scored,
        public readonly float $amount,
        public readonly bool $isPaid,
        public readonly string $installmentStatus,
        public readonly CarbonImmutable $installmentStart,
    ) {
    }
}
