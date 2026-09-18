<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Helpers\LimitsHelper;
use App\Jobs\SendPushNotificationJob;
use App\Models\ClientAccount;
use App\Models\InstallmentItem;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a new notification.
     * Set $enforceLimits to false for side-effect notifications (e.g. after installment create)
     * so a notification quota never fails the parent operation.
     */
    public function create(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = [],
        bool $enforceLimits = true
    ): ?Notification {
        if ($enforceLimits && ! $user->isOwner() && ! LimitsHelper::canCreate($user->id, 'notifications')) {
            abort(403, LimitsHelper::getLimitExceededMessage('notifications'));
        }

        if (! $enforceLimits && ! $user->isOwner() && ! LimitsHelper::canCreate($user->id, 'notifications')) {
            Log::info('Skipping notification due to plan limit', [
                'user_id' => $user->id,
                'type' => $type,
            ]);

            return null;
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        if (! $user->isOwner()) {
            LimitsHelper::incrementUsage($user->id, 'notifications');
        }

        $this->queuePushNotification($notification);

        $this->notifyPlatformAdmins(
            $type,
            $title,
            $message,
            $data,
            $user
        );

        return $notification;
    }

    /**
     * Create an in-app notification for a client account (no plan limits).
     */
    public function createForClient(
        ClientAccount $client,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): Notification {
        $notification = Notification::create([
            'user_id' => null,
            'client_account_id' => $client->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        $this->queuePushNotification($notification);

        $this->notifyPlatformAdmins(
            $type,
            $title,
            $message,
            array_merge($data, [
                'client_account_id' => $client->id,
                'client_name' => $client->name,
            ])
        );

        return $notification;
    }

    /**
     * Give every platform administrator an in-app audit notification without
     * consuming a merchant's notification allowance. This intentionally writes
     * directly instead of calling create() so mirrored records are not mirrored
     * again.
     */
    public function notifyPlatformAdmins(
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?User $actor = null
    ): void {
        if (in_array($type, ['payment_due', 'payment_overdue'], true)) {
            return;
        }

        try {
            $this->platformAdminsQuery()
                ->when($actor, fn ($query) => $query->whereKeyNot($actor->id))
                ->each(function (User $admin) use ($type, $title, $message, $data, $actor) {
                    Notification::create([
                        'user_id' => $admin->id,
                        'type' => $type,
                        'title' => $actor
                            ? "{$title} — {$actor->name}"
                            : $title,
                        'message' => $message,
                        'data' => array_merge($data, [
                            'is_platform_admin_copy' => true,
                            'actor_id' => $actor?->id,
                            'actor_name' => $actor?->name,
                            'actor_email' => $actor?->email,
                        ]),
                    ]);
                });
        } catch (\Throwable $e) {
            // Monitoring must never prevent the customer-facing operation.
            Log::warning('Failed to mirror notification to platform administrators', [
                'type' => $type,
                'actor_id' => $actor?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function queuePushNotification(Notification $notification): void
    {
        try {
            SendPushNotificationJob::dispatch($notification->id);
        } catch (\Throwable $e) {
            Log::warning('Failed to queue FCM push', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2).' ج.م';
    }

    /**
     * @param  Collection<int, InstallmentItem>  $overdue
     * @return array{title: string, message: string, data: array<string, mixed>}
     */
    private function overdueDigest(Collection $overdue): array
    {
        $count = $overdue->count();
        $total = round((float) $overdue->sum(fn (InstallmentItem $item) => (float) $item->amount), 2);
        $installmentIds = $overdue->pluck('installment_id')->unique()->values();
        $label = $count === 1 ? 'دفعة متأخرة' : 'دفعات متأخرة';

        $data = [
            'merged' => true,
            'count' => $count,
            'total_amount' => $total,
            'item_ids' => $overdue->pluck('id')->values()->all(),
            'installment_ids' => $installmentIds->all(),
            'items' => $this->overdueItemRows($overdue),
        ];

        if ($installmentIds->count() === 1) {
            $data['installment_id'] = $installmentIds->first();
        }

        return [
            'title' => $label,
            'message' => "لديك {$count} {$label} بإجمالي {$this->formatMoney($total)}",
            'data' => $data,
        ];
    }

    /**
     * @param  Collection<int, InstallmentItem>  $overdue
     * @return list<array<string, mixed>>
     */
    private function overdueItemRows(Collection $overdue): array
    {
        return $overdue->map(function (InstallmentItem $item) {
            $due = $item->due_date instanceof \DateTimeInterface
                ? $item->due_date->format('Y-m-d')
                : (string) $item->due_date;

            return [
                'item_id' => $item->id,
                'installment_id' => $item->installment_id,
                'customer_id' => $item->installment->customer_id,
                'customer_name' => $item->installment->customer->name ?? 'العميل',
                'merchant_id' => $item->installment->user_id,
                'merchant_name' => $item->installment->user->name ?? 'البائع',
                'amount' => (float) $item->amount,
                'due_date' => $due,
                'days_overdue' => max(0, (int) now()->startOfDay()->diffInDays($item->due_date)),
            ];
        })->values()->all();
    }

    /**
     * One weekly overdue digest for every platform admin.
     */
    public function notifyPlatformAdminsOverdueDigest(): int
    {
        $overdue = InstallmentItem::query()
            ->whereHas('installment', fn ($query) => $query->where('status', 'active'))
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->startOfDay())
            ->with(['installment.customer', 'installment.user'])
            ->orderBy('due_date')
            ->get();

        if ($overdue->isEmpty()) {
            return 0;
        }

        $digest = $this->overdueDigest($overdue);
        $count = (int) $digest['data']['count'];
        $label = $count === 1 ? 'دفعة متأخرة' : 'دفعات متأخرة';

        return $this->createForPlatformAdmins(
            'payment_overdue',
            'ملخص الدفعات المتأخرة',
            "هناك {$count} {$label} بإجمالي {$this->formatMoney((float) $digest['data']['total_amount'])}",
            array_merge($digest['data'], [
                'is_platform_admin_digest' => true,
            ])
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createForPlatformAdmins(string $type, string $title, string $message, array $data): int
    {
        $created = 0;

        $this->platformAdminsQuery()->each(function (User $admin) use ($type, $title, $message, $data, &$created) {
            $notification = Notification::create([
                'user_id' => $admin->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            $this->queuePushNotification($notification);
            $created++;
        });

        return $created;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function platformAdminsQuery()
    {
        $configuredEmails = array_filter(
            config('app.platform_admin_emails', []),
            fn ($email) => is_string($email) && $email !== ''
        );

        return User::query()->where(function ($query) use ($configuredEmails) {
            $query->where('is_platform_admin', true);

            if ($configuredEmails !== []) {
                $query->orWhereIn('email', $configuredEmails);
            }
        });
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
     * Notify about overdue payments as one weekly merged digest.
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
            ->with(['installment.customer', 'installment.user'])
            ->get();

        if ($overdue->isEmpty()) {
            return 0;
        }

        $digest = $this->overdueDigest($overdue);

        $this->create(
            $user,
            'payment_overdue',
            $digest['title'],
            $digest['message'],
            $digest['data']
        );

        return 1;
    }

    /**
     * Notify a client about overdue payments as one weekly merged digest.
     */
    public function notifyClientOverduePayments(ClientAccount $client): int
    {
        $customerIds = $client->customers()->pluck('id');

        if ($customerIds->isEmpty()) {
            return 0;
        }

        $overdue = InstallmentItem::query()
            ->whereHas('installment', function ($query) use ($customerIds) {
                $query->whereIn('customer_id', $customerIds)
                    ->where('status', 'active');
            })
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->startOfDay())
            ->with(['installment.user', 'installment.customer'])
            ->get();

        if ($overdue->isEmpty()) {
            return 0;
        }

        $digest = $this->overdueDigest($overdue);
        $vendorNames = $overdue
            ->map(fn (InstallmentItem $item) => $item->installment->user->name ?? 'البائع')
            ->unique()
            ->values();
        $vendorLabel = $vendorNames->count() === 1
            ? (string) $vendorNames->first()
            : 'البائعين';

        $this->createForClient(
            $client,
            'payment_overdue',
            $digest['title'],
            "{$digest['message']} لدى {$vendorLabel}",
            $digest['data']
        );

        return 1;
    }

    /**
     * In-app reminder for a single unpaid installment item.
     */
    public function notifyItemDueReminder(User $user, InstallmentItem $item): Notification
    {
        $item->loadMissing(['installment.customer']);
        $customerName = $item->installment->customer->name ?? 'العميل';
        $amountFormatted = $this->formatMoney((float) $item->amount);
        $dueFormatted = $item->due_date instanceof \Carbon\Carbon
            ? $item->due_date->format('Y-m-d')
            : (string) $item->due_date;

        $isOverdue = $item->due_date < now()->startOfDay();
        $type = $isOverdue ? 'payment_overdue' : 'payment_due';
        $title = $isOverdue ? 'تذكير بدفعة متأخرة' : 'تذكير بدفعة مستحقة';

        if ($isOverdue) {
            $daysOverdue = max(0, (int) now()->diffInDays($item->due_date));
            $message = "تذكير: دفعة بقيمة {$amountFormatted} للعميل {$customerName} متأخرة {$daysOverdue} يوم (استحقاق {$dueFormatted})";
            $extra = ['days_overdue' => $daysOverdue];
        } else {
            $daysUntilDue = max(0, (int) now()->diffInDays($item->due_date, false));
            $message = "تذكير: دفعة بقيمة {$amountFormatted} للعميل {$customerName} مستحقة خلال {$daysUntilDue} يوم ({$dueFormatted})";
            $extra = ['days_until_due' => $daysUntilDue];
        }

        return $this->create(
            $user,
            $type,
            $title,
            $message,
            array_merge([
                'installment_id' => $item->installment_id,
                'item_id' => $item->id,
                'amount' => $item->amount,
                'due_date' => $dueFormatted,
                'customer_name' => $customerName,
            ], $extra)
        );
    }

    /**
     * Broadcast an in-app notification to all regular users.
     */
    public function broadcastToAllUsers(
        string $title,
        string $message,
        array $data = [],
        string $type = 'system_announcement'
    ): int {
        return $this->broadcastToUsers([], $title, $message, $data, $type);
    }

    /**
     * Broadcast an in-app notification to selected regular users.
     * An empty $userIds list sends to every regular user.
     *
     * @param  list<int>  $userIds
     */
    public function broadcastToUsers(
        array $userIds,
        string $title,
        string $message,
        array $data = [],
        string $type = 'system_announcement'
    ): int {
        $count = 0;

        $query = User::query()->where('role', UserRole::User);

        if ($userIds !== []) {
            $query->whereIn('id', $userIds);
        }

        $query->chunkById(100, function ($users) use ($title, $message, $data, $type, &$count) {
            foreach ($users as $user) {
                $this->create(
                    $user,
                    $type,
                    $title,
                    $message,
                    $data,
                    enforceLimits: false
                );
                $count++;
            }
        });

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
    public function notifyInstallmentCreated(User $user, \App\Models\Installment $installment): ?Notification
    {
        try {
            $installment->loadMissing('customer');
            $customerName = $installment->customer?->name ?? 'عميل';
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
                ],
                false // never block installment creation on notification limits
            );
        } catch (\Throwable $e) {
            Log::warning('notifyInstallmentCreated failed', [
                'installment_id' => $installment->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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

        if (! $notification->isRead()) {
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
