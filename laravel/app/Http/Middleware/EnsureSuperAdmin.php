<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        if (!$user->isSuperAdmin()) {
            if ($user->isSupportAdmin()) {
                abort(403, 'Forbidden: your admin account is read-only. Ask a super admin to make this change.');
            }
            abort(403, 'Forbidden: admin access only.');
        }

        return $next($request);
    }
}
