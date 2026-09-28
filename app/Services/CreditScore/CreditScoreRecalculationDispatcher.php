<?php

namespace App\Services\CreditScore;

use App\Jobs\RecalculateCustomerCreditScoreJob;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;

/**
 * Marks customers stale and queues recalculation without embedding scoring logic
 * inside payment/installment controllers.
 */
class CreditScoreRecalculationDispatcher
{
    public function dispatchForCustomerId(?int $customerId): void
    {
        if ($customerId === null || $customerId <= 0) {
            return;
        }

        Customer::query()
            ->whereKey($customerId)
            ->each(fn (Customer $customer) => $this->markAndQueue($customer));
    }

    public function dispatchForInstallment(?Installment $installment): void
    {
        if ($installment === null) {
            return;
        }

        $this->dispatchForCustomerId($installment->customer_id);
    }

    public function dispatchForInstallmentItem(?InstallmentItem $item): void
    {
        if ($item === null) {
            return;
        }

        $item->loadMissing('installment');
        $this->dispatchForInstallment($item->installment);
    }

    private function markAndQueue(Customer $customer): void
    {
        $customer->markCreditScoreDirty();

        RecalculateCustomerCreditScoreJob::dispatch($customer->id)
            ->onQueue((string) config('credit_score.recalculation.queue', 'default'));
    }
}
