<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Entry gate for the whole /admin panel — both super_admin and read-only support_admin may browse it. */
class EnsureAdminPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        if (!Auth::user()->isAdminStaff()) {
            abort(403, 'Forbidden: admin access only.');
        }

        return $next($request);
    }
}
