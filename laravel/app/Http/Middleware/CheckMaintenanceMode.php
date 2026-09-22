<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → General → "Maintenance mode". Deliberately NOT applied to /admin/* (registered
 * after SetLocale in bootstrap/app.php's web() group, but bows out early for admin routes) so a
 * super admin can always reach the admin panel to turn it back off, and login/logout stay open
 * so an admin can sign in in the first place.
 */
class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::get('maintenance_mode', '0') !== '1') {
            return $next($request);
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            return $next($request);
        }

        if ($request->is('login') || $request->is('login/*') || $request->is('logout')) {
            return $next($request);
        }

        $messageEn = Setting::get('maintenance_message_en', '') ?: 'We are performing scheduled maintenance. Please check back shortly.';
        $messageAr = Setting::get('maintenance_message_ar', '') ?: 'نقوم حاليًا بأعمال صيانة مجدولة. يرجى المحاولة مرة أخرى بعد قليل.';

        return response()->view('site.maintenance', [
            'messageEn' => $messageEn,
            'messageAr' => $messageAr,
        ], 503);
    }
}
