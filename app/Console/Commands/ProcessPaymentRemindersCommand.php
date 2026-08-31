<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledRemindersJob;
use Illuminate\Console\Command;

class ProcessPaymentRemindersCommand extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Queue in-app notifications and payment reminder emails for all active users';

    public function handle(): int
    {
        ProcessScheduledRemindersJob::dispatch();

        $this->info('Scheduled payment reminders have been queued.');

        return self::SUCCESS;
    }
}
