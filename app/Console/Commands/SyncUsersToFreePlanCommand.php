<?php

namespace App\Console\Commands;

use App\Helpers\LimitsHelper;
use Illuminate\Console\Command;

class SyncUsersToFreePlanCommand extends Command
{
    protected $signature = 'users:sync-free-plan';

    protected $description = 'Assign all regular users to the free plan and refresh their limits';

    public function handle(): int
    {
        $result = LimitsHelper::syncAllUsersToFreePlan();

        $this->info("Synced {$result['synced']} user(s) to {$result['plan']}.");

        return self::SUCCESS;
    }
}
