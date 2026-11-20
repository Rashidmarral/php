<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Plan;
use App\Models\Project;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\VendorBill;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers Task #58 (project-level labor timesheets, wired into the Estimated-vs-Actual
 * cost-variance dashboard). Wrapped in DatabaseTransactions, same convention as
 * RfiSubmittalDocumentVersionTest, so none of this touches the real dev sqlite database
 * beyond the test.
 */
class TimesheetTest extends TestCase
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
            'feature_flags' => json_encode([
                'reports' => true,
                'timesheets' => true,
            ]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $role, string $tag, ?float $hourlyRate = null): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => ucfirst($role) . " {$tag}",
            'email' => strtolower($role) . '-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'hourly_rate' => $hourlyRate,
        ]);
    }

    private function makeProjectWithBaseline(Company $company, string $tag, float $laborVendorBillAmount): Project
    {
        $project = Project::create([
            'company_id' => $company->id,
            'name' => "Project {$tag}",
            'status' => 'in_progress',
            'budget' => 100000,
        ]);

        $estimate = Estimate::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'title' => "Baseline estimate {$tag}",
            'status' => 'accepted',
            'total' => 50000,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimate->id,
            'item_type' => 'labor',
            'description' => 'Baseline labor estimate',
            'qty' => 1,
            'unit_cost' => 20000,
            'total' => 20000,
        ]);

        VendorBill::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'category' => 'labor',
            'description' => 'Baseline labor vendor bill',
            'amount' => $laborVendorBillAmount,
            'bill_date' => now()->format('Y-m-d'),
            'status' => 'unpaid',
        ]);

        return $project;
    }

    /**
     * THE critical regression check: a project with existing vendor-bill data and ZERO
     * timesheet entries must produce byte-identical costVariance() output before and after
     * this feature's code lands. Since the code has already landed in this branch, this
     * proves the EQUIVALENT thing directly — that with zero TimesheetEntry rows in the
     * company, the new timesheet-derived term contributes exactly 0.0, so actualCost /
     * actualByCategory['labor'] equal the pre-existing VendorBill-only figures exactly,
     * down to the cent.
     */
    public function test_zero_timesheet_entries_leave_cost_variance_dashboard_completely_unchanged(): void
    {
        $company = $this->makeCompany('ZT');
        $owner = $this->makeUser($company, 'owner', 'ZT');
        $project = $this->makeProjectWithBaseline($company, 'ZT', 12345.67);

        $this->assertSame(0, TimesheetEntry::where('company_id', $company->id)->count());

        $response = $this->actingAs($owner)->get('/app/reports/profit');
        $response->assertOk();

        $rows = $response->viewData('rows');
        $this->assertCount(1, $rows);
        $row = $rows[0];

        // actualByCategory['labor'] is EXACTLY the VendorBill amount — the timesheet term
        // added exactly 0.0 on top, never a non-zero drift.
        $this->assertSame(12345.67, $row['actualByCategory']['labor']);
        $this->assertSame(12345.67, $row['actualCostTotal']);
        $this->assertSame(0.0, $row['timesheetHours']);

        // Every other category is untouched (still 0.0 — no vendor bills raised against them).
        foreach (['material', 'equipment', 'subcontractor', 'other'] as $cat) {
            $this->assertSame(0.0, $row['actualByCategory'][$cat]);
        }

        // Portfolio totals agree with the single project's own row — nothing added anywhere.
        $totals = $response->viewData('totals');
        $this->assertSame(12345.67, $totals['actualCost']);
    }

    /**
     * The worked example from the task: 3 timesheet entries (8h, 6h, 4h = 18h) for a team
     * member on hourly_rate = 50 SAR/hr => cost = 900.00 SAR total, ADDED ON TOP of the
     * project's existing VendorBill labor amount — not replacing it.
     */
    public function test_timesheet_hours_at_a_set_hourly_rate_add_exactly_on_top_of_existing_vendor_bill_labor_cost(): void
    {
        $company = $this->makeCompany('WK');
        $owner = $this->makeUser($company, 'owner', 'WK');
        $worker = $this->makeUser($company, 'estimator', 'WK', hourlyRate: 50.00);
        $existingVendorBillLabor = 5000.00;
        $project = $this->makeProjectWithBaseline($company, 'WK', $existingVendorBillLabor);

        foreach ([8, 6, 4] as $hours) {
            $create = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/timesheets', [
                'user_id' => $worker->id,
                'work_date' => now()->format('Y-m-d'),
                'hours' => $hours,
            ]);
            $create->assertRedirect('/app/projects/' . $project->id);
        }

        $this->assertSame(3, TimesheetEntry::where('project_id', $project->id)->count());
        $this->assertSame(18.0, (float) TimesheetEntry::where('project_id', $project->id)->sum('hours'));

        // Each entry snapshotted hourly_rate_snapshot=50.00 and cost = hours * 50 at creation.
        $entries = TimesheetEntry::where('project_id', $project->id)->orderBy('hours')->get();
        $this->assertSame('50.00', (string) $entries[0]->hourly_rate_snapshot);
        $this->assertSame(200.00, (float) $entries[0]->cost); // 4h * 50
        $this->assertSame(300.00, (float) $entries[1]->cost); // 6h * 50
        $this->assertSame(400.00, (float) $entries[2]->cost); // 8h * 50

        $totalTimesheetCost = (float) TimesheetEntry::where('project_id', $project->id)->sum('cost');
        $this->assertSame(900.00, $totalTimesheetCost, '18h at 50 SAR/hr = 900 SAR exactly');

        $response = $this->actingAs($owner)->get('/app/reports/profit');
        $response->assertOk();
        $rows = $response->viewData('rows');
        $row = $rows[0];

        // The 900 SAR is ADDED on top of the pre-existing 5000.00 vendor-bill labor cost.
        $this->assertSame($existingVendorBillLabor + 900.00, $row['actualByCategory']['labor']);
        $this->assertSame(5900.00, $row['actualByCategory']['labor']);
        $this->assertSame(18.0, $row['timesheetHours']);

        // Changing the worker's CURRENT hourly_rate afterward must NOT alter already-logged
        // entries — the snapshot precedent (same as SubcontractPayment::retention_percent).
        $worker->update(['hourly_rate' => 999]);
        $entries[0]->refresh();
        $this->assertSame('50.00', (string) $entries[0]->hourly_rate_snapshot, 'snapshot never re-reads a live rate change');
        $this->assertSame(200.00, (float) $entries[0]->cost);
    }

    /**
     * A team member with NO hourly_rate set: hours are still logged (tracked), but the
     * entry's cost is null — excluded from the cost sum entirely, not a silent wrong 0.00
     * charge — and flagged in the UI as "rate not set".
     */
    public function test_timesheet_entry_for_worker_with_no_hourly_rate_tracks_hours_but_contributes_null_cost(): void
    {
        $company = $this->makeCompany('NR');
        $owner = $this->makeUser($company, 'owner', 'NR');
        $workerNoRate = $this->makeUser($company, 'estimator', 'NR', hourlyRate: null);
        $project = $this->makeProjectWithBaseline($company, 'NR', 1000.00);

        $create = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/timesheets', [
            'user_id' => $workerNoRate->id,
            'work_date' => now()->format('Y-m-d'),
            'hours' => 7,
            'notes' => 'No rate on file yet',
        ]);
        $create->assertRedirect('/app/projects/' . $project->id);

        $entry = TimesheetEntry::where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(7.0, (float) $entry->hours, 'hours worked are still tracked');
        $this->assertNull($entry->hourly_rate_snapshot);
        $this->assertNull($entry->cost, 'no resolvable rate => null cost, never a wrong 0.00 charge');

        // The dashboard's labor actual-cost is UNCHANGED by this entry — a null cost is
        // excluded from the SUM, not coerced to 0 and added.
        $response = $this->actingAs($owner)->get('/app/reports/profit');
        $rows = $response->viewData('rows');
        $this->assertSame(1000.00, $rows[0]['actualByCategory']['labor'], 'null-cost entry contributes nothing to the sum');

        // Visibly flagged in the UI as "rate not set", not silently invisible.
        $show = $this->actingAs($owner)->get('/app/projects/' . $project->id);
        $show->assertOk();
        $show->assertSeeText(t('user.timesheets.rate_not_set'));
    }

    public function test_timesheet_validation_rejects_invalid_hours_future_dates_and_cross_tenant_workers(): void
    {
        $companyA = $this->makeCompany('VA');
        $companyB = $this->makeCompany('VB');
        $ownerA = $this->makeUser($companyA, 'owner', 'VA');
        $workerB = $this->makeUser($companyB, 'owner', 'VB');
        $projectA = $this->makeProjectWithBaseline($companyA, 'VA', 0.0);

        // Hours <= 0 rejected.
        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/timesheets', [
            'user_id' => $ownerA->id, 'work_date' => now()->format('Y-m-d'), 'hours' => 0,
        ]);
        $this->assertSame(0, TimesheetEntry::where('project_id', $projectA->id)->count());

        // Hours > 24 rejected.
        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/timesheets', [
            'user_id' => $ownerA->id, 'work_date' => now()->format('Y-m-d'), 'hours' => 25,
        ]);
        $this->assertSame(0, TimesheetEntry::where('project_id', $projectA->id)->count());

        // Future work_date rejected.
        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/timesheets', [
            'user_id' => $ownerA->id, 'work_date' => now()->addDays(2)->format('Y-m-d'), 'hours' => 4,
        ]);
        $this->assertSame(0, TimesheetEntry::where('project_id', $projectA->id)->count());

        // A cross-tenant user_id (company B's user) is rejected, never silently logged.
        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/timesheets', [
            'user_id' => $workerB->id, 'work_date' => now()->format('Y-m-d'), 'hours' => 4,
        ]);
        $this->assertSame(0, TimesheetEntry::where('project_id', $projectA->id)->count());

        // Company B cannot reach company A's project at all.
        $this->actingAs($workerB)->post('/app/projects/' . $projectA->id . '/timesheets', [
            'user_id' => $workerB->id, 'work_date' => now()->format('Y-m-d'), 'hours' => 4,
        ])->assertNotFound();
        $this->actingAs($workerB)->get('/app/projects/' . $projectA->id . '/timesheets')->assertNotFound();

        // A valid entry succeeds.
        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/timesheets', [
            'user_id' => $ownerA->id, 'work_date' => now()->format('Y-m-d'), 'hours' => 4,
        ]);
        $this->assertSame(1, TimesheetEntry::where('project_id', $projectA->id)->count());

        // Tenant isolation on destroy: company B cannot delete company A's entry.
        $entry = TimesheetEntry::where('project_id', $projectA->id)->first();
        $this->actingAs($workerB)->post('/app/timesheets/' . $entry->id . '/delete')->assertNotFound();
        $this->assertSame(1, TimesheetEntry::where('id', $entry->id)->count());

        // Company A itself can delete its own entry freely (no lock precedent, same as SiteLog).
        $this->actingAs($ownerA)->post('/app/timesheets/' . $entry->id . '/delete')->assertRedirect('/app/projects/' . $projectA->id);
        $this->assertSame(0, TimesheetEntry::where('id', $entry->id)->count());
    }

    public function test_timesheets_feature_gate_blocks_logging_when_plan_lacks_it(): void
    {
        $plan = Plan::create([
            'slug' => 'plan-tsoff-' . bin2hex(random_bytes(4)),
            'name' => 'Plan TSOFF',
            'feature_flags' => json_encode(['reports' => true, 'timesheets' => false]),
        ]);
        $company = Company::create(['name' => 'Acme TSOFF Co', 'email' => 'tsoff@example.com', 'plan_id' => $plan->id]);
        $owner = $this->makeUser($company, 'owner', 'TSOFF');
        $project = $this->makeProjectWithBaseline($company, 'TSOFF', 0.0);

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/timesheets', [
            'user_id' => $owner->id, 'work_date' => now()->format('Y-m-d'), 'hours' => 4,
        ]);
        $response->assertRedirect('/app/billing');
        $this->assertSame(0, TimesheetEntry::where('project_id', $project->id)->count());
    }
}
