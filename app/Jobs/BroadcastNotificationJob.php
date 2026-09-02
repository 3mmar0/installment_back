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

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(
        public string $title,
        public string $message,
        public array $data = [],
        public string $type = 'system_announcement',
        public array $userIds = [],
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->broadcastToUsers(
            $this->userIds,
            $this->title,
            $this->message,
            $this->data,
            $this->type
        );
    }
}
