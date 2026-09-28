<?php

namespace App\Http\Middleware;

use App\Helpers\LimitsHelper;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates Internal Credit Score APIs behind the subscription feature flag
 * `internal_credit_score`. Owners always pass.
 */
class EnsureInternalCreditScoreFeature
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated');
        }

        if ($user->isOwner() || $user->isPlatformAdmin()) {
            return $next($request);
        }

        if (! LimitsHelper::canAccessFeature($user->id, 'internal_credit_score')) {
            abort(403, 'ميزة Internal Credit Score غير متاحة في خطتك الحالية.');
        }

        return $next($request);
    }
}
