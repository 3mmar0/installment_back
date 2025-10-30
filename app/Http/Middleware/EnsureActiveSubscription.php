<?php

namespace App\Http\Middleware;

use App\Http\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        // Owners bypass subscription checks
        if (method_exists($user, 'isOwner') && $user->isOwner()) {
            return $next($request);
        }

        $subscription = $user->latestSubscription;
        if (!$subscription) {
            return $this->errorResponse('Subscription required', 402);
        }

        // Update computed status in-memory
        $subscription->refreshComputedStatus();

        // Validate active window and payment status
        $isActive = $subscription->status === 'active';
        $withinDates = !$subscription->ends_at || !$subscription->ends_at->isPast();

        if (!($isActive && $withinDates)) {
            return $this->errorResponse('Subscription inactive or expired', 402, [
                'subscription_status' => $subscription->status,
                'ends_at' => optional($subscription->ends_at)?->toISOString(),
            ]);
        }

        return $next($request);
    }
}
