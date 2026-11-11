<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** New users without a mailbox (and without imported emails) start at onboarding. */
class EnsureMailConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->mailAccounts()->doesntExist() && $user->rawEmails()->doesntExist()) {
            return redirect()->route('connect');
        }

        return $next($request);
    }
}
