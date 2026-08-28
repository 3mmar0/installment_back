<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isPlatformAdmin()) {
            abort(403, 'Platform admin access required');
        }

        return $next($request);
    }
}
