<?php

namespace App\Services;

use App\Helpers\LimitsHelper;
use App\Models\InstallmentItem;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create a new notification.
     */
    public function create(User $user, string $type, string $title, string $message, array $data = []): Notification
    {
        if (!$user->isOwner() && !LimitsHelper::canCreate($user->id, 'notifications')) {
            abort(403, LimitsHelper::getLimitExceededMessage('notifications'));
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        if (!$user->isOwner()) {
            LimitsHelper::incrementUsage($user->id, 'notifications');
        }

        return $notification;
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2) . ' ج.م';
    }

    /**
     * Notify owners about a new user registration.
     */
    public function notifyNewUserRegistered(User $owner, User $newUser): Notification
    {
        return $this->create(
            $owner,
            'new_user',
            'مستخدم جديد',
            "تم تسجيل مستخدم جديد: {$newUser->name} ({$newUser->email})",
            [
                'new_user_id' => $newUser->id,
                'new_user_email' => $newUser->email,
                'new_user_name' => $newUser->name,
            ]
        );
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
            $daysUntilDue = max(0, (int) now()->diffInDays($item->due_date, false));
            $customerName = $item->installment->customer->name;
            $amountFormatted = $this->formatMoney((float) $item->amount);

            $this->create(
                $user,
                'payment_due',
                'دفعة مستحقة قريباً',
                "دفعة بقيمة {$amountFormatted} مستحقة خلال {$daysUntilDue} يوم للعميل {$customerName}",
                [
                    'installment_id' => $item->installment_id,
                    'item_id' => $item->id,
                    'amount' => $item->amount,
                    'due_date' => $item->due_date,
                    'days_until_due' => $daysUntilDue,
                    'customer_name' => $customerName,
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
            $daysOverdue = max(0, (int) now()->diffInDays($item->due_date));
            $customerName = $item->installment->customer->name;
            $amountFormatted = $this->formatMoney((float) $item->amount);

            $this->create(
                $user,
                'payment_overdue',
                'دفعة متأخرة',
                "دفعة بقيمة {$amountFormatted} من العميل {$customerName} متأخرة {$daysOverdue} يوم",
                [
                    'installment_id' => $item->installment_id,
                    'item_id' => $item->id,
                    'amount' => $item->amount,
                    'due_date' => $item->due_date,
                    'days_overdue' => $daysOverdue,
                    'customer_name' => $customerName,
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
        $amountFormatted = $this->formatMoney($paidAmount);

        return $this->create(
            $user,
            'payment_received',
            'تم استلام دفعة',
            "تم استلام {$amountFormatted} من العميل {$customerName}",
            [
                'installment_id' => $item->installment_id,
                'item_id' => $item->id,
                'paid_amount' => $paidAmount,
                'customer_name' => $customerName,
            ]
        );
    }

    /**
     * Notify about installment creation.
     */
    public function notifyInstallmentCreated(User $user, \App\Models\Installment $installment): Notification
    {
        $customerName = $installment->customer->name;
        $totalFormatted = $this->formatMoney((float) $installment->total_amount);
        $months = (int) $installment->months;
        $monthsLabel = $months === 1 ? 'شهر' : 'شهراً';

        return $this->create(
            $user,
            'installment_created',
            'قسط جديد',
            "تم إنشاء خطة أقساط للعميل {$customerName} — الإجمالي {$totalFormatted} على {$months} {$monthsLabel}",
            [
                'installment_id' => $installment->id,
                'customer_id' => $installment->customer_id,
                'customer_name' => $customerName,
                'total_amount' => $installment->total_amount,
                'months' => $months,
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
