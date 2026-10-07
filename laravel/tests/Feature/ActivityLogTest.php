<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the tenant-level (company-user) activity/audit log. Most importantly,
 * same cross-tenant style as GlobalSearchTest/ApprovalChainTest: a company's own
 * Activity Log page must NEVER show another company's audit rows (tenant
 * isolation is the hard requirement — see AuditLog::recordForCompany()'s
 * docblock). Also proves a tenant write action actually gets logged with the
 * right company_id, and that extending AuditLog::record() with an optional
 * company_id param left the existing ADMIN-panel audit log feature (platform-
 * level actions, company_id always null) completely unaffected.
 *
 * Wrapped in DatabaseTransactions so none of this touches the real dev sqlite
 * database beyond the test, matching GlobalSearchTest's convention.
 */
class ActivityLogTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithOwner(string $tag): array
    {
        $company = Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag) . '@example.com',
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag) . '-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'name' => "Zephyr{$tag} Client",
            'email' => 'client-' . strtolower($tag) . '@example.com',
        ]);

        return compact('company', 'owner', 'client');
    }

    /** Creating an invoice (a tenant write action) must produce an audit_logs row stamped with this company's own company_id. */
    public function test_tenant_write_action_records_an_audit_row_with_the_correct_company_id(): void
    {
        $a = $this->makeCompanyWithOwner('ActA');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            'client_id' => $a['client']->id,
            'item_description' => ['Foundation works'],
            'item_qty' => [1],
            'item_price' => [4500],
            'apply_vat' => '0',
        ]);
        $response->assertRedirect();

        $log = AuditLog::where('action', 'invoice_create')->where('company_id', $a['company']->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($a['company']->id, $log->company_id);
        $this->assertSame($a['owner']->id, $log->admin_id);
        $this->assertStringContainsString('4,500.00 SAR', $log->details);
    }

    /** The critical cross-tenant-leakage test: Company A's Activity Log page must never show Company B's rows, even when both companies have audit rows. */
    public function test_company_activity_log_never_shows_another_companys_rows(): void
    {
        $a = $this->makeCompanyWithOwner('ActLeakA');
        $b = $this->makeCompanyWithOwner('ActLeakB');

        $this->actingAs($a['owner'])->post('/app/invoices', [
            'client_id' => $a['client']->id,
            'item_description' => ['Rebar supply A'],
            'item_qty' => [1],
            'item_price' => [1000],
            'apply_vat' => '0',
        ])->assertRedirect();

        $this->actingAs($b['owner'])->post('/app/invoices', [
            'client_id' => $b['client']->id,
            'item_description' => ['Rebar supply B — secret line for company B only'],
            'item_qty' => [1],
            'item_price' => [2000],
            'apply_vat' => '0',
        ])->assertRedirect();

        $bLog = AuditLog::where('action', 'invoice_create')->where('company_id', $b['company']->id)->first();
        $this->assertNotNull($bLog);

        $response = $this->actingAs($a['owner'])->get('/app/activity-log');

        $response->assertOk();
        $response->assertDontSee('Rebar supply B', false);
        $response->assertDontSee($bLog->details, false);
    }

    /** Same test from the other direction, for completeness: Company B must never see Company A's rows either. */
    public function test_company_activity_log_is_scoped_both_ways(): void
    {
        $a = $this->makeCompanyWithOwner('ActBothA');
        $b = $this->makeCompanyWithOwner('ActBothB');

        $this->actingAs($a['owner'])->post('/app/invoices', [
            'client_id' => $a['client']->id,
            'item_description' => ['Only visible to company A'],
            'item_qty' => [1],
            'item_price' => [777],
            'apply_vat' => '0',
        ])->assertRedirect();

        $response = $this->actingAs($b['owner'])->get('/app/activity-log');

        $response->assertOk();
        $response->assertDontSee('Only visible to company A', false);
        $response->assertSee('No matching activity yet.', false);
    }

    /**
     * Zero regression to the existing platform-level feature: an existing ADMIN-panel
     * action (Admin\PlanController::store(), the exact call site this task extends)
     * still records a row with company_id = null and still shows up correctly on the
     * admin audit log page.
     */
    public function test_existing_admin_panel_audit_logging_is_unaffected(): void
    {
        $superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'super-admin-activity-log-test@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin)->post('/admin/plans', [
            'slug' => 'plan-activity-log-test-' . bin2hex(random_bytes(4)),
            'name' => 'Activity Log Test Plan',
            'price_monthly' => 100,
            'price_yearly' => 1000,
            'max_users' => 5,
            'max_projects' => 10,
        ]);
        $response->assertRedirect('/admin/plans');

        $log = AuditLog::where('action', 'plan_create')->where('details', 'Activity Log Test Plan')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->company_id);
        $this->assertSame($superAdmin->id, $log->admin_id);

        $adminPage = $this->actingAs($superAdmin)->get('/admin/audit-log');
        $adminPage->assertOk();
        $adminPage->assertSee('Activity Log Test Plan', false);
    }

    /** A plain estimator (not owner/admin) must not be able to view the company's Activity Log — mirrors the manage_team Gate's own role restriction. */
    public function test_non_owner_admin_role_is_denied_the_activity_log_page(): void
    {
        $a = $this->makeCompanyWithOwner('ActRoleA');
        $estimator = User::create([
            'company_id' => $a['company']->id,
            'name' => 'Just an Estimator',
            'email' => 'estimator-activity-log-test@example.com',
            'password' => Hash::make('password'),
            'role' => 'estimator',
            'status' => 'active',
        ]);

        $response = $this->actingAs($estimator)->get('/app/activity-log');

        $response->assertRedirect('/app');
    }
}
