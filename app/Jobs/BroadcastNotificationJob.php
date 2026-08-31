<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $message,
        public array $data = [],
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->broadcastToAllUsers($this->title, $this->message, $this->data);
    }
}
