<?php

namespace App\Services;

use App\Jobs\SendCustomerReminderEmailsJob;
use App\Mail\ClientAppInviteMail;
use App\Mail\InstallmentCreated;
use App\Mail\PaymentDueReminderBatch;
use App\Mail\PaymentOverdueNoticeBatch;
use App\Mail\PaymentReceivedConfirmation;
use App\Models\ClientAccount;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    /**
     * Business emails are off by default so mail misconfig never breaks API logic.
     * Enable with MAIL_NOTIFICATIONS_ENABLED=true when mail is ready.
     */
    protected function isEnabled(): bool
    {
        return (bool) config('mail.notifications_enabled', false);
    }

    protected function isValidEmail(?string $email): bool
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @return Builder<InstallmentItem>
     */
    protected function unpaidItemsQuery(User $user, ?int $customerId = null): Builder
    {
        return InstallmentItem::query()
            ->whereHas('installment', function ($query) use ($user, $customerId) {
                $query->where('user_id', $user->id)
                    ->where('status', 'active');

                if ($customerId !== null) {
                    $query->where('customer_id', $customerId);
                }
            })
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->with(['installment.customer']);
    }

    /**
     * @return Collection<int, InstallmentItem>
     */
    protected function getDueSoonItems(User $user, ?int $customerId = null): Collection
    {
        $twoDaysLater = now()->addDays(2)->endOfDay();
        $oneDayLater = now()->addDays(1)->startOfDay();

        return $this->unpaidItemsQuery($user, $customerId)
            ->whereBetween('due_date', [$oneDayLater, $twoDaysLater])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * @return Collection<int, InstallmentItem>
     */
    protected function getOverdueItems(User $user, ?int $customerId = null): Collection
    {
        return $this->unpaidItemsQuery($user, $customerId)
            ->where('due_date', '<', now()->startOfDay())
            ->orderBy('due_date')
            ->get();
    }

    /**
     * @param  Collection<int, InstallmentItem>  $items
     */
    protected function sendDueSoonBatch(Customer $customer, Collection $items): bool
    {
        if ($items->isEmpty() || !$this->isValidEmail($customer->email)) {
            return false;
        }

        try {
            Mail::to($customer->email)->send(new PaymentDueReminderBatch($customer, $items));

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send consolidated due-soon reminder email', [
                'customer_id' => $customer->id,
                'items_count' => $items->count(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  Collection<int, InstallmentItem>  $items
     */
    protected function sendOverdueBatch(Customer $customer, Collection $items): bool
    {
        if ($items->isEmpty() || !$this->isValidEmail($customer->email)) {
            return false;
        }

        try {
            Mail::to($customer->email)->send(new PaymentOverdueNoticeBatch($customer, $items));

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send consolidated overdue notice email', [
                'customer_id' => $customer->id,
                'items_count' => $items->count(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  Collection<int, InstallmentItem>  $dueSoonItems
     * @param  Collection<int, InstallmentItem>  $overdueItems
     * @return array{due_reminders_sent: int, overdue_notices_sent: int, total_emails: int, items_included: int}
     */
    protected function sendConsolidatedReminders(
        Collection $dueSoonItems,
        Collection $overdueItems
    ): array {
        $dueRemindersSent = 0;
        $overdueNoticesSent = 0;

        foreach ($dueSoonItems->groupBy(fn (InstallmentItem $item) => $item->installment->customer_id) as $items) {
            /** @var Collection<int, InstallmentItem> $items */
            $customer = $items->first()?->installment?->customer;

            if ($customer && $this->sendDueSoonBatch($customer, $items)) {
                $dueRemindersSent++;
            }
        }

        foreach ($overdueItems->groupBy(fn (InstallmentItem $item) => $item->installment->customer_id) as $items) {
            /** @var Collection<int, InstallmentItem> $items */
            $customer = $items->first()?->installment?->customer;

            if ($customer && $this->sendOverdueBatch($customer, $items)) {
                $overdueNoticesSent++;
            }
        }

        return [
            'due_reminders_sent' => $dueRemindersSent,
            'overdue_notices_sent' => $overdueNoticesSent,
            'total_emails' => $dueRemindersSent + $overdueNoticesSent,
            'items_included' => $dueSoonItems->count() + $overdueItems->count(),
        ];
    }

    /**
     * Send payment due reminders grouped by customer (one email per customer).
     */
    public function sendPaymentDueReminders(User $user): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $result = $this->sendConsolidatedReminders(
            $this->getDueSoonItems($user),
            collect()
        );

        return $result['due_reminders_sent'];
    }

    /**
     * Send overdue payment notices grouped by customer (one email per customer).
     */
    public function sendOverduePaymentNotices(User $user): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $result = $this->sendConsolidatedReminders(
            collect(),
            $this->getOverdueItems($user)
        );

        return $result['overdue_notices_sent'];
    }

    /**
     * Send consolidated reminder emails for one customer.
     *
     * @return array{due_reminders_sent: int, overdue_notices_sent: int, total_emails: int, items_included: int, disabled?: bool}
     */
    public function sendCustomerPaymentReminders(Customer $customer, User $user): array
    {
        if (!$this->isEnabled()) {
            return [
                'due_reminders_sent' => 0,
                'overdue_notices_sent' => 0,
                'total_emails' => 0,
                'items_included' => 0,
                'disabled' => true,
            ];
        }

        if (!$user->isOwner() && $customer->user_id !== $user->id) {
            abort(403, 'غير مصرح لك بإرسال تذكير لهذا العميل');
        }

        return $this->sendConsolidatedReminders(
            $this->getDueSoonItems($user, $customer->id),
            $this->getOverdueItems($user, $customer->id)
        );
    }

    /**
     * Send consolidated reminders for specific installment items (customer only).
     *
     * @param  Collection<int, InstallmentItem>  $items
     * @return array{due_reminders_sent: int, overdue_notices_sent: int, total_emails: int, items_included: int}
     */
    public function sendItemsReminderEmails(Collection $items): array
    {
        if (!$this->isEnabled() || $items->isEmpty()) {
            return [
                'due_reminders_sent' => 0,
                'overdue_notices_sent' => 0,
                'total_emails' => 0,
                'items_included' => 0,
            ];
        }

        $items->loadMissing(['installment.customer']);

        $overdue = $items->filter(
            fn (InstallmentItem $item) => $item->due_date < now()->startOfDay()
        )->values();

        $dueSoon = $items->filter(
            fn (InstallmentItem $item) => $item->due_date >= now()->startOfDay()
        )->values();

        return $this->sendConsolidatedReminders($dueSoon, $overdue);
    }

    /**
     * Send payment received confirmation.
     */
    public function sendPaymentReceivedConfirmation(InstallmentItem $item, float $paidAmount, User $user): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $item->loadMissing('installment.customer');
            $customerEmail = $item->installment?->customer?->email;

            if ($this->isValidEmail($customerEmail)) {
                Mail::to($customerEmail)
                    ->send(new PaymentReceivedConfirmation(
                        $item,
                        $paidAmount,
                        $customerEmail
                    ));
            }

            if ($this->isValidEmail($user->email)) {
                Mail::to($user->email)
                    ->send(new PaymentReceivedConfirmation(
                        $item,
                        $paidAmount,
                        $user->email
                    ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send payment received confirmation email', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send installment created notification to customer and owner.
     */
    public function sendInstallmentCreatedNotification(Installment $installment, User $user): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $installment->loadMissing('customer');
            $customerEmail = $installment->customer?->email;

            if ($this->isValidEmail($customerEmail)) {
                Mail::to($customerEmail)
                    ->send(new InstallmentCreated(
                        $installment,
                        $customerEmail
                    ));
            }

            if ($this->isValidEmail($user->email)) {
                Mail::to($user->email)
                    ->send(new InstallmentCreated(
                        $installment,
                        $user->email
                    ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send installment created email', [
                'installment_id' => $installment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invite the customer to install the app / open the client portal when
     * they have an email but no client portal account yet.
     */
    public function sendClientAppInviteIfNeeded(Installment $installment, User $vendor): void
    {
        try {
            $installment->loadMissing('customer');
            $customer = $installment->customer;

            if (! $customer || ! $this->isValidEmail($customer->email)) {
                return;
            }

            if ($customer->client_account_id) {
                return;
            }

            $email = strtolower(trim((string) $customer->email));

            $hasClientAccount = ClientAccount::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->exists();

            if ($hasClientAccount) {
                return;
            }

            Mail::to($customer->email)->send(
                new ClientAppInviteMail($customer, $installment, $vendor)
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send client app invite email', [
                'installment_id' => $installment->id,
                'customer_id' => $installment->customer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @deprecated Use sendItemsReminderEmails() for consolidated customer emails.
     */
    public function sendItemReminderEmail(InstallmentItem $item, User $user): bool
    {
        $result = $this->sendItemsReminderEmails(collect([$item]));

        return $result['total_emails'] > 0;
    }

    /**
     * Send all payment reminders for a user (consolidated per customer).
     */
    public function sendAllPaymentReminders(User $user): array
    {
        if (!$this->isEnabled()) {
            return [
                'due_reminders_sent' => 0,
                'overdue_notices_sent' => 0,
                'total_emails' => 0,
                'items_included' => 0,
                'disabled' => true,
            ];
        }

        return $this->sendConsolidatedReminders(
            $this->getDueSoonItems($user),
            $this->getOverdueItems($user)
        );
    }

    /**
     * Preview consolidated emails for a specific set of installment items.
     *
     * @param  Collection<int, InstallmentItem>  $items
     * @return array{due_reminders_sent: int, overdue_notices_sent: int, total_emails: int, items_included: int}
     */
    public function buildReminderPreviewForItems(Collection $items): array
    {
        $items->loadMissing(['installment.customer']);

        $overdue = $items->filter(
            fn (InstallmentItem $item) => $item->due_date < now()->startOfDay()
        )->values();

        $dueSoon = $items->filter(
            fn (InstallmentItem $item) => $item->due_date >= now()->startOfDay()
        )->values();

        return $this->buildReminderPreview($dueSoon, $overdue);
    }

    /**
     * @param  Collection<int, InstallmentItem>  $dueSoonItems
     * @param  Collection<int, InstallmentItem>  $overdueItems
     * @return array{due_reminders_sent: int, overdue_notices_sent: int, total_emails: int, items_included: int}
     */
    protected function buildReminderPreview(Collection $dueSoonItems, Collection $overdueItems): array
    {
        $dueReminders = $dueSoonItems
            ->groupBy(fn (InstallmentItem $item) => $item->installment->customer_id)
            ->count();

        $overdueNotices = $overdueItems
            ->groupBy(fn (InstallmentItem $item) => $item->installment->customer_id)
            ->count();

        return [
            'due_reminders_sent' => $dueReminders,
            'overdue_notices_sent' => $overdueNotices,
            'total_emails' => $dueReminders + $overdueNotices,
            'items_included' => $dueSoonItems->count() + $overdueItems->count(),
        ];
    }

    /**
     * Queue consolidated reminder emails per customer (used by jobs and schedulers).
     */
    public function dispatchPaymentReminders(User $user, ?int $customerId = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $dueSoon = $this->getDueSoonItems($user, $customerId);
        $overdue = $this->getOverdueItems($user, $customerId);

        $customerIds = $dueSoon
            ->pluck('installment.customer_id')
            ->merge($overdue->pluck('installment.customer_id'))
            ->unique()
            ->filter();

        foreach ($customerIds as $id) {
            SendCustomerReminderEmailsJob::dispatch($user->id, (int) $id);
        }
    }

    /**
     * Queue all payment reminder emails for a user and return a delivery preview.
     *
     * @return array{due_reminders_sent: int, overdue_notices_sent: int, total_emails: int, items_included: int, queued: bool, disabled?: bool}
     */
    public function queueAllPaymentReminders(User $user): array
    {
        if (!$this->isEnabled()) {
            return [
                'due_reminders_sent' => 0,
                'overdue_notices_sent' => 0,
                'total_emails' => 0,
                'items_included' => 0,
                'queued' => false,
                'disabled' => true,
            ];
        }

        $preview = $this->buildReminderPreview(
            $this->getDueSoonItems($user),
            $this->getOverdueItems($user)
        );

        if ($preview['total_emails'] === 0) {
            return array_merge($preview, ['queued' => false]);
        }

        $this->dispatchPaymentReminders($user);

        return array_merge($preview, ['queued' => true]);
    }

    /**
     * Queue consolidated reminder emails for one customer and return a delivery preview.
     *
     * @return array{due_reminders_sent: int, overdue_notices_sent: int, total_emails: int, items_included: int, queued: bool, disabled?: bool}
     */
    public function queueCustomerPaymentReminders(Customer $customer, User $user): array
    {
        if (!$this->isEnabled()) {
            return [
                'due_reminders_sent' => 0,
                'overdue_notices_sent' => 0,
                'total_emails' => 0,
                'items_included' => 0,
                'queued' => false,
                'disabled' => true,
            ];
        }

        if (!$user->isOwner() && $customer->user_id !== $user->id) {
            abort(403, 'غير مصرح لك بإرسال تذكير لهذا العميل');
        }

        $preview = $this->buildReminderPreview(
            $this->getDueSoonItems($user, $customer->id),
            $this->getOverdueItems($user, $customer->id)
        );

        if ($preview['total_emails'] === 0) {
            return array_merge($preview, ['queued' => false]);
        }

        SendCustomerReminderEmailsJob::dispatch($user->id, $customer->id);

        return array_merge($preview, ['queued' => true]);
    }
}
