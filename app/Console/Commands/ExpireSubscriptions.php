<?php

namespace App\Console\Commands;

use App\Helpers\LimitsHelper;
use App\Models\Subscription;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionEvent;
use App\Models\UserLimit;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire subscriptions, auto-renew free plans, and downgrade expired paid plans to free';

    public function handle(): int
    {
        $expired = SubscriptionAssignment::query()
            ->where('status', 'active')
            ->whereDate('end_date', '<', today())
            ->get();

        $expiredCount = 0;
        $renewedCount = 0;
        $downgradedCount = 0;

        $service = app(SubscriptionEntitlementService::class);

        foreach ($expired as $assignment) {
            DB::transaction(function () use ($assignment, $service, &$expiredCount, &$renewedCount, &$downgradedCount) {
                $assignment->refresh();
                if ($assignment->status !== 'active' || ! $assignment->end_date?->isBefore(today())) {
                    return;
                }

                $assignment->update(['status' => 'expired', 'ended_reason' => 'expired']);
                UserLimit::query()->where('subscription_assignment_id', $assignment->id)->update(['status' => 'expired']);
                SubscriptionEvent::create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $assignment->user_id,
                    'type' => 'expired',
                    'payload' => ['end_date' => $assignment->end_date->toDateString()],
                ]);
                $expiredCount++;

                $subscription = $assignment->subscription;
                if (! $subscription) {
                    return;
                }

                $isFree = (float) $subscription->price <= 0;

                if ($isFree) {
                    // Auto-renew free plan for the same period
                    $service->activate($subscription, $assignment->user_id, 'auto_renew');
                    $renewedCount++;
                } else {
                    // Downgrade paid plan to free
                    $freePlan = LimitsHelper::ensureFreePlan();
                    $service->activate($freePlan, $assignment->user_id, 'auto_downgrade');
                    $downgradedCount++;
                }
            });
        }

        $this->info("Expired {$expiredCount} assignment(s). Renewed {$renewedCount} free plan(s). Downgraded {$downgradedCount} paid plan(s) to free.");
        return self::SUCCESS;
    }
}
