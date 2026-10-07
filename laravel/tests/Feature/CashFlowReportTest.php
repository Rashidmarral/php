<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the cash-flow forecasting report (ReportController::cashFlow()): proves the
 * overdue/monthly bucket sums and the running cumulative balance are arithmetically correct
 * against seeded Invoice/PurchaseOrder/SubcontractPayment data — and, separately, that the
 * excluded rows (a paid invoice, a fully-invoiced PO, a received PO, an already-certified
 * subcontract payment) never leak into any bucket — plus that Company A never sees Company
 * B's amounts. Wrapped in DatabaseTransactions, same convention as
 * TimesheetTest/GlobalSearchTest, so none of this touches the real dev sqlite database
 * beyond the test.
 */
class CashFlowReportTest extends TestCase
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
     * Seeds one company with exactly one of each thing the forecast must count, and exactly
     * one of each thing it must NOT count, then returns the numbers the caller should see:
     *
     * COUNTED: an overdue unpaid invoice (due 5 days ago), a future unpaid invoice due 10
     * days into the month 2 months from now, an open (issued) PO that's been partially
     * invoiced with an overdue expected_delivery_date (remaining balance = 3000), and a
     * draft subcontract payment landing 15 days into that same future month (800).
     *
     * EXCLUDED: a paid invoice, a fully-invoiced open PO (remaining <= 0), a received
     * (not "open") PO, and an already-certified subcontract payment.
     *
     * Dates are built from the month's own start (never a fixed day offset from "today")
     * so the future items land in a predictable, verifiable bucket regardless of which day
     * of the current month the test happens to run on.
     */
    private function seedCashFlowData(Company $company, string $tag): array
    {
        $project = Project::create([
            'company_id' => $company->id,
            'name' => "Project {$tag}",
            'status' => 'in_progress',
            'budget' => 100000,
        ]);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => "Supplier {$tag}"]);

        $now = now();
        $futureMonthStart = $now->copy()->startOfMonth()->addMonths(2);

        // --- Money IN ---
        Invoice::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'invoice_number' => "INV-{$tag}-1", 'status' => 'unpaid', 'total' => 1000.00,
            'due_date' => $now->copy()->subDays(5)->format('Y-m-d'),
        ]);
        Invoice::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'invoice_number' => "INV-{$tag}-2", 'status' => 'unpaid', 'total' => 2000.00,
            'due_date' => $futureMonthStart->copy()->addDays(10)->format('Y-m-d'),
        ]);
        // Paid — must contribute nothing anywhere in the forecast.
        Invoice::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'invoice_number' => "INV-{$tag}-3", 'status' => 'paid', 'total' => 9999.00,
            'due_date' => $now->copy()->subDays(5)->format('Y-m-d'),
        ]);

        // --- Money OUT: purchase orders ---
        $poOpenOverdue = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'po_number' => "PO-{$tag}-1", 'status' => 'issued', 'total' => 5000.00,
            'expected_delivery_date' => $now->copy()->subDays(2)->format('Y-m-d'),
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'purchase_order_id' => $poOpenOverdue->id, 'category' => 'material',
            'description' => 'Partial delivery', 'amount' => 2000.00,
            'bill_date' => $now->copy()->format('Y-m-d'), 'status' => 'unpaid',
        ]);
        // Fully invoiced already (remaining <= 0) — must be excluded entirely.
        $poFullyInvoiced = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'po_number' => "PO-{$tag}-2", 'status' => 'draft', 'total' => 3000.00,
            'expected_delivery_date' => $now->copy()->addDays(5)->format('Y-m-d'),
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'purchase_order_id' => $poFullyInvoiced->id, 'category' => 'material',
            'description' => 'Full delivery', 'amount' => 3000.00,
            'bill_date' => $now->copy()->format('Y-m-d'), 'status' => 'unpaid',
        ]);
        // Received (not "open") — must be excluded entirely even though uninvoiced.
        PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'po_number' => "PO-{$tag}-3", 'status' => 'received', 'total' => 10000.00,
            'expected_delivery_date' => $now->copy()->addDays(5)->format('Y-m-d'),
        ]);

        // --- Money OUT: subcontract payments ---
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'title' => "Subcontract {$tag}", 'contract_value' => 50000.00,
        ]);
        SubcontractPayment::create([
            'company_id' => $company->id, 'subcontract_id' => $subcontract->id,
            'payment_number' => 1, 'payment_date' => $futureMonthStart->copy()->addDays(15)->format('Y-m-d'),
            'status' => 'draft', 'net_payable' => 800.00,
        ]);
        // Certified — already a real expense, must be excluded entirely from the forecast.
        SubcontractPayment::create([
            'company_id' => $company->id, 'subcontract_id' => $subcontract->id,
            'payment_number' => 2, 'payment_date' => $now->copy()->subDays(10)->format('Y-m-d'),
            'status' => 'certified', 'net_payable' => 7000.00,
        ]);

        return [
            'overdueIn' => 1000.00,
            'overdueOut' => 3000.00,
            'futureMonthKey' => $futureMonthStart->format('Y-m'),
            'futureMonthIn' => 2000.00,
            'futureMonthOut' => 800.00,
        ];
    }

    public function test_cash_flow_forecast_buckets_sum_correctly_against_seeded_data(): void
    {
        $company = $this->makeCompany('CF');
        $owner = $this->makeUser($company, 'CF');
        $expected = $this->seedCashFlowData($company, 'CF');

        $response = $this->actingAs($owner)->get('/app/reports/cash-flow');
        $response->assertOk();

        $rows = $response->viewData('rows');
        $byKey = collect($rows)->keyBy('key');

        $this->assertCount(7, $rows, 'overdue + 6 month buckets');

        $overdueRow = $byKey['overdue'];
        $this->assertSame($expected['overdueIn'], $overdueRow['in']);
        $this->assertSame($expected['overdueOut'], $overdueRow['out']);
        $this->assertSame(-2000.00, $overdueRow['net'], '1000 in - 3000 out');
        $this->assertSame($overdueRow['net'], $overdueRow['cumulative'], "running balance starts at 0, so the first bucket's cumulative equals its own net");

        $this->assertTrue($byKey->has($expected['futureMonthKey']), 'the future month must appear as one of the 6 forecast buckets');
        $futureRow = $byKey[$expected['futureMonthKey']];
        $this->assertSame($expected['futureMonthIn'], $futureRow['in']);
        $this->assertSame($expected['futureMonthOut'], $futureRow['out']);
        $this->assertSame(1200.00, $futureRow['net'], '2000 in - 800 out');

        // Running cumulative balance: every row's cumulative = sum of net up to and
        // including that row, in chronological order (overdue first, then oldest to soonest).
        $running = 0.0;
        foreach ($rows as $row) {
            $running = round($running + $row['net'], 2);
            $this->assertSame($running, $row['cumulative'], "cumulative mismatch at bucket {$row['key']}");
        }
        $this->assertSame(-800.00, $running, 'final balance: -2000 (overdue) + 1200 (future month) across otherwise-empty buckets');

        // The totals block agrees exactly with the rows it was built from.
        $totals = $response->viewData('totals');
        $this->assertSame($expected['overdueIn'], $totals['overdueIn']);
        $this->assertSame($expected['overdueOut'], $totals['overdueOut']);
        $this->assertSame(end($rows)['cumulative'], $totals['endingBalance']);
        $this->assertSame(2000.00, $totals['forecastIn'], 'sum of the 6 month buckets only — excludes the overdue bucket');
        $this->assertSame(800.00, $totals['forecastOut']);
        $this->assertSame(1200.00, $totals['netForecast']);

        // A fully-invoiced PO, a received PO, a paid invoice, and an already-certified
        // subcontract payment must never contribute anywhere — the grand total across every
        // bucket (overdue + all 6 months) equals exactly what's still genuinely outstanding.
        $totalIn = round(array_sum(array_column($rows, 'in')), 2);
        $totalOut = round(array_sum(array_column($rows, 'out')), 2);
        $this->assertSame(3000.00, $totalIn, 'only the overdue (1000) + future (2000) unpaid invoices count — the 9999 paid invoice contributes nothing');
        $this->assertSame(3800.00, $totalOut, 'only the overdue PO remainder (3000) + future draft subcontract payment (800) count — the fully-invoiced PO, the received PO, and the certified payment contribute nothing');
    }

    public function test_cash_flow_forecast_never_leaks_another_companys_amounts(): void
    {
        $companyA = $this->makeCompany('CFA');
        $ownerA = $this->makeUser($companyA, 'CFA');
        $expectedA = $this->seedCashFlowData($companyA, 'CFA');

        $companyB = $this->makeCompany('CFB');
        $this->makeUser($companyB, 'CFB');
        // Seed company B's data TWICE, so a cross-tenant leak would visibly inflate company
        // A's totals rather than coincidentally matching them.
        $this->seedCashFlowData($companyB, 'CFB');
        $this->seedCashFlowData($companyB, 'CFB');

        $response = $this->actingAs($ownerA)->get('/app/reports/cash-flow');
        $response->assertOk();

        $rows = $response->viewData('rows');
        $byKey = collect($rows)->keyBy('key');
        $overdueRow = $byKey['overdue'];

        // Company A's figures are exactly its own seeded numbers — never inflated by
        // company B's (doubled) rows sitting in the very same tables.
        $this->assertSame($expectedA['overdueIn'], $overdueRow['in']);
        $this->assertSame($expectedA['overdueOut'], $overdueRow['out']);
        $futureRowA = $byKey[$expectedA['futureMonthKey']];
        $this->assertSame($expectedA['futureMonthIn'], $futureRowA['in']);
        $this->assertSame($expectedA['futureMonthOut'], $futureRowA['out']);

        $totals = $response->viewData('totals');
        $this->assertSame($expectedA['overdueIn'], $totals['overdueIn']);
        $this->assertSame($expectedA['overdueOut'], $totals['overdueOut']);
    }
}
