<?php

namespace App\Http\Middleware;

use App\Helpers\LimitsHelper;
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

        if (!LimitsHelper::isSubscriptionActive($user->id)) {
            $info = LimitsHelper::getSubscriptionInfo($user->id);

            return $this->errorResponse('Subscription inactive or expired', 402, [
                'subscription_status' => $info['subscription']['status'] ?? 'inactive',
                'ends_at' => $info['subscription']['end_date'] ?? null,
            ]);
        }

        return $next($request);
    }
}
