<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $notificationId) {}

    public function handle(FcmPushService $fcmPushService): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        $fcmPushService->sendForNotification($notification);
    }
}
