<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOwner
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isOwner()) {
            abort(403);
        }
        return $next($request);
    }
}
