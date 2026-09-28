<?php

namespace App\Services\CreditScore\Support;

use Carbon\CarbonImmutable;

/**
 * The complete, pre-loaded input for scoring one customer. Built once by the
 * collector (a handful of queries, no N+1) and handed to every analyzer so the
 * analyzers never touch the database and stay fully unit-testable.
 */
final class CustomerCreditData
{
    /**
     * @param  CreditItemRecord[]  $records
     * @param  CreditContract[]  $contracts
     */
    public function __construct(
        public readonly int $customerId,
        public readonly ?int $userId,
        public readonly ?CarbonImmutable $customerCreatedAt,
        public readonly ?string $nationalId,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly bool $guarantorPresent,
        public readonly bool $hasClientAccount,
        public readonly bool $duplicateIdentity,
        public readonly ?float $monthlySalary,
        public readonly CarbonImmutable $today,
        public readonly array $records,
        public readonly array $contracts,
    ) {
    }

    /** Items that have already come due (paid, or unpaid past their due date). */
    public function dueRecords(): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (CreditItemRecord $r) => $r->scored->hasComeDue
        ));
    }

    public function dueCount(): int
    {
        return count($this->dueRecords());
    }

    public function hasAnyContract(): bool
    {
        return count($this->contracts) > 0;
    }

    /**
     * Months since the earliest contract start date (the credit relationship
     * start). Returns 0 when there is no contract history at all.
     */
    public function historyMonths(): int
    {
        $earliest = null;
        foreach ($this->contracts as $contract) {
            if ($contract->startDate === null) {
                continue;
            }
            if ($earliest === null || $contract->startDate->lessThan($earliest)) {
                $earliest = $contract->startDate;
            }
        }

        if ($earliest === null) {
            return 0;
        }

        if ($this->today->lessThan($earliest)) {
            return 0;
        }

        $days = (int) $earliest->diffInDays($this->today);

        return (int) max(0, floor($days / 30.4375));
    }
}
