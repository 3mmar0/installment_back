<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateUserPaymentNotificationsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $userId,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            return;
        }

        $notificationService->notifyUpcomingPayments($user);
        $notificationService->notifyOverduePayments($user);
    }
}
