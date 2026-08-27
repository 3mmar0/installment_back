<?php

namespace App\Services;

use App\Mail\InstallmentCreated;
use App\Mail\PaymentDueReminder;
use App\Mail\PaymentOverdueNotice;
use App\Mail\PaymentReceivedConfirmation;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\User;
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
     * Send payment due reminders (exactly 2 days remaining).
     */
    public function sendPaymentDueReminders(User $user): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $twoDaysLater = now()->addDays(2)->endOfDay();
        $oneDayLater = now()->addDays(1)->startOfDay();

        $dueSoon = InstallmentItem::query()
            ->whereHas('installment', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 'active');
            })
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->whereBetween('due_date', [$oneDayLater, $twoDaysLater])
            ->with(['installment.customer'])
            ->get();

        $count = 0;
        foreach ($dueSoon as $item) {
            try {
                $customerEmail = $item->installment?->customer?->email;
                if (!$this->isValidEmail($customerEmail)) {
                    continue;
                }

                $daysRemaining = now()->diffInDays($item->due_date, false);

                Mail::to($customerEmail)
                    ->send(new PaymentDueReminder($item, $daysRemaining));

                if ($this->isValidEmail($user->email)) {
                    Mail::to($user->email)
                        ->send(new PaymentDueReminder($item, $daysRemaining));
                }

                $count++;
            } catch (\Throwable $e) {
                Log::error('Failed to send payment due reminder email', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Send overdue payment notices.
     */
    public function sendOverduePaymentNotices(User $user): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

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
            try {
                $customerEmail = $item->installment?->customer?->email;
                if (!$this->isValidEmail($customerEmail)) {
                    continue;
                }

                $daysOverdue = now()->diffInDays($item->due_date);

                Mail::to($customerEmail)
                    ->send(new PaymentOverdueNotice($item, $daysOverdue));

                if ($this->isValidEmail($user->email)) {
                    Mail::to($user->email)
                        ->send(new PaymentOverdueNotice($item, $daysOverdue));
                }

                $count++;
            } catch (\Throwable $e) {
                Log::error('Failed to send overdue payment notice email', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
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
     * Send due/overdue reminder email for one installment item.
     */
    public function sendItemReminderEmail(InstallmentItem $item, User $user): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $item->loadMissing(['installment.customer']);
        $customer = $item->installment?->customer;

        if (!$this->isValidEmail($customer?->email)) {
            return false;
        }

        try {
            if ($item->due_date < now()->startOfDay()) {
                $daysOverdue = now()->diffInDays($item->due_date);
                Mail::to($customer->email)->send(new PaymentOverdueNotice($item, $daysOverdue));
                if ($this->isValidEmail($user->email)) {
                    Mail::to($user->email)->send(new PaymentOverdueNotice($item, $daysOverdue));
                }
            } else {
                $daysRemaining = max(0, (int) now()->diffInDays($item->due_date, false));
                Mail::to($customer->email)->send(new PaymentDueReminder($item, $daysRemaining));
                if ($this->isValidEmail($user->email)) {
                    Mail::to($user->email)->send(new PaymentDueReminder($item, $daysRemaining));
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send installment item reminder email', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send all payment reminders for a user.
     */
    public function sendAllPaymentReminders(User $user): array
    {
        if (!$this->isEnabled()) {
            return [
                'due_reminders_sent' => 0,
                'overdue_notices_sent' => 0,
                'total_emails' => 0,
                'disabled' => true,
            ];
        }

        $dueReminders = $this->sendPaymentDueReminders($user);
        $overdueNotices = $this->sendOverduePaymentNotices($user);

        return [
            'due_reminders_sent' => $dueReminders,
            'overdue_notices_sent' => $overdueNotices,
            'total_emails' => $dueReminders + $overdueNotices,
        ];
    }
}
