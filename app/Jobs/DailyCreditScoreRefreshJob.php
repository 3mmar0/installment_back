<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\CreditScore\CreditScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily refresh: recalculate customers marked dirty and customers whose
 * installments crossed a due date since their last snapshot.
 */
class DailyCreditScoreRefreshJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(CreditScoreService $creditScoreService): void
    {
        $creditScoreService->ensureActiveModelVersion();

        $today = now()->startOfDay();

        Customer::query()
            ->whereNotNull('credit_score_dirty_at')
            ->chunkById(200, function ($customers) use ($creditScoreService) {
                foreach ($customers as $customer) {
                    $creditScoreService->recalculate($customer);
                }
            });

        Customer::query()
            ->whereNull('credit_score_dirty_at')
            ->whereHas('installments.items', function ($q) use ($today) {
                $q->whereNull('paid_at')
                    ->where('status', '!=', 'paid')
                    ->whereDate('due_date', $today);
            })
            ->chunkById(200, function ($customers) use ($creditScoreService) {
                foreach ($customers as $customer) {
                    $creditScoreService->recalculate($customer);
                }
            });
    }
}
