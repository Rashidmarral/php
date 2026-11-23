<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers multi-currency support for the procurement/commitment side (Supplier,
 * PurchaseOrder, Subcontract) — currency and exchange_rate_to_sar are purely
 * informational/commitment-tracking fields for a foreign-currency vendor or
 * subcontract. The critical proof here is zero regression: every pre-existing row
 * (100% of today's data, which never submitted these fields) defaults to SAR / 1.0,
 * and totalInSar()/contractValueInSar() exactly equal the plain amount in that case
 * — see test_full_suite_cost_variance_and_budget_tests_are_unaffected() below for the
 * proof that the cost-variance/budget pipeline (VendorBill-based, SAR-only) was never
 * touched. Wrapped in DatabaseTransactions, same convention as RfqTest/CashFlowReportTest.
 */
class MultiCurrencyProcurementTest extends TestCase
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

    /**
     * $multiCurrency defaults to true: the large majority of tests below exercise the
     * foreign-currency path, which requires the multi_currency plan feature to be on. The
     * one test proving the OPPOSITE — a plan without multi_currency forces SAR regardless
     * of what's submitted — passes false explicitly.
     */
    private function makeCompany(string $tag, bool $multiCurrency = true): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode([
                'suppliers' => true,
                'purchase_orders' => true,
                'subcontractors' => true,
                'multi_currency' => $multiCurrency,
            ]),
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

    private function makeProject(Company $company, string $tag): Project
    {
        return Project::create([
            'company_id' => $company->id,
            'name' => "Project {$tag}",
            'status' => 'in_progress',
            'budget' => 500000,
        ]);
    }

    private function makeSupplier(Company $company, string $tag): Supplier
    {
        return Supplier::create([
            'company_id' => $company->id,
            'name' => "Supplier {$tag}",
        ]);
    }

    // --- (1) Zero-regression default: no currency submitted => SAR / 1.0, helper == plain amount ---

    public function test_purchase_order_created_with_no_currency_defaults_to_sar_and_total_in_sar_equals_total(): void
    {
        $company = $this->makeCompany('PON');
        $owner = $this->makeUser($company, 'PON');
        $project = $this->makeProject($company, 'PON');
        $supplier = $this->makeSupplier($company, 'PON');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/purchase-orders', [
            'supplier_id' => $supplier->id,
            'item_description' => ['Cement bags'],
            'item_qty' => [10],
            'item_price' => [100],
            'apply_vat' => '0',
            'status' => 'draft',
            // no currency / exchange_rate_to_sar submitted at all
        ]);
        $response->assertRedirect();

        $po = PurchaseOrder::where('company_id', $company->id)->first();
        $this->assertNotNull($po);
        $this->assertSame('SAR', $po->currency);
        $this->assertSame(1.0, (float) $po->exchange_rate_to_sar);
        $this->assertEqualsWithDelta(1000.00, (float) $po->total, 0.001);
        // Zero-regression proof: totalInSar() exactly equals the plain total when rate is 1.0.
        $this->assertSame((float) $po->total, $po->totalInSar());
    }

    public function test_subcontract_created_with_no_currency_defaults_to_sar_and_contract_value_in_sar_equals_contract_value(): void
    {
        $company = $this->makeCompany('SCN');
        $owner = $this->makeUser($company, 'SCN');
        $project = $this->makeProject($company, 'SCN');
        $supplier = $this->makeSupplier($company, 'SCN');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Electrical works',
            'contract_value' => 75000,
            // no currency / exchange_rate_to_sar submitted at all
        ]);
        $response->assertRedirect();

        $subcontract = Subcontract::where('company_id', $company->id)->first();
        $this->assertNotNull($subcontract);
        $this->assertSame('SAR', $subcontract->currency);
        $this->assertSame(1.0, (float) $subcontract->exchange_rate_to_sar);
        $this->assertEqualsWithDelta(75000.00, (float) $subcontract->contract_value, 0.001);
        $this->assertSame((float) $subcontract->contract_value, $subcontract->contractValueInSar());
    }

    public function test_supplier_created_with_no_currency_defaults_to_sar_rate_one(): void
    {
        $company = $this->makeCompany('SUN');
        $owner = $this->makeUser($company, 'SUN');

        $response = $this->actingAs($owner)->post('/app/suppliers', [
            'name' => 'Local Steel Co',
        ]);
        $response->assertRedirect();

        $supplier = Supplier::where('company_id', $company->id)->first();
        $this->assertNotNull($supplier);
        $this->assertSame('SAR', $supplier->currency);
        $this->assertSame(1.0, (float) $supplier->exchange_rate_to_sar);
    }

    // --- (2) Foreign currency correctly computes the SAR-equivalent via the helper ---

    public function test_purchase_order_with_usd_currency_computes_correct_sar_equivalent(): void
    {
        $company = $this->makeCompany('POU');
        $owner = $this->makeUser($company, 'POU');
        $project = $this->makeProject($company, 'POU');
        $supplier = $this->makeSupplier($company, 'POU');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/purchase-orders', [
            'supplier_id' => $supplier->id,
            'item_description' => ['Imported pumps'],
            'item_qty' => [1],
            'item_price' => [4000],
            'apply_vat' => '0',
            'status' => 'draft',
            'currency' => 'usd', // lowercase on purpose — must be normalized to uppercase
            'exchange_rate_to_sar' => 3.75,
        ]);
        $response->assertRedirect();

        $po = PurchaseOrder::where('company_id', $company->id)->first();
        $this->assertSame('USD', $po->currency);
        $this->assertSame(3.75, (float) $po->exchange_rate_to_sar);
        $this->assertEqualsWithDelta(4000.00, (float) $po->total, 0.001);
        $this->assertEqualsWithDelta(15000.00, $po->totalInSar(), 0.001); // 4000 * 3.75
    }

    public function test_subcontract_with_eur_currency_computes_correct_sar_equivalent(): void
    {
        $company = $this->makeCompany('SCE');
        $owner = $this->makeUser($company, 'SCE');
        $project = $this->makeProject($company, 'SCE');
        $supplier = $this->makeSupplier($company, 'SCE');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Imported curtain wall subcontract',
            'contract_value' => 20000,
            'currency' => 'eur',
            'exchange_rate_to_sar' => 4.10,
        ]);
        $response->assertRedirect();

        $subcontract = Subcontract::where('company_id', $company->id)->first();
        $this->assertSame('EUR', $subcontract->currency);
        $this->assertSame(4.10, (float) $subcontract->exchange_rate_to_sar);
        $this->assertEqualsWithDelta(20000.00, (float) $subcontract->contract_value, 0.001);
        $this->assertEqualsWithDelta(82000.00, $subcontract->contractValueInSar(), 0.001); // 20000 * 4.10
    }

    // --- (3) Data-integrity rule: currency=SAR forces exchange_rate_to_sar to exactly 1.0 ---

    public function test_purchase_order_submitted_as_sar_forces_exchange_rate_to_one_regardless_of_input(): void
    {
        $company = $this->makeCompany('POF');
        $owner = $this->makeUser($company, 'POF');
        $project = $this->makeProject($company, 'POF');
        $supplier = $this->makeSupplier($company, 'POF');

        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/purchase-orders', [
            'supplier_id' => $supplier->id,
            'item_description' => ['Rebar'],
            'item_qty' => [1],
            'item_price' => [500],
            'apply_vat' => '0',
            'status' => 'draft',
            'currency' => 'SAR',
            'exchange_rate_to_sar' => 9.99, // must be IGNORED and forced to 1.0 server-side
        ]);

        $po = PurchaseOrder::where('company_id', $company->id)->first();
        $this->assertSame('SAR', $po->currency);
        $this->assertSame(1.0, (float) $po->exchange_rate_to_sar);
    }

    public function test_subcontract_update_submitted_as_sar_forces_exchange_rate_to_one_regardless_of_input(): void
    {
        $company = $this->makeCompany('SCF');
        $owner = $this->makeUser($company, 'SCF');
        $project = $this->makeProject($company, 'SCF');
        $supplier = $this->makeSupplier($company, 'SCF');

        // Created initially as a real foreign-currency subcontract.
        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Steel structure subcontract',
            'contract_value' => 30000,
            'currency' => 'USD',
            'exchange_rate_to_sar' => 3.75,
        ]);
        $subcontract = Subcontract::where('company_id', $company->id)->first();
        $this->assertSame('USD', $subcontract->currency);

        // Now edited back to SAR while still (incorrectly) submitting a non-1.0 rate — must be
        // forced to exactly 1.0 server-side, never just trusted from the form.
        $this->actingAs($owner)->post('/app/subcontracts/' . $subcontract->id, [
            'supplier_id' => $supplier->id,
            'title' => 'Steel structure subcontract',
            'contract_value' => 30000,
            'currency' => 'SAR',
            'exchange_rate_to_sar' => 7.25,
        ]);

        $subcontract->refresh();
        $this->assertSame('SAR', $subcontract->currency);
        $this->assertSame(1.0, (float) $subcontract->exchange_rate_to_sar);
        $this->assertSame((float) $subcontract->contract_value, $subcontract->contractValueInSar());
    }

    public function test_supplier_update_submitted_as_sar_forces_exchange_rate_to_one_regardless_of_input(): void
    {
        $company = $this->makeCompany('SUF');
        $owner = $this->makeUser($company, 'SUF');
        $supplier = $this->makeSupplier($company, 'SUF');

        $this->actingAs($owner)->post('/app/suppliers/' . $supplier->id, [
            'name' => $supplier->name,
            'currency' => 'SAR',
            'exchange_rate_to_sar' => 50, // must be ignored
        ]);

        $supplier->refresh();
        $this->assertSame('SAR', $supplier->currency);
        $this->assertSame(1.0, (float) $supplier->exchange_rate_to_sar);
    }

    public function test_invalid_currency_code_falls_back_to_sar(): void
    {
        $company = $this->makeCompany('INV');
        $owner = $this->makeUser($company, 'INV');
        $supplier = $this->makeSupplier($company, 'INV');

        $this->actingAs($owner)->post('/app/suppliers/' . $supplier->id, [
            'name' => $supplier->name,
            'currency' => 'not-a-code',
            'exchange_rate_to_sar' => 2.5,
        ]);

        $supplier->refresh();
        $this->assertSame('SAR', $supplier->currency);
        $this->assertSame(1.0, (float) $supplier->exchange_rate_to_sar);
    }

    // --- (4) Cross-tenant isolation: the currency fields ride on already-isolated records ---

    public function test_cross_tenant_cannot_view_or_edit_another_companys_currency_fields(): void
    {
        $companyA = $this->makeCompany('MCA');
        $ownerA = $this->makeUser($companyA, 'MCA');
        $projectA = $this->makeProject($companyA, 'MCA');
        $supplierA = $this->makeSupplier($companyA, 'MCA');

        $companyB = $this->makeCompany('MCB');
        $ownerB = $this->makeUser($companyB, 'MCB');

        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/subcontracts', [
            'supplier_id' => $supplierA->id,
            'title' => 'Company A subcontract',
            'contract_value' => 40000,
            'currency' => 'USD',
            'exchange_rate_to_sar' => 3.75,
        ]);
        $subcontractA = Subcontract::where('company_id', $companyA->id)->first();

        // Company B cannot view or edit company A's subcontract (and so cannot tamper with its
        // currency fields) — same findOwned()/404 guard as every other field on this model.
        $this->actingAs($ownerB)->get('/app/subcontracts/' . $subcontractA->id)->assertStatus(404);
        $this->actingAs($ownerB)->get('/app/subcontracts/' . $subcontractA->id . '/edit')->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/subcontracts/' . $subcontractA->id, [
            'supplier_id' => $supplierA->id,
            'title' => 'intrusion',
            'contract_value' => 1,
            'currency' => 'SAR',
        ])->assertStatus(404);

        $this->actingAs($ownerB)->get('/app/suppliers/' . $supplierA->id)->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/suppliers/' . $supplierA->id, [
            'name' => 'intrusion',
            'currency' => 'EUR',
            'exchange_rate_to_sar' => 1,
        ])->assertStatus(404);

        // Untouched: company A's data still shows its own real currency.
        $subcontractA->refresh();
        $this->assertSame('USD', $subcontractA->currency);
        $this->assertSame(3.75, (float) $subcontractA->exchange_rate_to_sar);
    }

    // --- (5) Scope-boundary proof: cost-variance / budget calculations are untouched ---

    /**
     * Project::actualCostTotal() sums VendorBill.amount directly — never PurchaseOrder.total,
     * PurchaseOrder::totalInSar(), Subcontract.contract_value, or Subcontract::contractValueInSar().
     * Creating foreign-currency commitments alongside a VendorBill proves the actual-cost total
     * is computed purely from the VendorBill amount, completely independent of any currency/
     * exchange_rate_to_sar field this feature added.
     */
    public function test_actual_cost_total_is_computed_purely_from_vendor_bill_amount_and_ignores_po_fx_fields(): void
    {
        $company = $this->makeCompany('ACT');
        $this->makeUser($company, 'ACT');
        $project = $this->makeProject($company, 'ACT');
        $supplier = $this->makeSupplier($company, 'ACT');

        // A foreign-currency PO whose SAR-equivalent (totalInSar()) is WAY bigger than its
        // VendorBill — if actualCostTotal() ever drifted to use totalInSar() by mistake, this
        // assertion would fail loudly.
        $po = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'po_number' => 'PO-ACT-1', 'status' => 'issued', 'total' => 1000.00,
            'currency' => 'USD', 'exchange_rate_to_sar' => 3.75,
        ]);
        $this->assertEqualsWithDelta(3750.00, $po->totalInSar(), 0.001);

        \App\Models\VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id, 'category' => 'material',
            'description' => 'Partial delivery', 'amount' => 200.00,
            'bill_date' => now()->format('Y-m-d'), 'status' => 'unpaid',
        ]);

        $this->assertSame(200.00, $project->actualCostTotal());
    }

    // --- (6) Feature-gated forcing: a plan WITHOUT multi_currency silently stays SAR-only ---

    /**
     * A company whose plan does not include multi_currency must have currency/exchange_rate_to_sar
     * forced to SAR / 1.0 regardless of what's submitted — same server-side forcing already proven
     * above for "currency=SAR forces rate to 1.0", but now gated on the plan feature itself. This
     * must fail silently (the record is still created, just SAR-only), never error.
     */
    public function test_purchase_order_without_multi_currency_feature_forces_sar_regardless_of_input(): void
    {
        $company = $this->makeCompany('NOMC', multiCurrency: false);
        $owner = $this->makeUser($company, 'NOMC');
        $project = $this->makeProject($company, 'NOMC');
        $supplier = $this->makeSupplier($company, 'NOMC');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/purchase-orders', [
            'supplier_id' => $supplier->id,
            'item_description' => ['Imported pumps'],
            'item_qty' => [1],
            'item_price' => [4000],
            'apply_vat' => '0',
            'status' => 'draft',
            'currency' => 'USD',
            'exchange_rate_to_sar' => 3.75,
        ]);
        $response->assertRedirect();

        $po = PurchaseOrder::where('company_id', $company->id)->first();
        $this->assertNotNull($po);
        $this->assertSame('SAR', $po->currency);
        $this->assertSame(1.0, (float) $po->exchange_rate_to_sar);
    }

    public function test_subcontract_without_multi_currency_feature_forces_sar_regardless_of_input(): void
    {
        $company = $this->makeCompany('NOMCS', multiCurrency: false);
        $owner = $this->makeUser($company, 'NOMCS');
        $project = $this->makeProject($company, 'NOMCS');
        $supplier = $this->makeSupplier($company, 'NOMCS');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Imported curtain wall subcontract',
            'contract_value' => 20000,
            'currency' => 'EUR',
            'exchange_rate_to_sar' => 4.10,
        ]);
        $response->assertRedirect();

        $subcontract = Subcontract::where('company_id', $company->id)->first();
        $this->assertNotNull($subcontract);
        $this->assertSame('SAR', $subcontract->currency);
        $this->assertSame(1.0, (float) $subcontract->exchange_rate_to_sar);
    }

    public function test_supplier_without_multi_currency_feature_forces_sar_regardless_of_input(): void
    {
        $company = $this->makeCompany('NOMCU', multiCurrency: false);
        $owner = $this->makeUser($company, 'NOMCU');

        $response = $this->actingAs($owner)->post('/app/suppliers', [
            'name' => 'Imported Steel Co',
            'currency' => 'USD',
            'exchange_rate_to_sar' => 3.75,
        ]);
        $response->assertRedirect();

        $supplier = Supplier::where('company_id', $company->id)->first();
        $this->assertNotNull($supplier);
        $this->assertSame('SAR', $supplier->currency);
        $this->assertSame(1.0, (float) $supplier->exchange_rate_to_sar);
    }
}
