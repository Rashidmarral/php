<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the visitor's Host header against the ~10 admin-managed `company_domains`
 * rows (see that migration's docblock) and, when it matches an active one, makes the
 * company available to the rest of the request. This middleware never changes what a
 * matched request is ALLOWED to do — no data scoping, no auth bypass — it only:
 *
 *   (a) stashes the resolved Company on the request (and via DomainContext::current())
 *       so the login page / public layout header can show that company's own branding
 *       instead of the generic platform branding, and
 *   (b) redirects an unauthenticated visitor away from the generic marketing site to
 *       /login, since someone hitting a client's own domain wants that client's login
 *       page, not the platform's sales pitch.
 *
 * The platform's own URL (and any host that isn't an active company_domains row — the
 * normal case before a client's domain is configured) matches nothing here and this
 * middleware is a complete no-op for it, exactly as before this feature existed.
 *
 * Registered first in the web group (bootstrap/app.php), ahead of SetLocale/
 * CheckMaintenanceMode, so any of their downstream decisions can see the resolved
 * company too if they ever need to.
 */
class ResolveCompanyDomain
{
    /**
     * Public marketing/content routes that exist to sell the platform itself. An
     * unauthenticated visitor whose Host resolved to a company is redirected to /login
     * instead of seeing these. Deliberately NOT included: anything a visitor might
     * legitimately need regardless of whose domain they're on — share-token links
     * (e/, ipc/, co/, rfq-bid/, i/), the public quick-estimate calculator (a real tool,
     * not marketing copy), the admin panel, the app panel, and the API.
     *
     * @var list<string>
     */
    private const MARKETING_PATHS = ['features', 'pricing', 'about', 'contact', 'privacy', 'terms', 'support', 'security'];

    public function handle(Request $request, Closure $next): Response
    {
        $company = $this->resolveCompany($request);

        if (! $company) {
            return $next($request);
        }

        $request->attributes->set('resolvedDomainCompany', $company);

        if (! Auth::check() && $request->isMethod('GET') && $this->isMarketingPath($request->path())) {
            return redirect('/login');
        }

        return $next($request);
    }

    private function resolveCompany(Request $request): ?Company
    {
        $host = strtolower($request->getHost());

        try {
            $map = Cache::remember(
                CompanyDomain::CACHE_KEY,
                now()->addMinutes(10),
                fn () => CompanyDomain::where('is_active', true)->pluck('company_id', 'domain'),
            );
        } catch (\Throwable $e) {
            Log::error('ResolveCompanyDomain: failed to load the company_domains map, treating as no match.', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        $companyId = $map[$host] ?? null;

        return $companyId ? Company::find($companyId) : null;
    }

    private function isMarketingPath(string $path): bool
    {
        $path = trim($path, '/');

        if ($path === '') {
            return true;
        }

        if (in_array($path, self::MARKETING_PATHS, true)) {
            return true;
        }

        return str_starts_with($path, 'p/');
    }
}
