<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePlanRequest;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\SubscribeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function current(Request $request): JsonResponse
    {
        $user = Auth::user();
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->with('plan')
            ->first();

        if ($subscription) {
            $subscription->refreshComputedStatus();
        }

        return $this->successResponse($subscription, 'Current subscription retrieved');
    }

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $user = Auth::user();
        $planId = (int) ($request->validated()['plan_id'] ?? 0);
        $plan = Plan::active()->findOrFail($planId);

        $subscription = $this->createOrReplaceSubscription($user->id, $plan);

        return $this->createdResponse($subscription->load('plan'), 'Subscribed successfully');
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = Auth::user();
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due'])
            ->latest('id')
            ->first();

        if (!$subscription) {
            return $this->notFoundResponse('No active subscription to cancel');
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        return $this->successResponse($subscription, 'Subscription canceled');
    }

    public function changePlan(ChangePlanRequest $request): JsonResponse
    {
        $user = Auth::user();
        $planId = (int) ($request->validated()['plan_id'] ?? 0);
        $plan = Plan::active()->findOrFail($planId);

        $subscription = $this->createOrReplaceSubscription($user->id, $plan);

        return $this->successResponse($subscription->load('plan'), 'Plan changed successfully');
    }

    public function paymentsIndex(Request $request): JsonResponse
    {
        $user = Auth::user();
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (!$subscription) {
            return $this->successResponse([], 'No payments');
        }

        $payments = SubscriptionTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->orderByDesc('id')
            ->get();

        return $this->successResponse($payments, 'Payments retrieved');
    }

    public function recordPayment(RecordPaymentRequest $request): JsonResponse
    {
        $user = Auth::user();
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (!$subscription) {
            return $this->notFoundResponse('No subscription found');
        }

        $validated = $request->validated();
        $amount = (int) $validated['amount_cents'];
        $note = $validated['note'] ?? null;

        return DB::transaction(function () use ($subscription, $user, $amount, $note) {
            $txn = SubscriptionTransaction::create([
                'subscription_id' => $subscription->id,
                'amount_cents' => $amount,
                'type' => 'payment',
                'note' => $note,
                'recorded_by' => $user->id,
            ]);

            $subscription->paid_cents = (int) $subscription->paid_cents + $amount;
            $subscription->refreshComputedStatus();
            $subscription->save();

            return $this->successResponse([
                'transaction' => $txn,
                'subscription' => $subscription->fresh(),
            ], 'Payment recorded');
        });
    }

    protected function createOrReplaceSubscription(int $userId, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($userId, $plan) {
            Subscription::query()
                ->where('user_id', $userId)
                ->whereIn('status', ['active', 'past_due'])
                ->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

            $startsAt = now();
            $trialDays = (int) ($plan->trial_days ?? 0);
            if ($trialDays > 0) {
                $endsAt = (clone $startsAt)->addDays($trialDays);
            } else {
                $endsAt = $plan->interval === 'monthly'
                    ? (clone $startsAt)->addMonth()
                    : (clone $startsAt)->addYear();
            }

            return Subscription::create([
                'user_id' => $userId,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'next_due_at' => $endsAt,
                'amount_cents' => (int) $plan->price_cents,
                'paid_cents' => 0,
            ]);
        });
    }
}
