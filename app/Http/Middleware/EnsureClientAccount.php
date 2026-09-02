<?php

namespace App\Http\Middleware;

use App\Models\ClientAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof ClientAccount) {
            abort(403, 'هذا المسار مخصص لحسابات العملاء فقط');
        }

        if (! $user->isEmailVerified()) {
            abort(403, 'يرجى تأكيد البريد الإلكتروني أولاً');
        }

        return $next($request);
    }
}
