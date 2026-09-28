<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\CreditScore\CreditScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateCustomerCreditScoreJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $customerId)
    {
    }

    public function handle(CreditScoreService $creditScoreService): void
    {
        $customer = Customer::query()->find($this->customerId);
        if ($customer === null) {
            return;
        }

        $creditScoreService->recalculate($customer);
    }
}
