<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\User;
use App\Support\DomainContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the admin-only custom-domain feature end to end: the company_domains CRUD
 * (admin.super-only, mirroring CompanyFeatureOverrideTest's own
 * super-admin-vs-support-admin split), the format/uniqueness/platform-host
 * validation in CompanyDomainController, and App\Http\Middleware\ResolveCompanyDomain's
 * resolution + marketing-bypass behavior.
 */
class CompanyDomainTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // The active-domain map is cached (see CompanyDomain::CACHE_KEY) — the array
        // cache store persists for the whole test process, so start every test with a
        // clean slate rather than risking a previous test's map leaking in.
        Cache::forget(CompanyDomain::CACHE_KEY);
    }

    private function makeCompany(string $tag): Company
    {
        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag).'@example.com',
        ]);
    }

    private function makeSuperAdmin(string $tag): User
    {
        return User::create([
            'name' => "Super Admin {$tag}",
            'email' => 'super-admin-'.strtolower($tag).'@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function makeSupportAdmin(string $tag): User
    {
        return User::create([
            'name' => "Support Admin {$tag}",
            'email' => 'support-admin-'.strtolower($tag).'@example.com',
            'password' => Hash::make('password'),
            'role' => 'support_admin',
            'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------------------
    // Admin CRUD
    // ---------------------------------------------------------------------

    public function test_super_admin_can_add_a_domain(): void
    {
        $company = $this->makeCompany('ADD');
        $admin = $this->makeSuperAdmin('ADD');

        $response = $this->actingAs($admin)->post("/admin/companies/{$company->id}/domains", [
            'domain' => 'App.AcmeAdd.com',
        ]);
        $response->assertRedirect("/admin/companies/{$company->id}");

        $domain = CompanyDomain::where('company_id', $company->id)->first();
        $this->assertNotNull($domain);
        $this->assertSame('app.acmeadd.com', $domain->domain, 'the domain must be lowercased before storing');
        $this->assertTrue($domain->is_active);

        $log = AuditLog::where('action', 'company_domain_added')->where('target_id', $company->id)->first();
        $this->assertNotNull($log);
    }

    public function test_super_admin_can_deactivate_and_reactivate_a_domain(): void
    {
        $company = $this->makeCompany('TOGGLE');
        $admin = $this->makeSuperAdmin('TOGGLE');
        $domain = CompanyDomain::create(['company_id' => $company->id, 'domain' => 'app.toggle.com', 'is_active' => true]);

        $this->actingAs($admin)
            ->post("/admin/companies/{$company->id}/domains/{$domain->id}/toggle")
            ->assertRedirect("/admin/companies/{$company->id}");
        $this->assertFalse($domain->refresh()->is_active);

        $this->actingAs($admin)
            ->post("/admin/companies/{$company->id}/domains/{$domain->id}/toggle")
            ->assertRedirect("/admin/companies/{$company->id}");
        $this->assertTrue($domain->refresh()->is_active);
    }

    public function test_super_admin_can_delete_a_domain(): void
    {
        $company = $this->makeCompany('DEL');
        $admin = $this->makeSuperAdmin('DEL');
        $domain = CompanyDomain::create(['company_id' => $company->id, 'domain' => 'app.deleteme.com', 'is_active' => true]);

        $this->actingAs($admin)
            ->post("/admin/companies/{$company->id}/domains/{$domain->id}/delete")
            ->assertRedirect("/admin/companies/{$company->id}");

        $this->assertNull(CompanyDomain::find($domain->id));
    }

    public function test_support_admin_cannot_add_toggle_or_delete_a_domain(): void
    {
        $company = $this->makeCompany('SUPPORT');
        $supportAdmin = $this->makeSupportAdmin('SUPPORT');
        $domain = CompanyDomain::create(['company_id' => $company->id, 'domain' => 'app.supportguard.com', 'is_active' => true]);

        $this->actingAs($supportAdmin)
            ->post("/admin/companies/{$company->id}/domains", ['domain' => 'app.shouldnotexist.com'])
            ->assertStatus(403);
        $this->assertSame(0, CompanyDomain::where('domain', 'app.shouldnotexist.com')->count());

        $this->actingAs($supportAdmin)
            ->post("/admin/companies/{$company->id}/domains/{$domain->id}/toggle")
            ->assertStatus(403);
        $this->assertTrue($domain->refresh()->is_active, 'a support_admin must never be able to toggle a domain');

        $this->actingAs($supportAdmin)
            ->post("/admin/companies/{$company->id}/domains/{$domain->id}/delete")
            ->assertStatus(403);
        $this->assertNotNull(CompanyDomain::find($domain->id), 'a support_admin must never be able to delete a domain');
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    public function test_adding_a_domain_already_used_by_another_company_is_rejected(): void
    {
        $companyA = $this->makeCompany('TAKENA');
        $companyB = $this->makeCompany('TAKENB');
        $admin = $this->makeSuperAdmin('TAKEN');
        CompanyDomain::create(['company_id' => $companyA->id, 'domain' => 'app.shared.com', 'is_active' => true]);

        $this->actingAs($admin)
            ->post("/admin/companies/{$companyB->id}/domains", ['domain' => 'app.shared.com'])
            ->assertRedirect("/admin/companies/{$companyB->id}");

        $this->assertSame(1, CompanyDomain::where('domain', 'app.shared.com')->count());
        $this->assertSame($companyA->id, CompanyDomain::where('domain', 'app.shared.com')->first()->company_id);
    }

    public function test_adding_a_malformed_domain_is_rejected(): void
    {
        $company = $this->makeCompany('BADFMT');
        $admin = $this->makeSuperAdmin('BADFMT');

        foreach (['http://app.example.com', 'app.example.com/path', 'app.example.com:8080', 'not a domain', '-leading-hyphen.com', 'nodot'] as $bad) {
            $this->actingAs($admin)->post("/admin/companies/{$company->id}/domains", ['domain' => $bad]);
        }

        $this->assertSame(0, $company->domains()->count(), 'none of these malformed values should have been stored');
    }

    public function test_adding_the_platforms_own_host_is_rejected(): void
    {
        $company = $this->makeCompany('PLATFORMHOST');
        $admin = $this->makeSuperAdmin('PLATFORMHOST');
        $platformHost = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($admin)
            ->post("/admin/companies/{$company->id}/domains", ['domain' => $platformHost])
            ->assertRedirect("/admin/companies/{$company->id}");

        $this->assertSame(0, CompanyDomain::where('domain', strtolower($platformHost))->count());
    }

    // ---------------------------------------------------------------------
    // ResolveCompanyDomain middleware
    // ---------------------------------------------------------------------

    public function test_middleware_resolves_the_company_for_a_matching_host_and_sets_the_domain_context(): void
    {
        $company = $this->makeCompany('RESOLVE');
        CompanyDomain::create(['company_id' => $company->id, 'domain' => 'resolve-me.test', 'is_active' => true]);

        // Not one of the marketing paths, and no auth check involved — just confirms
        // the resolution itself without the bypass redirect muddying the assertion.
        // Laravel's test get()/HTTP_HOST quirk: Symfony\Request::create() always lets the
        // URI's own host win over a passed HTTP_HOST server var, so the only reliable way
        // to drive $request->getHost() in a feature test is a full absolute URL here,
        // rather than a relative path plus an HTTP_HOST header (which gets overridden back
        // to APP_URL's host). The container's bound 'request' is the exact Request
        // instance the middleware ran against (the test runs in-process), so it reflects
        // what the middleware set.
        $this->get('http://resolve-me.test/p/does-not-exist');

        $this->assertSame($company->id, DomainContext::current()?->id);
    }

    public function test_middleware_is_a_no_op_for_a_host_that_matches_no_active_domain(): void
    {
        $company = $this->makeCompany('NOMATCH');
        CompanyDomain::create(['company_id' => $company->id, 'domain' => 'matched.test', 'is_active' => true]);

        $response = $this->get('http://totally-unrelated-host.test/');

        $response->assertOk();
        $this->assertNull(DomainContext::current());
    }

    public function test_middleware_ignores_an_inactive_domain_row(): void
    {
        $company = $this->makeCompany('INACTIVE');
        CompanyDomain::create(['company_id' => $company->id, 'domain' => 'inactive-domain.test', 'is_active' => false]);

        $response = $this->get('http://inactive-domain.test/');

        $response->assertOk();
        $this->assertNull(DomainContext::current());
    }

    public function test_marketing_bypass_redirects_an_unauthenticated_visit_to_login_on_a_resolved_domain(): void
    {
        $company = $this->makeCompany('BYPASS');
        CompanyDomain::create(['company_id' => $company->id, 'domain' => 'client-domain.test', 'is_active' => true]);

        $this->get('http://client-domain.test/')->assertRedirect('/login');
        $this->get('http://client-domain.test/pricing')->assertRedirect('/login');
    }

    public function test_marketing_bypass_does_not_apply_to_a_share_token_route(): void
    {
        $company = $this->makeCompany('SHAREBYPASS');
        CompanyDomain::create(['company_id' => $company->id, 'domain' => 'client-share.test', 'is_active' => true]);

        // An invalid/unknown token 404s inside ShareController — the important thing is
        // it is NOT redirected to /login the way a marketing page would be.
        $response = $this->get('http://client-share.test/e/not-a-real-token');

        $response->assertStatus(404);
    }

    public function test_marketing_bypass_does_not_apply_when_the_host_does_not_resolve(): void
    {
        // Sanity check: the platform's own host browsing its own marketing site is
        // completely unaffected — this is the ExampleTest-style baseline behavior.
        $response = $this->get('/pricing');

        $response->assertOk();
    }
}
