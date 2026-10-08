<?php

namespace Tests\Feature;

use App\Http\Controllers\App\TeamController;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the per-user permission_overrides escape hatch on top of the 5 fixed roles in
 * AppServiceProvider::boot(): a JSON map on `users` that can grant or deny one of the 6
 * Gate abilities for one specific user beyond their role's default. Wrapped in
 * DatabaseTransactions, matching EquipmentTest/RfqTest's own convention.
 */
class PermissionOverrideTest extends TestCase
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

    private function makeCompany(string $tag): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode([]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $role, string $tag, ?array $overrides = null): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => ucfirst($role) . " {$tag}",
            'email' => strtolower($role) . '-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'permission_overrides' => $overrides,
        ]);
    }

    /**
     * (1) The critical zero-regression proof: with NO overrides set, every one of the 4
     * non-owner assignable roles resolves every one of the 6 abilities EXACTLY as the
     * pre-existing role-only logic in AppServiceProvider did before this feature existed.
     * Also covers 'owner' for completeness, since it's a real role value even though it's
     * not in ASSIGNABLE_ROLES.
     */
    public function test_user_with_no_overrides_resolves_every_ability_exactly_as_the_original_role_logic(): void
    {
        $company = $this->makeCompany('REG');

        $expected = [
            'owner' => ['manage_billing' => true, 'manage_team' => true, 'manage_company_settings' => true, 'manage_business_setup' => true, 'approve_documents' => true, 'write' => true],
            'admin' => ['manage_billing' => false, 'manage_team' => true, 'manage_company_settings' => true, 'manage_business_setup' => true, 'approve_documents' => true, 'write' => true],
            'estimator' => ['manage_billing' => false, 'manage_team' => false, 'manage_company_settings' => false, 'manage_business_setup' => false, 'approve_documents' => false, 'write' => true],
            'accountant' => ['manage_billing' => false, 'manage_team' => false, 'manage_company_settings' => false, 'manage_business_setup' => false, 'approve_documents' => false, 'write' => true],
            'viewer' => ['manage_billing' => false, 'manage_team' => false, 'manage_company_settings' => false, 'manage_business_setup' => false, 'approve_documents' => false, 'write' => false],
        ];

        foreach ($expected as $role => $abilities) {
            $user = $this->makeUser($company, $role, 'REG' . $role);
            $this->assertNull($user->permission_overrides, "{$role} must have no overrides stored by default");
            foreach ($abilities as $ability => $expectedResult) {
                $this->assertSame(
                    $expectedResult,
                    $user->can($ability),
                    "role={$role} ability={$ability} must resolve to " . ($expectedResult ? 'true' : 'false') . ' with no overrides'
                );
                // permissionOverride() itself must report "no override" for every ability.
                $this->assertNull($user->permissionOverride($ability), "role={$role} ability={$ability} permissionOverride() must be null when unset");
            }
        }
    }

    /** (2) An explicit true override grants an ability the role would normally deny. */
    public function test_explicit_true_override_grants_an_ability_the_role_would_normally_deny(): void
    {
        $company = $this->makeCompany('GRANT');
        $estimator = $this->makeUser($company, 'estimator', 'GRANT');

        $this->assertFalse($estimator->can('approve_documents'), 'sanity check: estimator cannot approve_documents by default');

        $estimator->update(['permission_overrides' => ['approve_documents' => true]]);
        $estimator->refresh();

        $this->assertTrue($estimator->can('approve_documents'), 'explicit true override must grant approve_documents to an estimator');
        // Every other ability for this user is untouched and still falls through to role logic.
        $this->assertTrue($estimator->can('write'));
        $this->assertFalse($estimator->can('manage_team'));
    }

    /** (3) An explicit false override denies an ability the role would normally have. */
    public function test_explicit_false_override_denies_an_ability_the_role_would_normally_have(): void
    {
        $company = $this->makeCompany('DENY');
        $admin = $this->makeUser($company, 'admin', 'DENY');

        $this->assertTrue($admin->can('write'), 'sanity check: admin has write by default');

        $admin->update(['permission_overrides' => ['write' => false]]);
        $admin->refresh();

        $this->assertFalse($admin->can('write'), 'explicit false override must deny write to this admin');
        // Other abilities for this admin are untouched.
        $this->assertTrue($admin->can('manage_team'));
        $this->assertTrue($admin->can('approve_documents'));
    }

    /** (4) An owner/admin can set another same-company user's overrides via the new route. */
    public function test_owner_and_admin_can_set_another_same_company_users_overrides_via_the_route(): void
    {
        $company = $this->makeCompany('ROUTE');
        $owner = $this->makeUser($company, 'owner', 'ROUTE');
        $admin = $this->makeUser($company, 'admin', 'ROUTEA');
        $estimator = $this->makeUser($company, 'estimator', 'ROUTEE');
        $accountant = $this->makeUser($company, 'accountant', 'ROUTEC');

        // Owner grants the estimator approve_documents and denies them write.
        $ownerResponse = $this->actingAs($owner)->post("/app/team/{$estimator->id}/permissions", [
            'overrides' => [
                'approve_documents' => 'allow',
                'write' => 'deny',
                'manage_team' => 'default',
            ],
        ]);
        $ownerResponse->assertRedirect('/app/team');
        $estimator->refresh();
        $this->assertTrue($estimator->can('approve_documents'));
        $this->assertFalse($estimator->can('write'));
        $this->assertFalse($estimator->can('manage_team'), 'left as default, so still role-false for an estimator');

        // Admin (who also has manage_team) can set overrides on the accountant too.
        $adminResponse = $this->actingAs($admin)->post("/app/team/{$accountant->id}/permissions", [
            'overrides' => [
                'manage_company_settings' => 'allow',
            ],
        ]);
        $adminResponse->assertRedirect('/app/team');
        $accountant->refresh();
        $this->assertTrue($accountant->can('manage_company_settings'));

        // Setting every ability back to 'default' clears the column back to null.
        $this->actingAs($owner)->post("/app/team/{$estimator->id}/permissions", [
            'overrides' => [
                'approve_documents' => 'default',
                'write' => 'default',
            ],
        ]);
        $estimator->refresh();
        $this->assertNull($estimator->permission_overrides);
    }

    /** (5) Tenant isolation: the route rejects attempts to set overrides on a user from another company. */
    public function test_route_rejects_setting_overrides_on_a_user_from_another_company(): void
    {
        $companyA = $this->makeCompany('TENA');
        $companyB = $this->makeCompany('TENB');
        $ownerA = $this->makeUser($companyA, 'owner', 'TENA');
        $estimatorB = $this->makeUser($companyB, 'estimator', 'TENB');

        $response = $this->actingAs($ownerA)->post("/app/team/{$estimatorB->id}/permissions", [
            'overrides' => ['approve_documents' => 'allow'],
        ]);
        $response->assertNotFound();

        $estimatorB->refresh();
        $this->assertNull($estimatorB->permission_overrides, 'cross-company override attempt must never be applied');
        $this->assertFalse($estimatorB->can('approve_documents'));
    }

    /** (6) The route rejects attempts to set overrides on an 'owner'-role user. */
    public function test_route_rejects_setting_overrides_on_an_owner_user(): void
    {
        $company = $this->makeCompany('OWN');
        $owner = $this->makeUser($company, 'owner', 'OWN');
        $admin = $this->makeUser($company, 'admin', 'OWNA');

        $response = $this->actingAs($admin)->post("/app/team/{$owner->id}/permissions", [
            'overrides' => ['manage_billing' => 'allow'],
        ]);
        $response->assertRedirect('/app/team');

        $owner->refresh();
        $this->assertNull($owner->permission_overrides, "the owner's permission_overrides must never be set");
        // Owner abilities remain fixed/always-everything regardless.
        $this->assertTrue($owner->can('manage_billing'));
    }

    /** A user (even an admin with manage_team) cannot set overrides on their OWN record. */
    public function test_route_rejects_a_user_editing_their_own_overrides(): void
    {
        $company = $this->makeCompany('SELF');
        $admin = $this->makeUser($company, 'admin', 'SELF');

        $response = $this->actingAs($admin)->post("/app/team/{$admin->id}/permissions", [
            'overrides' => ['manage_billing' => 'allow'],
        ]);
        $response->assertRedirect('/app/team');

        $admin->refresh();
        $this->assertNull($admin->permission_overrides, 'a user must not be able to self-escalate via their own overrides');
        $this->assertFalse($admin->can('manage_billing'), 'manage_billing must still be owner-only for this admin');
    }

    /** A user without manage_team (e.g. a viewer) cannot reach the route at all. */
    public function test_route_requires_manage_team_ability(): void
    {
        $company = $this->makeCompany('NOPERM');
        $viewer = $this->makeUser($company, 'viewer', 'NOPERM');
        $estimator = $this->makeUser($company, 'estimator', 'NOPERME');

        $response = $this->actingAs($viewer)->post("/app/team/{$estimator->id}/permissions", [
            'overrides' => ['write' => 'deny'],
        ]);
        $response->assertRedirect('/app');

        $estimator->refresh();
        $this->assertNull($estimator->permission_overrides);
    }

    /** (7) Gate::before's super_admin bypass still works completely unaffected by any of this. */
    public function test_super_admin_bypass_is_unaffected_by_permission_overrides(): void
    {
        $company = $this->makeCompany('SUPER');
        // A platform super_admin isn't tied to a company in normal use, but Gate::before
        // short-circuits purely on role, before any company/ability logic runs.
        $superAdmin = User::create([
            'company_id' => $company->id,
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        foreach (TeamController::OVERRIDABLE_ABILITIES as $ability) {
            $this->assertTrue($superAdmin->can($ability), "super_admin must bypass the {$ability} check via Gate::before");
        }

        // Even an explicit false override on the super_admin's own record can't take it away —
        // Gate::before short-circuits before the ability closure (and permissionOverride()) ever runs.
        $superAdmin->update(['permission_overrides' => array_fill_keys(TeamController::OVERRIDABLE_ABILITIES, false)]);
        $superAdmin->refresh();
        foreach (TeamController::OVERRIDABLE_ABILITIES as $ability) {
            $this->assertTrue($superAdmin->can($ability), "super_admin must still bypass {$ability} even with a false override stored");
        }
    }
}
