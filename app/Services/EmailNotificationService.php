<?php

namespace App\Services;

use App\Mail\PaymentDueReminder;
use App\Mail\PaymentOverdueNotice;
use App\Mail\PaymentReceivedConfirmation;
use App\Models\InstallmentItem;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    /**
     * Send payment due reminders (exactly 2 days remaining).
     */
    public function sendPaymentDueReminders(User $user): int
    {
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
                $daysRemaining = now()->diffInDays($item->due_date, false);

                // Send to customer
                Mail::to($item->installment->customer->email)
                    ->send(new PaymentDueReminder($item, $daysRemaining));

                // Send to owner
                if ($user->email) {
                    Mail::to($user->email)
                        ->send(new PaymentDueReminder($item, $daysRemaining));
                }

                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to send payment due reminder email', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage()
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
                $daysOverdue = now()->diffInDays($item->due_date);

                // Send to customer
                Mail::to($item->installment->customer->email)
                    ->send(new PaymentOverdueNotice($item, $daysOverdue));

                // Send to owner
                if ($user->email) {
                    Mail::to($user->email)
                        ->send(new PaymentOverdueNotice($item, $daysOverdue));
                }

                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to send overdue payment notice email', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage()
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
        try {
            // Send to customer
            Mail::to($item->installment->customer->email)
                ->send(new PaymentReceivedConfirmation(
                    $item,
                    $paidAmount,
                    $item->installment->customer->email
                ));

            // Send to owner
            if ($user->email) {
                Mail::to($user->email)
                    ->send(new PaymentReceivedConfirmation(
                        $item,
                        $paidAmount,
                        $user->email
                    ));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send payment received confirmation email', [
                'item_id' => $item->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send all payment reminders for a user.
     */
    public function sendAllPaymentReminders(User $user): array
    {
        $dueReminders = $this->sendPaymentDueReminders($user);
        $overdueNotices = $this->sendOverduePaymentNotices($user);

        return [
            'due_reminders_sent' => $dueReminders,
            'overdue_notices_sent' => $overdueNotices,
            'total_emails' => $dueReminders + $overdueNotices,
        ];
    }
}
