<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Mirrors the original App\Core\Lang::boot(): ?lang=en|ar switches and persists in session; session value wins otherwise. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('lang', 'en');

        if (in_array($request->query('lang'), ['en', 'ar'], true)) {
            $locale = $request->query('lang');
            $request->session()->put('lang', $locale);
        }

        App::setLocale($locale);

        return $next($request);
    }
}
