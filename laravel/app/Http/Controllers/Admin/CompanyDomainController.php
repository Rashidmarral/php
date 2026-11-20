<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin-only management of the custom domains a company's panel can also be reached on
 * (see the company_domains migration's docblock for the product shape). There is
 * deliberately no company-owner-facing equivalent of this controller — only a platform
 * admin assigns/activates a company's domain.
 *
 * All three actions here are admin.super-only (see routes/web.php), unlike the plain
 * admin.panel read access to the company page itself: pointing a domain at the wrong
 * company is a real takeover risk, so a read-only support_admin must never be able to
 * mutate this.
 */
class CompanyDomainController extends Controller
{
    /** Each label: 1–63 chars, letters/digits/hyphens, no leading/trailing hyphen. Overall host: 1–253 chars. */
    private const HOSTNAME_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    public function store(Request $request, int $companyId): RedirectResponse
    {
        $company = Company::findOrFail($companyId);

        $domain = $this->normalize((string) $request->input('domain'));
        $error = $this->validateDomain($domain);
        if ($error) {
            return $this->redirectWithFlash('/admin/companies/'.$company->id, 'error', $error);
        }

        CompanyDomain::create([
            'company_id' => $company->id,
            'domain' => $domain,
            'is_active' => true,
        ]);

        AuditLog::record($request->user(), 'company_domain_added', 'company', $company->id, "{$company->name} → {$domain}");

        return $this->redirectWithFlash('/admin/companies/'.$company->id, 'success', t('admin.company.domain_added'));
    }

    public function toggle(int $companyId, int $domainId): RedirectResponse
    {
        $company = Company::findOrFail($companyId);
        $domain = $this->findOrFail($company, $domainId);

        $domain->update(['is_active' => ! $domain->is_active]);

        AuditLog::record(auth()->user(), 'company_domain_toggled', 'company', $company->id, "{$company->name} → {$domain->domain} (".($domain->is_active ? 'active' : 'inactive').')');

        return $this->redirectWithFlash('/admin/companies/'.$company->id, 'success', t('admin.company.domain_updated'));
    }

    public function destroy(int $companyId, int $domainId): RedirectResponse
    {
        $company = Company::findOrFail($companyId);
        $domain = $this->findOrFail($company, $domainId);

        $removed = $domain->domain;
        $domain->delete();

        AuditLog::record(auth()->user(), 'company_domain_removed', 'company', $company->id, "{$company->name} → {$removed}");

        return $this->redirectWithFlash('/admin/companies/'.$company->id, 'success', t('admin.company.domain_removed'));
    }

    private function findOrFail(Company $company, int $domainId): CompanyDomain
    {
        $domain = CompanyDomain::where('company_id', $company->id)->where('id', $domainId)->first();
        abort_if(! $domain, 404, 'Domain not found.');

        return $domain;
    }

    private function normalize(string $domain): string
    {
        return strtolower(trim($domain));
    }

    /** Returns a translated error message, or null when $domain passes every check. */
    private function validateDomain(string $domain): ?string
    {
        if ($domain === '') {
            return t('admin.company.domain_required');
        }

        // Reject a scheme/path/port (http://, a trailing /path, a :port) or any whitespace
        // outright — the hostname regex below would already reject most of these too, but
        // this catches them with one check before it, for a clear single error message.
        if (preg_match('/[\s\/:]/', $domain) === 1) {
            return t('admin.company.domain_format_invalid');
        }

        if (preg_match(self::HOSTNAME_PATTERN, $domain) !== 1) {
            return t('admin.company.domain_format_invalid');
        }

        $platformHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        if ($platformHost !== '' && $domain === $platformHost) {
            return t('admin.company.domain_is_platform_host');
        }

        if (CompanyDomain::where('domain', $domain)->exists()) {
            return t('admin.company.domain_already_taken');
        }

        return null;
    }
}
