<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\InstallmentItem;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create a new notification.
     */
    public function create(User $user, string $type, string $title, string $message, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Notify about upcoming payments (due in 3 days or less).
     */
    public function notifyUpcomingPayments(User $user): int
    {
        $dueSoon = InstallmentItem::query()
            ->whereHas('installment', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 'active');
            })
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(3)->endOfDay()])
            ->with(['installment.customer'])
            ->get();

        $count = 0;
        foreach ($dueSoon as $item) {
            $daysUntilDue = now()->diffInDays($item->due_date, false);
            $this->create(
                $user,
                'payment_due',
                "Payment Due Soon",
                "Payment of $" . number_format($item->amount, 2) . " is due in {$daysUntilDue} day(s) for {$item->installment->customer->name}",
                [
                    'installment_id' => $item->installment_id,
                    'item_id' => $item->id,
                    'amount' => $item->amount,
                    'due_date' => $item->due_date,
                    'customer_name' => $item->installment->customer->name,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Notify about overdue payments.
     */
    public function notifyOverduePayments(User $user): int
    {
        $overdue = InstallmentItem::query()
            ->whereHas('installment', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 'active');
            })
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->startOfDay())
            ->with(['installment.customer'])
            ->get();

        $count = 0;
        foreach ($overdue as $item) {
            $daysOverdue = now()->diffInDays($item->due_date);
            $this->create(
                $user,
                'payment_overdue',
                "Payment Overdue",
                "Payment of $" . number_format($item->amount, 2) . " from {$item->installment->customer->name} is {$daysOverdue} day(s) overdue",
                [
                    'installment_id' => $item->installment_id,
                    'item_id' => $item->id,
                    'amount' => $item->amount,
                    'due_date' => $item->due_date,
                    'days_overdue' => $daysOverdue,
                    'customer_name' => $item->installment->customer->name,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Notify about received payments.
     */
    public function notifyPaymentReceived(User $user, InstallmentItem $item, float $paidAmount): Notification
    {
        $customerName = $item->installment->customer->name;

        return $this->create(
            $user,
            'payment_received',
            "Payment Received",
            "Received $" . number_format($paidAmount, 2) . " from {$customerName}",
            [
                'installment_id' => $item->installment_id,
                'item_id' => $item->id,
                'paid_amount' => $paidAmount,
                'customer_name' => $customerName,
            ]
        );
    }

    /**
     * Get user notifications.
     */
    public function getUserNotifications(User $user, bool $unreadOnly = false): Collection
    {
        $query = $user->notifications()->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->get();
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $notificationId, User $user): bool
    {
        $notification = $user->notifications()->findOrFail($notificationId);

        if (!$notification->isRead()) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * Get unread count.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Delete old read notifications (older than 30 days).
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return Notification::whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}
