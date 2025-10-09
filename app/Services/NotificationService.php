<?php

namespace App\Services;

use App\Models\User;
use App\Models\InstallmentItem;
use App\Notifications\OverdueNotification;
use App\Notifications\UpcomingDueNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function sendOverdueNotifications()
    {
        $overdueItems = InstallmentItem::query()
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->with(['installment.user', 'installment.customer'])
            ->get();

        foreach ($overdueItems as $item) {
            // Notify the user
            $item->installment->user->notify(new OverdueNotification($item));

            // Notify the customer if they have email
            if ($item->installment->customer->email) {
                Notification::route('mail', $item->installment->customer->email)
                    ->notify(new OverdueNotification($item));
            }
        }
    }

    public function sendUpcomingDueNotifications()
    {
        $dueSoonItems = InstallmentItem::query()
            ->where('status', 'pending')
            ->whereBetween('due_date', [now(), now()->addDays(3)])
            ->with(['installment.user', 'installment.customer'])
            ->get();

        foreach ($dueSoonItems as $item) {
            // Notify the user
            $item->installment->user->notify(new UpcomingDueNotification($item));

            // Notify the customer if they have email
            if ($item->installment->customer->email) {
                Notification::route('mail', $item->installment->customer->email)
                    ->notify(new UpcomingDueNotification($item));
            }
        }
    }

    public function sendDailyNotifications()
    {
        $this->sendOverdueNotifications();
        $this->sendUpcomingDueNotifications();
    }
}
