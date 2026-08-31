<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUserPaymentRemindersJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $userId,
    ) {}

    public function handle(EmailNotificationService $emailNotificationService): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            return;
        }

        $emailNotificationService->dispatchPaymentReminders($user);
    }
}
