<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * Covers the new company-facing ZATCA submission dashboard
 * (App\Http\Controllers\App\ZatcaDashboardController): strict
 * company-scoping on both the dashboard list and the log-detail page (one
 * company must never see another's rows, and must 404 — not just hide —
 * another company's log id), and the ability ('manage_company_settings')
 * + plan feature ('zatca_phase2') gates that guard both actions, mirroring
 * InvoiceTemplateCopyToAllTest's own "blocked across companies"/
 * "blocked for a user without the right ability" style.
 */
class ZatcaUserLogUiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * App\Support\Feature caches the resolved plan/flags in static
     * properties across calls within the same PHP process — reset them
     * between tests here (same pattern CompanyFeatureOverrideTest already
     * uses) so one test's Auth::user() doesn't leak into the next
     * assertion's Feature::allows() check.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function companyWithZatcaPhase2(string $tag): Company
    {
        return Company::create([
            'name' => "Zatca UI {$tag} Co",
            'email' => 'zatca-ui-'.strtolower($tag).'-'.uniqid().'@example.com',
            'feature_overrides' => ['zatca_phase2' => true],
        ]);
    }

    private function user(Company $company, string $tag, string $role = 'owner'): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => ucfirst($role)." {$tag}",
            'email' => strtolower($role).'-zatca-ui-'.strtolower($tag).'-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function invoiceWithLog(Company $company, string $number, string $status = 'failed'): ZatcaInvoiceLog
    {
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => $number,
            'status' => 'unpaid',
            'total' => 1000,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'zatca_uuid' => (string) Str::uuid(),
            'zatca_status' => $status,
        ]);

        return ZatcaInvoiceLog::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'environment' => 'developer',
            'invoice_type' => 'b2c',
            'direction' => 'reporting',
            'status' => $status,
            'request_uuid' => (string) Str::uuid(),
            'error_message' => $status === 'failed' ? 'HTTP 400: invalid invoice' : null,
            'submitted_at' => now(),
        ]);
    }

    /** (1) The dashboard must show only the logged-in user's own company's logs, never another company's. */
    public function test_user_dashboard_shows_only_that_companys_own_logs(): void
    {
        $companyA = $this->companyWithZatcaPhase2('A');
        $ownerA = $this->user($companyA, 'A');
        $this->invoiceWithLog($companyA, 'INV-UI-USER-A-0001');

        $companyB = $this->companyWithZatcaPhase2('B');
        $this->user($companyB, 'B');
        $this->invoiceWithLog($companyB, 'INV-UI-USER-B-0001');

        $response = $this->actingAs($ownerA)->get('/app/zatca');

        $response->assertOk();
        $response->assertSee('INV-UI-USER-A-0001', false);
        $response->assertDontSee('INV-UI-USER-B-0001', false);
    }

    /** (2) A log-show request for another company's log id must 404, not just be hidden from the listing. */
    public function test_user_log_show_404s_for_another_companys_log(): void
    {
        $companyA = $this->companyWithZatcaPhase2('C');
        $this->user($companyA, 'C');
        $logA = $this->invoiceWithLog($companyA, 'INV-UI-USER-C-0001');

        $companyB = $this->companyWithZatcaPhase2('D');
        $ownerB = $this->user($companyB, 'D');

        $response = $this->actingAs($ownerB)->get("/app/zatca/logs/invoice/{$logA->id}");

        $response->assertNotFound();
    }

    /** (2b) ...but the owning company can see its own log's full detail. */
    public function test_user_log_show_renders_own_companys_log(): void
    {
        $company = $this->companyWithZatcaPhase2('E');
        $owner = $this->user($company, 'E');
        $log = $this->invoiceWithLog($company, 'INV-UI-USER-E-0001');
        $log->update(['xml_payload' => '<Invoice>mine</Invoice>']);

        $response = $this->actingAs($owner)->get("/app/zatca/logs/invoice/{$log->id}");

        $response->assertOk();
        $response->assertSee('INV-UI-USER-E-0001', false);
        $response->assertSee('mine', false);
    }

    /** (3) A 'viewer' (no manage_company_settings ability) is blocked from the dashboard, same shape as InvoiceTemplateCopyToAllTest's ability-gating test. */
    public function test_user_without_manage_company_settings_ability_is_blocked(): void
    {
        $company = $this->companyWithZatcaPhase2('F');
        $viewer = $this->user($company, 'F', 'viewer');

        $response = $this->actingAs($viewer)->get('/app/zatca');

        $response->assertRedirect('/app');
    }

    /** (4) An owner whose plan has no zatca_phase2 feature is redirected to Billing, same as every other zatca_phase2-gated action. */
    public function test_user_without_zatca_phase2_feature_is_blocked(): void
    {
        $company = Company::create([
            'name' => 'No Feature Co',
            'email' => 'zatca-ui-nofeature-'.uniqid().'@example.com',
        ]);
        $owner = $this->user($company, 'G');

        $response = $this->actingAs($owner)->get('/app/zatca');

        $response->assertRedirect('/app/billing');
    }
}
