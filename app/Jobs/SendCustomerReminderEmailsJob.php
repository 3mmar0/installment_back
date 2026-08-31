<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCustomerReminderEmailsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public int $customerId,
    ) {}

    public function handle(EmailNotificationService $emailNotificationService): void
    {
        $user = User::find($this->userId);
        $customer = Customer::find($this->customerId);

        if (!$user || !$customer) {
            return;
        }

        $emailNotificationService->sendCustomerPaymentReminders($customer, $user);
    }
}
