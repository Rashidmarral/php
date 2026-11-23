<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Entry gate for the supplier/vendor portal (/supplier-portal) — a fully separate identity from company staff/admin. */
class EnsurePortalSupplier
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('supplier')->check()) {
            return redirect('/supplier-portal/login');
        }

        return $next($request);
    }
}
