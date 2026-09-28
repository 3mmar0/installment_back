<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\CreditScore\CreditScoreService;
use Illuminate\Console\Command;

class BackfillCreditScoresCommand extends Command
{
    protected $signature = 'credit-score:backfill
                            {--user= : Limit to a merchant user id}
                            {--chunk=200 : Batch size}';

    protected $description = 'Calculate initial Internal Credit Score snapshots for customers missing a current score.';

    public function handle(CreditScoreService $creditScoreService): int
    {
        $creditScoreService->ensureActiveModelVersion();

        $chunk = max(50, (int) $this->option('chunk'));
        $userId = $this->option('user');

        $query = Customer::query()
            ->whereNull('current_credit_score_id')
            ->whereHas('installments');
        if ($userId !== null) {
            $query->where('user_id', (int) $userId);
        }

        $total = (clone $query)->count();
        $this->info("Backfilling Internal Credit Score for {$total} customers...");

        $processed = 0;
        $query->orderBy('id')->chunkById($chunk, function ($customers) use ($creditScoreService, &$processed) {
            foreach ($customers as $customer) {
                $creditScoreService->recalculate($customer);
                $processed++;
            }
            $this->line("Processed {$processed}...");
        });

        $this->info("Done. {$processed} customers scored.");

        return self::SUCCESS;
    }
}
