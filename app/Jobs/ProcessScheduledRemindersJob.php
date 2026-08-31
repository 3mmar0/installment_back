<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Jobs\MarkOverdueInstallmentItemsJob;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduledRemindersJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        MarkOverdueInstallmentItemsJob::dispatchSync();

        User::query()
            ->where('role', UserRole::User)
            ->whereHas('installments', fn ($query) => $query->where('status', 'active'))
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    GenerateUserPaymentNotificationsJob::dispatch($user->id);
                    SendUserPaymentRemindersJob::dispatch($user->id);
                }
            });
    }
}
