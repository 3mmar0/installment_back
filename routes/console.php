<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Notification;
use App\Models\InstallmentItem;
use App\Notifications\UpcomingDueNotification;
use App\Notifications\OverdueNotification;

Schedule::call(function () {
    $start = now()->startOfDay();
    $end = now()->addDays(3)->endOfDay();

    InstallmentItem::query()
        ->with(['installment.user', 'installment.customer'])
        ->whereNull('paid_at')
        ->whereBetween('due_date', [$start, $end])
        ->chunkById(500, function ($items) {
            foreach ($items as $item) {
                $item->installment->user->notify(new UpcomingDueNotification($item));
                if ($item->installment->customer->email) {
                    Notification::route('mail', $item->installment->customer->email)
                        ->notify(new UpcomingDueNotification($item));
                }
            }
        });
})->dailyAt('08:00')->name('due-soon-reminders');

Schedule::call(function () {
    $now = now()->startOfDay();

    InstallmentItem::query()
        ->with(['installment.user', 'installment.customer'])
        ->whereNull('paid_at')
        ->where('due_date', '<', $now)
        ->chunkById(500, function ($items) {
            foreach ($items as $item) {
                $item->update(['status' => 'overdue']);
                $item->installment->user->notify(new OverdueNotification($item));
                if ($item->installment->customer->email) {
                    Notification::route('mail', $item->installment->customer->email)
                        ->notify(new OverdueNotification($item));
                }
            }
        });
})->dailyAt('09:00')->name('overdue-reminders');
