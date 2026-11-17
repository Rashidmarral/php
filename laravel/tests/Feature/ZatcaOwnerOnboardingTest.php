<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use ReflectionClass;
use Tests\TestCase;

/**
 * Covers the new company-owner self-service ZATCA Phase 2 onboarding path
 * (App\Http\Controllers\App\ZatcaOnboardingController, mounted under
 * /app/settings/zatca) — a parallel entry point to the pre-existing
 * admin-only Admin\CompanyZatcaController, which this test suite does not
 * touch. Covers the same two gates ZatcaUserLogUiTest already covers for
 * the sibling ZatcaDashboardController ('manage_company_settings' ability,
 * 'zatca_phase2' plan feature) across every one of this controller's
 * routes, plus a real end-to-end CSR generation and the structural
 * guarantee that the controller can never read another company's data
 * (it has no {id} route parameter to tamper with — it only ever resolves
 * the company from Auth::user()->company_id).
 */
class ZatcaOwnerOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * App\Support\Feature caches the resolved plan/flags in static
     * properties across calls within the same PHP process — reset them
     * between tests here (same pattern ZatcaUserLogUiTest/
     * CompanyFeatureOverrideTest already use) so one test's Auth::user()
     * doesn't leak into the next assertion's Feature::allows() check.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function companyWithZatcaPhase2(string $tag, array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => "Zatca Owner {$tag} Co",
            'email' => 'zatca-owner-'.strtolower($tag).'-'.uniqid().'@example.com',
            'vat_number' => '300000000000003',
            'feature_overrides' => ['zatca_phase2' => true],
        ], $overrides));
    }

    private function user(Company $company, string $tag, string $role = 'owner'): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => ucfirst($role)." {$tag}",
            'email' => strtolower($role).'-zatca-owner-'.strtolower($tag).'-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    /** (1) An owner on a plan WITH zatca_phase2 can reach the self-service onboarding page. */
    public function test_owner_with_feature_can_view_the_onboarding_page(): void
    {
        $company = $this->companyWithZatcaPhase2('A');
        $owner = $this->user($company, 'A');

        $response = $this->actingAs($owner)->get('/app/settings/zatca');

        $response->assertOk();
        $response->assertSee($company->name, false);
    }

    /** (1b) ...and can generate a real CSR, which persists the key material and flips the status, same as the admin path does. */
    public function test_owner_with_feature_can_generate_a_csr(): void
    {
        $company = $this->companyWithZatcaPhase2('B');
        $owner = $this->user($company, 'B');

        $response = $this->actingAs($owner)->post('/app/settings/zatca/csr');

        $response->assertRedirect('/app/settings/zatca');

        $company->refresh();
        $this->assertNotEmpty($company->zatca_csr);
        $this->assertNotEmpty($company->zatca_private_key);
        $this->assertSame('csr_generated', $company->zatca_status);
        $this->assertNull($company->zatca_last_error);
    }

    /**
     * (2) An owner whose plan has no zatca_phase2 feature is redirected to
     * Billing on every one of this controller's routes, same as every
     * other zatca_phase2-gated action in this codebase.
     */
    public function test_owner_without_feature_is_redirected_to_billing_on_every_route(): void
    {
        $company = Company::create([
            'name' => 'No Feature Owner Co',
            'email' => 'zatca-owner-nofeature-'.uniqid().'@example.com',
            'vat_number' => '300000000000003',
        ]);
        $owner = $this->user($company, 'C');

        $routes = [
            ['GET', '/app/settings/zatca'],
            ['POST', '/app/settings/zatca/settings'],
            ['POST', '/app/settings/zatca/csr'],
            ['POST', '/app/settings/zatca/compliance-csid'],
            ['POST', '/app/settings/zatca/compliance-check'],
            ['POST', '/app/settings/zatca/production-csid'],
            ['POST', '/app/settings/zatca/test-connection'],
            ['POST', '/app/settings/zatca/reset'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($owner)->call($method, $uri);
            $response->assertRedirect('/app/billing');
        }
    }

    /**
     * (3) A team member without manage_company_settings is blocked from
     * every one of this controller's routes, same shape as
     * ZatcaUserLogUiTest's own ability-gating test.
     */
    public function test_non_owner_without_ability_is_blocked_on_every_route(): void
    {
        $company = $this->companyWithZatcaPhase2('D');
        $viewer = $this->user($company, 'D', 'viewer');

        $routes = [
            ['GET', '/app/settings/zatca'],
            ['POST', '/app/settings/zatca/settings'],
            ['POST', '/app/settings/zatca/csr'],
            ['POST', '/app/settings/zatca/compliance-csid'],
            ['POST', '/app/settings/zatca/compliance-check'],
            ['POST', '/app/settings/zatca/production-csid'],
            ['POST', '/app/settings/zatca/test-connection'],
            ['POST', '/app/settings/zatca/reset'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($viewer)->call($method, $uri);
            $response->assertRedirect('/app');
        }
    }

    /**
     * (4) There is no {id} parameter anywhere on this controller's routes
     * for one company's owner to tamper with to reach another company's
     * ZATCA data — a sanity check that generating a CSR as company A's
     * owner only ever touches company A, never company B, confirming the
     * controller really does scope strictly to Auth::user()->company_id.
     */
    public function test_owner_can_only_ever_affect_their_own_company(): void
    {
        $companyA = $this->companyWithZatcaPhase2('E');
        $ownerA = $this->user($companyA, 'E');

        $companyB = $this->companyWithZatcaPhase2('F');
        $this->user($companyB, 'F');

        $this->actingAs($ownerA)->post('/app/settings/zatca/csr')->assertRedirect('/app/settings/zatca');

        $companyA->refresh();
        $companyB->refresh();

        $this->assertNotEmpty($companyA->zatca_csr);
        $this->assertNull($companyB->zatca_csr);
        $this->assertSame('csr_generated', $companyA->zatca_status);
        $this->assertNotSame('csr_generated', $companyB->zatca_status);
    }
}
