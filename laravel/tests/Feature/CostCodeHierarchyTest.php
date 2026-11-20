<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CostCode;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\VendorBill;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the new, OPTIONAL CSI-MasterFormat-style cost-code hierarchy: the
 * platform-seeded cost_codes table/CostCode model, the new
 * ReportController::costByCode() report, and — most importantly — proof
 * that none of this changes a single number the existing
 * ReportController::costVariance() dashboard already shows. Wrapped in
 * DatabaseTransactions, same convention as CashFlowReportTest/TimesheetTest,
 * so none of this touches the real dev sqlite database beyond the test
 * (the platform-wide cost codes themselves are already seeded there by
 * CostCodeSeeder and are read-only reference data for every test below).
 */
class CostCodeHierarchyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompany(string $tag): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-'.strtolower($tag).'-'.bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['reports' => true]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag).'@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $tag): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => "Owner {$tag}",
            'email' => 'owner-'.strtolower($tag).'@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    /**
     * (1) The platform-seeded hierarchy is exactly 2 levels deep, every
     * division is platform-wide (company_id null), and a known
     * division/sub-code pair resolves correctly — e.g. "03 30 00
     * Cast-in-Place Concrete" is a child of "03 00 00 Concrete".
     */
    public function test_seeded_cost_codes_form_the_expected_two_level_hierarchy(): void
    {
        $divisions = CostCode::whereNull('parent_id')->get();
        $this->assertGreaterThanOrEqual(12, $divisions->count(), 'a practical subset of ~12-15 divisions, not the full 49-division CSI standard');
        $this->assertLessThanOrEqual(15, $divisions->count());

        foreach ($divisions as $division) {
            $this->assertSame(1, $division->level);
            $this->assertNull($division->company_id, 'every seeded division is platform-wide');
        }

        $subCodes = CostCode::whereNotNull('parent_id')->get();
        $this->assertGreaterThan(0, $subCodes->count());
        foreach ($subCodes as $subCode) {
            $this->assertSame(2, $subCode->level);
            $this->assertNull($subCode->company_id, 'every seeded sub-code is platform-wide');
            // No level goes any deeper than 2 — a sub-code is never itself a parent.
            $this->assertSame(0, CostCode::where('parent_id', $subCode->id)->count());
        }

        $concrete = CostCode::whereNull('parent_id')->where('code', '03 00 00')->first();
        $this->assertNotNull($concrete, 'the Concrete division must be seeded');
        $this->assertSame('Concrete', $concrete->description);

        $castInPlace = CostCode::where('code', '03 30 00')->first();
        $this->assertNotNull($castInPlace);
        $this->assertSame($concrete->id, $castInPlace->parent_id, '"03 30 00 Cast-in-Place Concrete" rolls up under the "03 00 00 Concrete" division');
        $this->assertSame(2, $castInPlace->level);

        // CostCode::selectableFor() returns every division with its children eager-loaded.
        $selectable = CostCode::selectableFor($this->makeCompany('HIER')->id);
        $concreteSelectable = $selectable->firstWhere('code', '03 00 00');
        $this->assertNotNull($concreteSelectable);
        $this->assertTrue($concreteSelectable->children->contains('code', '03 30 00'));
    }

    /**
     * (2) Assigning cost codes — one to a sub-code, one straight to its
     * parent division, and one left uncoded — correctly sums
     * estimated-vs-actual under the right division in the new report,
     * including rolling the sub-code assignment up into its parent
     * division's total alongside the direct-to-division assignment.
     */
    public function test_cost_by_code_report_sums_and_rolls_up_correctly(): void
    {
        $company = $this->makeCompany('CC1');
        $owner = $this->makeUser($company, 'CC1');
        $project = Project::create(['company_id' => $company->id, 'name' => 'Project CC1', 'status' => 'in_progress', 'budget' => 50000]);

        $concrete = CostCode::whereNull('parent_id')->where('code', '03 00 00')->first();
        $castInPlace = CostCode::where('code', '03 30 00')->first();
        $masonry = CostCode::whereNull('parent_id')->where('code', '04 00 00')->first();

        $estimate = Estimate::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Baseline CC1', 'status' => 'accepted', 'total' => 1800,
        ]);
        // Coded to a SUB-code of Concrete.
        EstimateItem::create([
            'estimate_id' => $estimate->id, 'description' => 'Foundation pour',
            'item_type' => 'material', 'qty' => 1, 'unit_cost' => 1000, 'total' => 1000,
            'cost_code_id' => $castInPlace->id,
        ]);
        // Coded straight to the Masonry DIVISION itself (no sub-code).
        EstimateItem::create([
            'estimate_id' => $estimate->id, 'description' => 'Block work',
            'item_type' => 'material', 'qty' => 1, 'unit_cost' => 500, 'total' => 500,
            'cost_code_id' => $masonry->id,
        ]);
        // Left uncoded — the default for every item today.
        EstimateItem::create([
            'estimate_id' => $estimate->id, 'description' => 'Misc',
            'item_type' => 'other', 'qty' => 1, 'unit_cost' => 300, 'total' => 300,
            'cost_code_id' => null,
        ]);

        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'material',
            'description' => 'Concrete delivery', 'amount' => 400, 'bill_date' => now()->format('Y-m-d'),
            'cost_code_id' => $castInPlace->id,
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'material',
            'description' => 'Block delivery', 'amount' => 200, 'bill_date' => now()->format('Y-m-d'),
            'cost_code_id' => $masonry->id,
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'other',
            'description' => 'Misc bill', 'amount' => 150, 'bill_date' => now()->format('Y-m-d'),
            'cost_code_id' => null,
        ]);

        $response = $this->actingAs($owner)->get('/app/reports/cost-codes');
        $response->assertOk();

        $tree = collect($response->viewData('tree'));
        $concreteRow = $tree->firstWhere('id', $concrete->id);
        $masonryRow = $tree->firstWhere('id', $masonry->id);

        $this->assertNotNull($concreteRow);
        $this->assertSame(1000.0, $concreteRow['estimated'], 'the sub-code assignment rolls up into its parent Concrete division');
        $this->assertSame(400.0, $concreteRow['actual']);
        $castInPlaceChild = collect($concreteRow['children'])->firstWhere('id', $castInPlace->id);
        $this->assertNotNull($castInPlaceChild);
        $this->assertSame(1000.0, $castInPlaceChild['estimated'], 'the sub-code itself also shows its own exact figure');
        $this->assertSame(400.0, $castInPlaceChild['actual']);

        $this->assertNotNull($masonryRow);
        $this->assertSame(500.0, $masonryRow['estimated'], 'an item coded straight to the division (no sub-code) still counts under that division');
        $this->assertSame(200.0, $masonryRow['actual']);

        $totals = $response->viewData('totals');
        $this->assertSame(300.0, $totals['uncodedEstimated']);
        $this->assertSame(150.0, $totals['uncodedActual']);
        $this->assertSame(1800.0, $totals['estimated'], '1000 (concrete) + 500 (masonry) + 300 (uncoded)');
        $this->assertSame(750.0, $totals['actual'], '400 (concrete) + 200 (masonry) + 150 (uncoded)');
    }

    /**
     * (3) THE non-interference proof: a project whose estimate items and
     * vendor bills are left entirely UNCODED (cost_code_id = null — the
     * default for every row today) must have its new report's "Uncoded"
     * totals match the EXISTING costVariance() dashboard's portfolio
     * totals for that same company EXACTLY, down to the cent. This proves
     * the new cost-code dimension changes nothing about the existing
     * Estimated-vs-Actual dashboard.
     */
    public function test_uncoded_totals_match_existing_cost_variance_dashboard_exactly(): void
    {
        $company = $this->makeCompany('CC2');
        $owner = $this->makeUser($company, 'CC2');
        $project = Project::create(['company_id' => $company->id, 'name' => 'Project CC2', 'status' => 'in_progress', 'budget' => 50000]);

        $estimate = Estimate::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Baseline CC2', 'status' => 'accepted', 'total' => 20000,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimate->id, 'description' => 'Labor baseline',
            'item_type' => 'labor', 'qty' => 1, 'unit_cost' => 12345.67, 'total' => 12345.67,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimate->id, 'description' => 'Material baseline',
            'item_type' => 'material', 'qty' => 1, 'unit_cost' => 5000, 'total' => 5000,
        ]);

        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'labor',
            'description' => 'Labor bill', 'amount' => 9876.54, 'bill_date' => now()->format('Y-m-d'),
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'material',
            'description' => 'Material bill', 'amount' => 3210.00, 'bill_date' => now()->format('Y-m-d'),
        ]);

        $this->assertSame(0, EstimateItem::whereIn('estimate_id', [$estimate->id])->whereNotNull('cost_code_id')->count());
        $this->assertSame(0, VendorBill::where('company_id', $company->id)->whereNotNull('cost_code_id')->count());

        $variance = $this->actingAs($owner)->get('/app/reports/profit');
        $variance->assertOk();
        $varianceTotals = $variance->viewData('totals');

        $byCode = $this->actingAs($owner)->get('/app/reports/cost-codes');
        $byCode->assertOk();
        $byCodeTotals = $byCode->viewData('totals');

        $this->assertSame($varianceTotals['estimatedCost'], $byCodeTotals['uncodedEstimated'], 'with nothing coded, the Uncoded bucket must equal the existing dashboard\'s portfolio estimated cost exactly');
        $this->assertSame($varianceTotals['actualCost'], $byCodeTotals['uncodedActual'], 'with nothing coded, the Uncoded bucket must equal the existing dashboard\'s portfolio actual cost exactly');
        $this->assertSame(0.0, $byCodeTotals['estimated'] - $byCodeTotals['uncodedEstimated'], 'every division total must be exactly 0 — nothing was coded');
        $this->assertSame(0.0, $byCodeTotals['actual'] - $byCodeTotals['uncodedActual']);

        foreach (collect($byCode->viewData('tree')) as $division) {
            $this->assertSame(0.0, $division['estimated']);
            $this->assertSame(0.0, $division['actual']);
        }
    }

    /** (4) Standard cross-tenant isolation: company B's coded amounts never leak into company A's cost-by-code totals. */
    public function test_cost_by_code_report_never_leaks_another_companys_amounts(): void
    {
        $companyA = $this->makeCompany('CCA');
        $ownerA = $this->makeUser($companyA, 'CCA');
        $projectA = Project::create(['company_id' => $companyA->id, 'name' => 'Project CCA', 'status' => 'in_progress', 'budget' => 10000]);
        $concrete = CostCode::whereNull('parent_id')->where('code', '03 00 00')->first();

        $estimateA = Estimate::create([
            'company_id' => $companyA->id, 'project_id' => $projectA->id,
            'title' => 'Baseline CCA', 'status' => 'accepted', 'total' => 1000,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimateA->id, 'description' => 'A item',
            'item_type' => 'material', 'qty' => 1, 'unit_cost' => 1000, 'total' => 1000,
            'cost_code_id' => $concrete->id,
        ]);
        VendorBill::create([
            'company_id' => $companyA->id, 'project_id' => $projectA->id, 'category' => 'material',
            'description' => 'A bill', 'amount' => 600, 'bill_date' => now()->format('Y-m-d'),
            'cost_code_id' => $concrete->id,
        ]);

        $companyB = $this->makeCompany('CCB');
        $projectB = Project::create(['company_id' => $companyB->id, 'name' => 'Project CCB', 'status' => 'in_progress', 'budget' => 10000]);
        $estimateB = Estimate::create([
            'company_id' => $companyB->id, 'project_id' => $projectB->id,
            'title' => 'Baseline CCB', 'status' => 'accepted', 'total' => 999999,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimateB->id, 'description' => 'B item',
            'item_type' => 'material', 'qty' => 1, 'unit_cost' => 999999, 'total' => 999999,
            'cost_code_id' => $concrete->id,
        ]);
        VendorBill::create([
            'company_id' => $companyB->id, 'project_id' => $projectB->id, 'category' => 'material',
            'description' => 'B bill', 'amount' => 999999, 'bill_date' => now()->format('Y-m-d'),
            'cost_code_id' => $concrete->id,
        ]);

        $response = $this->actingAs($ownerA)->get('/app/reports/cost-codes');
        $response->assertOk();

        $tree = collect($response->viewData('tree'));
        $concreteRow = $tree->firstWhere('id', $concrete->id);
        $this->assertSame(1000.0, $concreteRow['estimated'], 'company B\'s huge estimate must never leak into company A\'s total');
        $this->assertSame(600.0, $concreteRow['actual']);

        $totals = $response->viewData('totals');
        $this->assertSame(1000.0, $totals['estimated']);
        $this->assertSame(600.0, $totals['actual']);
    }

    /** A custom, company-owned cost code is never visible to (or usable by) another company. */
    public function test_company_custom_cost_code_is_scoped_to_its_own_company(): void
    {
        $companyA = $this->makeCompany('SCOPEA');
        $companyB = $this->makeCompany('SCOPEB');

        $custom = CostCode::create([
            'company_id' => $companyA->id,
            'code' => '99 00 00',
            'description' => 'Custom Division',
            'parent_id' => null,
            'level' => 1,
            'sort_order' => 0,
        ]);

        $this->assertNull(CostCode::ownedById($custom->id, $companyB->id), 'company B must never resolve company A\'s custom cost code as its own');
        $this->assertNotNull(CostCode::ownedById($custom->id, $companyA->id));

        $selectableForB = CostCode::selectableFor($companyB->id);
        $this->assertFalse($selectableForB->contains('id', $custom->id), 'company B\'s selectable list must not include company A\'s custom division');

        $selectableForA = CostCode::selectableFor($companyA->id);
        $this->assertTrue($selectableForA->contains('id', $custom->id));
    }
}
