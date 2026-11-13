<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** New users without a mailbox (and without imported emails or manual transactions) start at onboarding. */
class EnsureMailConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->mailAccounts()->exists()) {
            return $next($request);
        }

        if ($user->rawEmails()->exists()) {
            return $next($request);
        }

        if ($user->transactions()->withTrashed()->exists()) {
            return $next($request);
        }

        return redirect()->route('connect');
    }
}
