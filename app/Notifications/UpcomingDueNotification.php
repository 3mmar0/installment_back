<?php

namespace App\Notifications;

use App\Models\InstallmentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public InstallmentItem $item) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $inst = $this->item->installment;
        return (new MailMessage)
            ->subject('Upcoming installment due')
            ->greeting('Hello')
            ->line('A payment is due soon.')
            ->line('Customer: ' . $inst->customer->name)
            ->line('Due date: ' . $this->item->due_date->toFormattedDateString())
            ->line('Amount: ' . $this->item->amount . ' EGP')
            ->action('View details', url('/installments/' . $inst->id));
    }
}
