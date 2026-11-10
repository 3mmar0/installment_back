<?php

namespace App\Services;

use App\Helpers\LimitsHelper;
use App\Models\Subscription;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPurchaseRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionEntitlementService
{
    /** @return array<string, mixed> */
    public function snapshot(Subscription $subscription): array
    {
        return [
            'subscription_id' => $subscription->id,
            'name' => $subscription->name,
            'slug' => $subscription->slug,
            'currency' => $subscription->currency,
            'price' => (float) $subscription->price,
            'duration' => $subscription->duration,
            'description' => $subscription->description,
            'customers' => $subscription->customers,
            'installments' => $subscription->installments,
            'notifications' => $subscription->notifications,
            'assistant_messages' => $subscription->assistant_messages,
            'whatsapp_messages' => $subscription->whatsapp_messages,
            'reports' => $subscription->reports,
            'features' => $subscription->features ?? [],
        ];
    }

    public function activate(Subscription $subscription, int $userId, string $source, ?int $actorId = null, ?SubscriptionPurchaseRequest $purchase = null, ?Carbon $endDate = null, array $featureOverrides = []): SubscriptionAssignment
    {
        return DB::transaction(function () use ($subscription, $userId, $source, $actorId, $purchase, $endDate, $featureOverrides) {
            $snapshot = $purchase?->plan_snapshot ?: $this->snapshot($subscription);
            $now = now();
            $existing = SubscriptionAssignment::query()->where('user_id', $userId)->where('status', 'active')->lockForUpdate()->get();

            foreach ($existing as $assignment) {
                $assignment->update(['status' => 'superseded', 'end_date' => $now->toDateString(), 'ended_reason' => 'replaced']);
            }

            $features = array_merge($snapshot['features'] ?? [], $featureOverrides);
            $startsAt = $now->copy()->startOfDay();
            $endsAt = $endDate ?: $this->endDateFor($snapshot, $startsAt);
            $assignment = SubscriptionAssignment::create([
                'user_id' => $userId,
                'subscription_id' => $subscription->id,
                'start_date' => $startsAt,
                'end_date' => $endsAt,
                'status' => 'active',
                'source' => $source,
                'snapshot' => $snapshot,
                'features' => $features,
                'activated_by' => $actorId,
            ]);

            LimitsHelper::createOrUpdateUserLimits($userId, [
                'subscription_id' => $subscription->id,
                'subscription_assignment_id' => $assignment->id,
                'subscription_name' => $snapshot['name'], 'subscription_slug' => $snapshot['slug'],
                'currency' => $snapshot['currency'], 'price' => $snapshot['price'],
                'duration' => $snapshot['duration'], 'description' => $snapshot['description'],
                'customers' => $snapshot['customers'], 'installments' => $snapshot['installments'],
                'notifications' => $snapshot['notifications'], 'assistant_messages' => $snapshot['assistant_messages'],
                'whatsapp_messages' => $snapshot['whatsapp_messages'], 'reports' => $snapshot['reports'],
                'features' => $features, 'start_date' => $startsAt, 'end_date' => $endsAt, 'status' => 'active',
            ]);

            SubscriptionEvent::create(['assignment_id' => $assignment->id, 'user_id' => $userId, 'purchase_request_id' => $purchase?->id, 'type' => 'activated', 'actor_id' => $actorId, 'payload' => ['source' => $source, 'snapshot' => $snapshot]]);

            return $assignment;
        });
    }

    private function endDateFor(array $snapshot, Carbon $startsAt): ?Carbon
    {
        if ((float) ($snapshot['price'] ?? 0) <= 0) return null;
        return ($snapshot['duration'] ?? 'monthly') === 'yearly' ? $startsAt->copy()->addYear() : $startsAt->copy()->addMonth();
    }
}
