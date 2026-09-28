<?php

namespace App\Services\CreditScore\Support;

use Carbon\CarbonImmutable;

/**
 * A contract = one Installment plan. In this system there is no separate
 * contract entity, so an installment IS the credit relationship line.
 */
final class CreditContract
{
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly float $totalAmount,
        public readonly ?CarbonImmutable $startDate,
        public readonly ?CarbonImmutable $createdAt,
        public readonly int $totalItems,
        public readonly int $paidItems,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Completed either explicitly, or implicitly when every item is settled
     * (payment-request approval does not flip the plan status today).
     */
    public function isCompleted(): bool
    {
        if ($this->status === 'completed') {
            return true;
        }

        return $this->totalItems > 0 && $this->paidItems === $this->totalItems;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || $this->status === 'canceled';
    }
}
