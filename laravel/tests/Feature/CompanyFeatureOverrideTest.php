<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the company-level feature_overrides escape hatch on top of each company's
 * subscribed plan: a JSON map on `companies` that can grant or deny one Feature::ALL key
 * for one specific company beyond/instead of whatever its plan's feature_flags says. Exact
 * structural mirror of PermissionOverrideTest (users.permission_overrides on top of role
 * defaults) — company_feature_overrides is the same null-means-fall-through convention one
 * level up. Wrapped in DatabaseTransactions, matching PermissionOverrideTest's own convention.
 */
class CompanyFeatureOverrideTest extends TestCase
{
    use DatabaseTransactions;

    /** See RfiSubmittalDocumentVersionTest's own docblock on why this reset is needed. */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Support\Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makePlan(string $tag, array $flags): Plan
    {
        return Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode($flags),
        ]);
    }

    private function makeCompany(string $tag, ?Plan $plan = null, ?array $featureOverrides = null): Company
    {
        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan?->id,
            'feature_overrides' => $featureOverrides,
        ]);
    }

    private function makeUser(Company $company, string $tag, string $role = 'owner'): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => ucfirst($role) . " {$tag}",
            'email' => strtolower($role) . '-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    /** (1a) Zero-regression: a company with no overrides behaves exactly as its plan dictates, via allows(). */
    public function test_company_with_no_overrides_behaves_exactly_as_its_plan_dictates_via_allows(): void
    {
        $plan = $this->makePlan('REG', ['reports' => true, 'suppliers' => false]);
        $company = $this->makeCompany('REG', $plan);
        $owner = $this->makeUser($company, 'REG');

        $this->assertNull($company->feature_overrides);
        $this->assertNull($company->featureOverride('reports'));
        $this->assertNull($company->featureOverride('suppliers'));

        $this->actingAs($owner);
        $this->assertTrue(Feature::allows('reports'));
        $this->assertFalse(Feature::allows('suppliers'));
    }

    /** (1b) Same zero-regression proof for the no-session allowsForCompany() path. */
    public function test_company_with_no_overrides_behaves_exactly_as_its_plan_dictates_via_allows_for_company(): void
    {
        $plan = $this->makePlan('REGPUB', ['reports' => true, 'suppliers' => false]);
        $company = $this->makeCompany('REGPUB', $plan);

        $this->assertTrue(Feature::allowsForCompany('reports', $company));
        $this->assertFalse(Feature::allowsForCompany('suppliers', $company));
    }

    /** (2) An explicit true override grants a feature the plan doesn't include, via allows(). */
    public function test_explicit_true_override_grants_a_feature_the_plan_does_not_include(): void
    {
        $plan = $this->makePlan('GRANT', ['reports' => false]);
        $company = $this->makeCompany('GRANT', $plan);
        $owner = $this->makeUser($company, 'GRANT');

        $this->actingAs($owner);
        $this->assertFalse(Feature::allows('reports'), 'sanity check: plan does not include reports');

        $company->update(['feature_overrides' => ['reports' => true]]);
        $company->refresh();

        $this->assertTrue(Feature::allows('reports'), 'explicit true override must grant reports beyond the plan');
    }

    /** (2b) Same proof for allowsForCompany(). */
    public function test_explicit_true_override_grants_a_feature_via_allows_for_company(): void
    {
        $plan = $this->makePlan('GRANTPUB', ['reports' => false]);
        $company = $this->makeCompany('GRANTPUB', $plan, ['reports' => true]);

        $this->assertTrue(Feature::allowsForCompany('reports', $company));
    }

    /** (3) An explicit false override denies a feature the plan DOES include, via allows(). */
    public function test_explicit_false_override_denies_a_feature_the_plan_does_include(): void
    {
        $plan = $this->makePlan('DENY', ['reports' => true]);
        $company = $this->makeCompany('DENY', $plan);
        $owner = $this->makeUser($company, 'DENY');

        $this->actingAs($owner);
        $this->assertTrue(Feature::allows('reports'), 'sanity check: plan includes reports');

        $company->update(['feature_overrides' => ['reports' => false]]);
        $company->refresh();

        $this->assertFalse(Feature::allows('reports'), 'explicit false override must deny reports even though the plan includes it');
    }

    /** (3b) Same proof for allowsForCompany(). */
    public function test_explicit_false_override_denies_a_feature_via_allows_for_company(): void
    {
        $plan = $this->makePlan('DENYPUB', ['reports' => true]);
        $company = $this->makeCompany('DENYPUB', $plan, ['reports' => false]);

        $this->assertFalse(Feature::allowsForCompany('reports', $company));
    }

    /** (4) A super admin can hit the new route and set another company's feature overrides. */
    public function test_super_admin_can_set_a_companys_feature_overrides_via_the_route(): void
    {
        $plan = $this->makePlan('ROUTE', ['reports' => true, 'suppliers' => true]);
        $company = $this->makeCompany('ROUTE', $plan);
        $superAdmin = User::create([
            'name' => 'Super Admin Route',
            'email' => 'super-admin-route@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin)->post("/admin/companies/{$company->id}/features", [
            'overrides' => [
                'reports' => 'deny',
                'suppliers' => 'default',
                'cash_flow_forecasting' => 'allow',
            ],
        ]);
        $response->assertRedirect("/admin/companies/{$company->id}");

        $company->refresh();
        $this->assertSame(
            ['reports' => false, 'cash_flow_forecasting' => true],
            $company->feature_overrides,
            'only the explicitly allow/deny keys are stored — "default" is omitted entirely'
        );
        $this->assertFalse(Feature::allowsForCompany('reports', $company));
        $this->assertTrue(Feature::allowsForCompany('suppliers', $company), 'left as default, so still plan-true');
        $this->assertTrue(Feature::allowsForCompany('cash_flow_forecasting', $company));
    }

    /** (4) Only admin.super (super_admin) can hit the new route — a support_admin is rejected. */
    public function test_support_admin_cannot_set_a_companys_feature_overrides(): void
    {
        $plan = $this->makePlan('SUPPORT', ['reports' => true]);
        $company = $this->makeCompany('SUPPORT', $plan);
        $supportAdmin = User::create([
            'name' => 'Support Admin Route',
            'email' => 'support-admin-route@example.com',
            'password' => Hash::make('password'),
            'role' => 'support_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($supportAdmin)->post("/admin/companies/{$company->id}/features", [
            'overrides' => ['reports' => 'deny'],
        ]);
        $response->assertStatus(403);

        $company->refresh();
        $this->assertNull($company->feature_overrides, 'a support_admin must never be able to set company feature overrides');
    }

    /** Setting every feature back to 'default' clears the column back to null, mirroring permission_overrides. */
    public function test_setting_every_feature_back_to_default_clears_the_column_to_null(): void
    {
        $plan = $this->makePlan('CLEAR', ['reports' => true]);
        $company = $this->makeCompany('CLEAR', $plan, ['reports' => false]);
        $superAdmin = User::create([
            'name' => 'Super Admin Clear',
            'email' => 'super-admin-clear@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin)->post("/admin/companies/{$company->id}/features", [
            'overrides' => ['reports' => 'default'],
        ]);
        $response->assertRedirect("/admin/companies/{$company->id}");

        $company->refresh();
        $this->assertNull($company->feature_overrides);
    }

    /** (6) AuditLog::record fires on save with the company_id correctly attached as the target. */
    public function test_audit_log_records_the_feature_override_change_with_the_company_as_target(): void
    {
        $plan = $this->makePlan('AUDIT', ['reports' => true]);
        $company = $this->makeCompany('AUDIT', $plan);
        $superAdmin = User::create([
            'name' => 'Super Admin Audit',
            'email' => 'super-admin-audit@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($superAdmin)->post("/admin/companies/{$company->id}/features", [
            'overrides' => ['reports' => 'deny'],
        ])->assertRedirect("/admin/companies/{$company->id}");

        $log = AuditLog::where('action', 'company_feature_override')->where('target_id', $company->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('company', $log->target_type);
        $this->assertSame($superAdmin->id, $log->admin_id);
        $this->assertStringContainsString($company->name, (string) $log->details);
        $this->assertStringContainsString('reports', (string) $log->details);
    }

    /** A super_admin always allows every feature regardless of overrides, via Feature::allows()'s own bypass. */
    public function test_super_admin_bypass_is_unaffected_by_company_feature_overrides(): void
    {
        $plan = $this->makePlan('BYPASS', []);
        $company = $this->makeCompany('BYPASS', $plan, array_fill_keys(array_keys(Feature::ALL), false));
        $superAdmin = User::create([
            'company_id' => $company->id,
            'name' => 'Super Admin Bypass',
            'email' => 'super-admin-bypass@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($superAdmin);
        foreach (array_keys(Feature::ALL) as $key) {
            $this->assertTrue(Feature::allows($key), "super_admin must bypass the {$key} override via the isSuperAdmin() check");
        }
    }
}
