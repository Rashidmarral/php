<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors the original App\Core\Lang::boot(): ?lang=en|ar switches and persists in session;
 * session value wins otherwise. A visitor who has never picked a language (no session value yet)
 * gets the admin's Settings → General → Default Language instead of a hardcoded 'en' — this is
 * the one place that setting actually takes effect, not just a cosmetic field.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('lang');
        if (!$locale) {
            $locale = Setting::get('default_language', 'en') === 'ar' ? 'ar' : 'en';
        }

        if (in_array($request->query('lang'), ['en', 'ar'], true)) {
            $locale = $request->query('lang');
            $request->session()->put('lang', $locale);
        }

        App::setLocale($locale);

        return $next($request);
    }
}
