<?php

namespace App\Services\CreditScore\Support;

use Carbon\CarbonImmutable;

/**
 * A single installment item after delinquency analysis. Immutable so analyzers
 * can share it without side effects.
 */
final class ScoredItem
{
    public function __construct(
        public readonly CarbonImmutable $dueDate,
        public readonly float $amount,
        public readonly bool $isPaid,
        public readonly ?CarbonImmutable $closureDate,
        public readonly int $dpd,
        public readonly ?string $bucket,
        public readonly bool $isEarly,
        public readonly bool $isOnTime,
        public readonly bool $isOverdueNow,
        public readonly float $monthsAgo,
        public readonly bool $hasComeDue,
    ) {
    }
}
