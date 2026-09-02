<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Jobs\MarkOverdueInstallmentItemsJob;
use App\Models\ClientAccount;
use App\Models\InstallmentItem;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduledRemindersJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(NotificationService $notificationService): void
    {
        MarkOverdueInstallmentItemsJob::dispatchSync();

        User::query()
            ->where('role', UserRole::User)
            ->whereHas('installments', fn ($query) => $query->where('status', 'active'))
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    GenerateUserPaymentNotificationsJob::dispatch($user->id);
                    SendUserPaymentRemindersJob::dispatch($user->id);
                }
            });

        $this->notifyClientsDueSoon($notificationService);
    }

    private function notifyClientsDueSoon(NotificationService $notificationService): void
    {
        ClientAccount::query()
            ->whereNotNull('email_verified_at')
            ->whereHas('customers.installments', fn ($q) => $q->where('status', 'active'))
            ->chunkById(100, function ($clients) use ($notificationService) {
                foreach ($clients as $client) {
                    $customerIds = $client->customers()->pluck('id');
                    if ($customerIds->isEmpty()) {
                        continue;
                    }

                    $dueSoon = InstallmentItem::query()
                        ->whereHas('installment', function ($query) use ($customerIds) {
                            $query->whereIn('customer_id', $customerIds)
                                ->where('status', 'active');
                        })
                        ->whereNull('paid_at')
                        ->where('status', '!=', 'paid')
                        ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(3)->endOfDay()])
                        ->with(['installment.user'])
                        ->get();

                    foreach ($dueSoon as $item) {
                        $daysUntilDue = max(0, (int) now()->diffInDays($item->due_date, false));
                        $amountFormatted = number_format((float) $item->amount, 2).' ج.م';
                        $vendorName = $item->installment->user->name ?? 'البائع';

                        $notificationService->createForClient(
                            $client,
                            'payment_due',
                            'دفعة مستحقة قريباً',
                            "دفعة بقيمة {$amountFormatted} مستحقة خلال {$daysUntilDue} يوم لدى {$vendorName}",
                            [
                                'installment_id' => $item->installment_id,
                                'item_id' => $item->id,
                                'amount' => (float) $item->amount,
                                'due_date' => $item->due_date instanceof \DateTimeInterface
                                    ? $item->due_date->format('Y-m-d')
                                    : $item->due_date,
                                'days_until_due' => $daysUntilDue,
                                'vendor_name' => $vendorName,
                            ]
                        );
                    }
                }
            });
    }
}
