<?php

namespace App\Services;

use App\Helpers\LimitsHelper;
use App\Models\Customer;
use App\Models\InstallmentItem;
use App\Models\User;
use App\Models\WhatsAppDailyCharge;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppReminderService
{
    public function __construct(
        private readonly WhatsAppCloudApiService $whatsApp,
    ) {}

    /**
     * Send WhatsApp reminders for a merchant following a 3-touch sequence:
     *   • Day -2: items due exactly 2 days from now  → "بعد يومين"
     *   • Day  0: items due today (unpaid)            → "اليوم"
     *   • Day +2: items overdue by exactly 2 days     → "متأخر منذ يومين"
     *
     * Only customers with whatsapp_auto_reminders = true receive messages.
     *
     * @return array{customers: int, messages_sent: int, quota_charged: int, skipped: int}
     */
    public function sendForMerchant(User $user): array
    {
        $stats = [
            'customers' => 0,
            'messages_sent' => 0,
            'quota_charged' => 0,
            'skipped' => 0,
        ];

        if (! $user->receivesOperationalComms()) {
            return $stats;
        }

        if (! config('services.whatsapp.enabled')) {
            return $stats;
        }

        if (! $user->isOwner() && ! LimitsHelper::canAccessFeature($user->id, 'whatsapp')) {
            Log::info('whatsapp_reminders_skipped_feature', ['user_id' => $user->id]);

            return $stats;
        }

        $items = collect()
            ->merge($this->getItemsDueInDays($user, 2))   // Day -2
            ->merge($this->getItemsDueToday($user))        // Day  0
            ->merge($this->getItemsOverdueByDays($user, 2)); // Day +2

        $itemIds = $items->pluck('id')->unique()->filter()->values()->all();

        if ($itemIds === []) {
            return $stats;
        }

        $items = InstallmentItem::query()
            ->whereIn('id', $itemIds)
            ->with(['installment.customer'])
            ->get();

        $grouped = $items->groupBy(fn (InstallmentItem $item) => $item->installment->customer_id);

        foreach ($grouped as $customerItems) {
            /** @var Collection<int, InstallmentItem> $customerItems */
            $customer = $customerItems->first()?->installment?->customer;
            if (! $customer instanceof Customer) {
                $stats['skipped']++;

                continue;
            }

            if (! $customer->whatsapp_auto_reminders) {
                $stats['skipped']++;

                continue;
            }

            $phone = $customer->phone ?: $customer->phone_normalized;
            if (! is_string($phone) || trim($phone) === '') {
                $stats['skipped']++;
                Log::info('whatsapp_reminders_skipped_no_phone', [
                    'user_id' => $user->id,
                    'customer_id' => $customer->id,
                ]);

                continue;
            }

            if (! $user->isOwner() && ! LimitsHelper::canCreate($user->id, 'whatsapp_messages')) {
                $stats['skipped']++;
                Log::info('whatsapp_reminders_skipped_merchant_quota', [
                    'user_id' => $user->id,
                    'customer_id' => $customer->id,
                ]);

                break;
            }

            if (! PlatformWhatsAppQuota::hasRemaining()) {
                $stats['skipped']++;
                Log::warning('whatsapp_reminders_skipped_platform_quota', [
                    'user_id' => $user->id,
                    'customer_id' => $customer->id,
                ]);

                break;
            }

            $sent = 0;
            foreach ($customerItems as $item) {
                $this->paceBeforeNextSend($sent > 0 || $stats['messages_sent'] > 0);

                try {
                    $body = $this->buildMessage($customer, $item);
                    $this->whatsApp->sendText($phone, $body, $user, consumeMerchantQuota: false);
                    $sent++;
                } catch (Throwable $e) {
                    Log::error('whatsapp_reminder_send_failed', [
                        'user_id' => $user->id,
                        'customer_id' => $customer->id,
                        'item_id' => $item->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($sent === 0) {
                continue;
            }

            $stats['customers']++;
            $stats['messages_sent'] += $sent;

            if ($this->chargeMerchantOncePerDay($user, $customer, $sent)) {
                $stats['quota_charged']++;
            }
        }

        return $stats;
    }

    private function paceBeforeNextSend(bool $needsDelay): void
    {
        if (! $needsDelay) {
            return;
        }

        $seconds = (float) config('services.whatsapp.send_delay_seconds', 2.5);
        if ($seconds <= 0) {
            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }

    private function chargeMerchantOncePerDay(User $user, Customer $customer, int $messagesSent): bool
    {
        if ($user->isOwner()) {
            return false;
        }

        $today = now()->toDateString();
        $existing = WhatsAppDailyCharge::query()
            ->where('user_id', $user->id)
            ->where('customer_id', $customer->id)
            ->whereDate('charge_date', $today)
            ->first();

        if ($existing) {
            $existing->increment('messages_sent', $messagesSent);

            return false;
        }

        WhatsAppDailyCharge::query()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'charge_date' => $today,
            'messages_sent' => $messagesSent,
        ]);

        LimitsHelper::incrementUsage($user->id, 'whatsapp_messages');

        return true;
    }

    private function buildMessage(Customer $customer, InstallmentItem $item): string
    {
        $amount = number_format((float) $item->amount, 2);
        $due = $item->due_date instanceof \DateTimeInterface
            ? $item->due_date->format('Y-m-d')
            : (string) $item->due_date;

        $dueDate = $item->due_date instanceof Carbon
            ? $item->due_date
            : Carbon::parse($item->due_date);

        $diffDays = (int) now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);

        if ($diffDays > 0) {
            $prefix = "تذكير: قسط مستحق بعد {$diffDays} " . ($diffDays === 1 ? 'يوم' : 'يومين');
        } elseif ($diffDays === 0) {
            $prefix = 'تذكير: قسط مستحق اليوم';
        } else {
            $daysPast = abs($diffDays);
            $prefix = "تذكير: قسط متأخر منذ {$daysPast} " . ($daysPast === 1 ? 'يوم' : 'يومين');
        }

        return "{$prefix} للعميل {$customer->name}. المبلغ {$amount} ج.م، تاريخ الاستحقاق {$due}.";
    }

    /**
     * Items due exactly $days days from today (unpaid).
     *
     * @return Collection<int, InstallmentItem>
     */
    private function getItemsDueInDays(User $user, int $days): Collection
    {
        $target = now()->addDays($days);

        return InstallmentItem::query()
            ->whereHas('installment', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->whereBetween('due_date', [$target->copy()->startOfDay(), $target->copy()->endOfDay()])
            ->with(['installment.customer'])
            ->get();
    }

    /**
     * Items due today (unpaid).
     *
     * @return Collection<int, InstallmentItem>
     */
    private function getItemsDueToday(User $user): Collection
    {
        return InstallmentItem::query()
            ->whereHas('installment', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->whereBetween('due_date', [now()->startOfDay(), now()->endOfDay()])
            ->with(['installment.customer'])
            ->get();
    }

    /**
     * Items overdue by exactly $days days (unpaid).
     *
     * @return Collection<int, InstallmentItem>
     */
    private function getItemsOverdueByDays(User $user, int $days): Collection
    {
        $target = now()->subDays($days);

        return InstallmentItem::query()
            ->whereHas('installment', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->whereBetween('due_date', [$target->copy()->startOfDay(), $target->copy()->endOfDay()])
            ->with(['installment.customer'])
            ->get();
    }
}
