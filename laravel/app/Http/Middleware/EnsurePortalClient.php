<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Entry gate for the client portal (/portal) — a fully separate identity from company staff/admin. */
class EnsurePortalClient
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('client')->check()) {
            return redirect('/portal/login');
        }

        return $next($request);
    }
}
