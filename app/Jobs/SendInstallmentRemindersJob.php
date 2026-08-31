<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendInstallmentRemindersJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $installmentId,
        public int $userId,
        public ?int $itemId = null,
    ) {}

    public function handle(
        NotificationService $notificationService,
        EmailNotificationService $emailNotificationService
    ): void {
        $user = User::find($this->userId);

        if (!$user) {
            return;
        }

        DB::transaction(function () use ($notificationService, $emailNotificationService, $user) {
            $installment = $user->isOwner()
                ? \App\Models\Installment::find($this->installmentId)
                : $user->installments()->find($this->installmentId);

            if (!$installment) {
                return;
            }

            $query = $installment->items()
                ->where('status', '!=', 'paid')
                ->whereNull('paid_at')
                ->orderBy('due_date');

            if ($this->itemId !== null) {
                $query->where('id', $this->itemId);
            }

            $items = $query->get();

            if ($items->isEmpty()) {
                return;
            }

            foreach ($items as $item) {
                $notificationService->notifyItemDueReminder($user, $item);
            }

            $emailNotificationService->sendItemsReminderEmails($items);
        });
    }
}
