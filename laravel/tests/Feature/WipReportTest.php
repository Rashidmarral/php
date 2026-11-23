<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\VendorBill;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the Work-in-Progress (WIP) report (ReportController::wip()), which is built on the
 * exact same per-project financial baseline ReportController::projectFinancialBaselines()
 * extracted out of costVariance() (see WipReportTest's sibling checks below, and
 * TimesheetTest/CostCodeHierarchyTest, which must still pass unchanged against
 * /app/reports/profit after that refactor).
 *
 * Wrapped in DatabaseTransactions, same convention as CashFlowReportTest/TimesheetTest, so
 * none of this touches the real dev sqlite database beyond the test.
 */
class WipReportTest extends TestCase
{
    use DatabaseTransactions;

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
            'feature_flags' => json_encode(['reports' => true]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $tag): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => "Owner {$tag}",
            'email' => 'owner-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    /**
     * Hand-worked example (verify this arithmetic independently before trusting the report's
     * own output — same rigor as AsphaltEstimateCalcTest):
     *
     *   contractValue      = accepted estimate total                      = 100,000.00
     *   estimatedCostTotal = accepted estimate's single item total        =  70,000.00
     *   actualCostTotal    = single VendorBill amount                     =  35,000.00
     *   percentComplete    = actualCostTotal / estimatedCostTotal * 100
     *                      = 35,000 / 70,000 * 100                       =      50.0 %
     *   earnedRevenue      = contractValue * (percentComplete / 100)
     *                      = 100,000 * 0.50                               =  50,000.00
     *   billedToDate       = SUM(invoices.total) - SUM(issued credit notes.total)
     *                        + SUM(issued debit notes.total)
     *                      = 60,000 - 5,000 + 0                           =  55,000.00
     *                        (a SECOND credit note, status='draft' i.e. NOT issued, for
     *                        9,999.00 is also seeded and must be excluded from this sum —
     *                        proves the 'issued' status filter, mirroring
     *                        Invoice::creditedTotal()'s own filter, actually applies)
     *   overUnderBilled    = billedToDate - earnedRevenue
     *                      = 55,000 - 50,000                              =   5,000.00 (overbilled)
     *   costToComplete     = max(0, estimatedCostTotal - actualCostTotal)
     *                      = max(0, 70,000 - 35,000)                      =  35,000.00
     *   projectedFinalProfit = contractValue - estimatedCostTotal
     *                      = 100,000 - 70,000                             =  30,000.00
     *
     *   alert: overbilling threshold is 5% of contractValue = 5,000.00 exactly; the
     *   overUnderBilled of 5,000.00 is NOT strictly greater than the threshold, so this
     *   project must NOT be flagged — a deliberate boundary check that "at the threshold"
     *   does not trip the alert, only "past" it does.
     */
    public function test_wip_report_computes_exact_numbers_for_a_hand_worked_example(): void
    {
        $company = $this->makeCompany('WIP1');
        $owner = $this->makeUser($company, 'WIP1');

        $project = Project::create([
            'company_id' => $company->id, 'name' => 'Project WIP1',
            'status' => 'in_progress', 'budget' => 100000,
        ]);

        $estimate = Estimate::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Baseline WIP1', 'status' => 'accepted', 'total' => 100000.00,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimate->id, 'description' => 'Baseline material estimate',
            'item_type' => 'material', 'qty' => 1, 'unit_cost' => 70000.00, 'total' => 70000.00,
        ]);

        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'material',
            'description' => 'Actual material spend', 'amount' => 35000.00,
            'bill_date' => now()->format('Y-m-d'), 'status' => 'unpaid',
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'invoice_number' => 'INV-WIP1-1', 'status' => 'unpaid', 'total' => 60000.00,
        ]);
        CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'note_number' => 'CN-WIP1-1', 'status' => 'issued', 'total' => 5000.00,
        ]);
        // NOT issued — must be excluded from billedToDate entirely.
        CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'note_number' => 'CN-WIP1-2', 'status' => 'draft', 'total' => 9999.00,
        ]);

        $response = $this->actingAs($owner)->get('/app/reports/wip');
        $response->assertOk();

        $rows = $response->viewData('rows');
        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertSame(100000.0, $row['contractValue']);
        $this->assertSame(50.0, $row['percentComplete']);
        $this->assertSame(50000.0, $row['earnedRevenue']);
        $this->assertSame(55000.0, $row['billedToDate']);
        $this->assertSame(5000.0, $row['overUnderBilled']);
        $this->assertSame(35000.0, $row['costToComplete']);
        $this->assertSame(30000.0, $row['projectedFinalProfit']);
        $this->assertFalse($row['alert'], 'overbilled by exactly the 5% threshold, not past it, so must not be flagged');

        $totals = $response->viewData('totals');
        $this->assertSame(100000.0, $totals['contractValue']);
        $this->assertSame(50000.0, $totals['earnedRevenue']);
        $this->assertSame(55000.0, $totals['billedToDate']);
        $this->assertSame(5000.0, $totals['overUnderBilled']);
        $this->assertSame(35000.0, $totals['costToComplete']);
    }

    /**
     * A debit note (status='issued') must ADD to billedToDate, on top of the invoice total
     * minus credited amount — the mirror image of a credit note.
     */
    public function test_wip_report_adds_issued_debit_notes_to_billed_to_date(): void
    {
        $company = $this->makeCompany('WIP2');
        $owner = $this->makeUser($company, 'WIP2');

        $project = Project::create([
            'company_id' => $company->id, 'name' => 'Project WIP2',
            'status' => 'in_progress', 'budget' => 50000,
        ]);
        $estimate = Estimate::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Baseline WIP2', 'status' => 'accepted', 'total' => 40000.00,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimate->id, 'description' => 'Baseline labor estimate',
            'item_type' => 'labor', 'qty' => 1, 'unit_cost' => 20000.00, 'total' => 20000.00,
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'labor',
            'description' => 'Actual labor spend', 'amount' => 10000.00,
            'bill_date' => now()->format('Y-m-d'), 'status' => 'unpaid',
        ]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'invoice_number' => 'INV-WIP2-1', 'status' => 'unpaid', 'total' => 10000.00,
        ]);
        DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'note_number' => 'DN-WIP2-1', 'status' => 'issued', 'total' => 2000.00,
        ]);

        $response = $this->actingAs($owner)->get('/app/reports/wip');
        $response->assertOk();
        $row = $response->viewData('rows')[0];

        // billedToDate = 10,000 - 0 (no credit notes) + 2,000 = 12,000.
        $this->assertSame(12000.0, $row['billedToDate']);
    }

    /** A project with no accepted estimate has no WIP baseline and must be excluded entirely. */
    public function test_project_without_accepted_estimate_is_excluded_from_wip_report(): void
    {
        $company = $this->makeCompany('WIP3');
        $owner = $this->makeUser($company, 'WIP3');

        // No accepted estimate at all — only a project and an invoice sitting against it.
        $noBaselineProject = Project::create([
            'company_id' => $company->id, 'name' => 'Project WIP3 No Baseline',
            'status' => 'in_progress', 'budget' => 20000,
        ]);
        Invoice::create([
            'company_id' => $company->id, 'project_id' => $noBaselineProject->id,
            'invoice_number' => 'INV-WIP3-1', 'status' => 'unpaid', 'total' => 15000.00,
        ]);

        // A declined (not accepted) estimate on the same project must not count as a baseline.
        Estimate::create([
            'company_id' => $company->id, 'project_id' => $noBaselineProject->id,
            'title' => 'Declined estimate', 'status' => 'declined', 'total' => 99999.00,
        ]);

        $response = $this->actingAs($owner)->get('/app/reports/wip');
        $response->assertOk();

        $this->assertCount(0, $response->viewData('rows'));
        $totals = $response->viewData('totals');
        $this->assertSame(0.0, $totals['contractValue']);
        $this->assertSame(0.0, $totals['billedToDate']);
    }

    /**
     * A project whose actual cost has run past its whole estimated cost must show
     * percentComplete UNCAPPED past 100% (a real overrun signal, not hidden by a cap), and
     * must be flagged regardless of its billing position.
     *
     *   estimatedCostTotal = 50,000.00, actualCostTotal = 60,000.00
     *   percentComplete = 60,000 / 50,000 * 100 = 120.0 %
     *   contractValue = 100,000.00 → earnedRevenue = 100,000 * 1.20 = 120,000.00
     *   billedToDate = 0 (no invoices at all) → overUnderBilled = 0 - 120,000 = -120,000.00
     *   (deeply UNDERBILLED by this measure — but the project is still flagged, because
     *   percentComplete > 100 is itself an alert condition independent of billing)
     */
    public function test_project_over_estimated_cost_shows_uncapped_percent_and_is_flagged(): void
    {
        $company = $this->makeCompany('WIP4');
        $owner = $this->makeUser($company, 'WIP4');

        $project = Project::create([
            'company_id' => $company->id, 'name' => 'Project WIP4 Overrun',
            'status' => 'in_progress', 'budget' => 100000,
        ]);
        $estimate = Estimate::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Baseline WIP4', 'status' => 'accepted', 'total' => 100000.00,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimate->id, 'description' => 'Baseline equipment estimate',
            'item_type' => 'equipment', 'qty' => 1, 'unit_cost' => 50000.00, 'total' => 50000.00,
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'equipment',
            'description' => 'Overrun equipment spend', 'amount' => 60000.00,
            'bill_date' => now()->format('Y-m-d'), 'status' => 'unpaid',
        ]);

        $response = $this->actingAs($owner)->get('/app/reports/wip');
        $response->assertOk();
        $row = $response->viewData('rows')[0];

        $this->assertSame(120.0, $row['percentComplete'], 'uncapped: 60,000/50,000 * 100 = 120%, not clamped to 100');
        $this->assertSame(-120000.0, $row['overUnderBilled']);
        $this->assertTrue($row['alert'], 'percentComplete > 100 must flag the project even though it is underbilled, not overbilled');
    }

    /** Company A must never see Company B's projects, invoices, or notes in its WIP report. */
    public function test_wip_report_never_leaks_another_companys_data(): void
    {
        $companyA = $this->makeCompany('WIPA');
        $ownerA = $this->makeUser($companyA, 'WIPA');
        $projectA = Project::create([
            'company_id' => $companyA->id, 'name' => 'Project WIPA',
            'status' => 'in_progress', 'budget' => 100000,
        ]);
        $estimateA = Estimate::create([
            'company_id' => $companyA->id, 'project_id' => $projectA->id,
            'title' => 'Baseline WIPA', 'status' => 'accepted', 'total' => 100000.00,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimateA->id, 'description' => 'A material estimate',
            'item_type' => 'material', 'qty' => 1, 'unit_cost' => 40000.00, 'total' => 40000.00,
        ]);
        VendorBill::create([
            'company_id' => $companyA->id, 'project_id' => $projectA->id, 'category' => 'material',
            'description' => 'A material spend', 'amount' => 20000.00,
            'bill_date' => now()->format('Y-m-d'), 'status' => 'unpaid',
        ]);
        Invoice::create([
            'company_id' => $companyA->id, 'project_id' => $projectA->id,
            'invoice_number' => 'INV-WIPA-1', 'status' => 'unpaid', 'total' => 30000.00,
        ]);

        // Company B: same shape, different (much larger) numbers, seeded TWICE so a leak
        // would visibly inflate company A's totals rather than coincidentally matching them.
        $companyB = $this->makeCompany('WIPB');
        $this->makeUser($companyB, 'WIPB');
        for ($i = 0; $i < 2; $i++) {
            $projectB = Project::create([
                'company_id' => $companyB->id, 'name' => "Project WIPB {$i}",
                'status' => 'in_progress', 'budget' => 900000,
            ]);
            $estimateB = Estimate::create([
                'company_id' => $companyB->id, 'project_id' => $projectB->id,
                'title' => "Baseline WIPB {$i}", 'status' => 'accepted', 'total' => 900000.00,
            ]);
            EstimateItem::create([
                'estimate_id' => $estimateB->id, 'description' => 'B material estimate',
                'item_type' => 'material', 'qty' => 1, 'unit_cost' => 500000.00, 'total' => 500000.00,
            ]);
            VendorBill::create([
                'company_id' => $companyB->id, 'project_id' => $projectB->id, 'category' => 'material',
                'description' => 'B material spend', 'amount' => 400000.00,
                'bill_date' => now()->format('Y-m-d'), 'status' => 'unpaid',
            ]);
            Invoice::create([
                'company_id' => $companyB->id, 'project_id' => $projectB->id,
                'invoice_number' => "INV-WIPB-{$i}", 'status' => 'unpaid', 'total' => 700000.00,
            ]);
        }

        $response = $this->actingAs($ownerA)->get('/app/reports/wip');
        $response->assertOk();

        $rows = $response->viewData('rows');
        $this->assertCount(1, $rows, 'only company A\'s one baselined project must appear');
        $this->assertSame('Project WIPA', $rows[0]['project']['name']);
        $this->assertSame(100000.0, $rows[0]['contractValue']);
        $this->assertSame(30000.0, $rows[0]['billedToDate']);

        $totals = $response->viewData('totals');
        $this->assertSame(100000.0, $totals['contractValue'], 'must equal company A\'s own contract value, never inflated by company B\'s rows');
        $this->assertSame(30000.0, $totals['billedToDate']);
    }
}
