<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyItem;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Plan;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers AssemblyController's CRUD (create/store/edit/update/destroy),
 * including the cross-tenant ownership check on edit/update/destroy —
 * mirroring MaterialNumericValidationTest's
 * test_store_never_attaches_another_companys_supplier-style cross-tenant
 * check — and proves end-to-end that an estimate submitted with
 * assembly-expanded line items (exactly what the assembly-picker's JS would
 * post: multiple item_description[]/item_qty[]/... entries, each item's qty
 * already scaled client-side) persists correctly through the existing
 * EstimateController::store() path, with no new server-side code needed
 * for that part.
 */
class AssemblyTest extends TestCase
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

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-'.strtolower($tag).'-'.bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['materials' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag).'@example.com',
            'plan_id' => $plan->id,
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag).'-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    public function test_store_creates_an_assembly_with_its_items(): void
    {
        $a = $this->makeCompanyWithOwner('AsmStore');

        $response = $this->actingAs($a['owner'])->post('/app/assemblies', [
            'name' => 'Standard Interior Wall',
            'name_ar' => 'جدار داخلي قياسي',
            'base_uom' => 'lm',
            'description' => 'Framing + drywall + insulation + paint',
            'item_description' => ['2x4 stud', 'Drywall sheet', 'Paint'],
            'item_description_ar' => ['', '', ''],
            'item_type' => ['material', 'material', 'material'],
            'item_uom' => ['each', 'sheet', 'L'],
            'item_qty_per_unit' => ['0.67', '0.5', '0.3'],
            'item_cost' => ['12.00', '45.00', '30.00'],
        ]);

        $response->assertRedirect('/app/assemblies');
        $assembly = Assembly::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($assembly);
        $this->assertSame('Standard Interior Wall', $assembly->name);
        $this->assertSame('lm', $assembly->base_uom);
        $this->assertSame(3, AssemblyItem::where('assembly_id', $assembly->id)->count());
        $stud = AssemblyItem::where('assembly_id', $assembly->id)->where('description', '2x4 stud')->first();
        $this->assertSame('0.6700', $stud->qty_per_unit);
        $this->assertSame('12.00', $stud->unit_cost);
    }

    public function test_store_rejects_blank_name(): void
    {
        $a = $this->makeCompanyWithOwner('AsmBlankName');

        $response = $this->actingAs($a['owner'])->post('/app/assemblies', [
            'name' => '   ',
            'base_uom' => 'lm',
            'item_description' => ['Stud'],
            'item_qty_per_unit' => ['1'],
        ]);

        $response->assertRedirect('/app/assemblies/create');
        $this->assertSame(0, Assembly::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_missing_base_uom(): void
    {
        $a = $this->makeCompanyWithOwner('AsmNoUom');

        $response = $this->actingAs($a['owner'])->post('/app/assemblies', [
            'name' => 'Some Assembly',
            'base_uom' => '',
            'item_description' => ['Stud'],
            'item_qty_per_unit' => ['1'],
        ]);

        $response->assertRedirect('/app/assemblies/create');
        $this->assertSame(0, Assembly::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_an_assembly_with_no_items(): void
    {
        $a = $this->makeCompanyWithOwner('AsmNoItems');

        $response = $this->actingAs($a['owner'])->post('/app/assemblies', [
            'name' => 'Empty Assembly',
            'base_uom' => 'lm',
        ]);

        $response->assertRedirect('/app/assemblies/create');
        $this->assertSame(0, Assembly::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_an_invalid_qty_per_unit(): void
    {
        $a = $this->makeCompanyWithOwner('AsmBadQty');

        $response = $this->actingAs($a['owner'])->post('/app/assemblies', [
            'name' => 'Some Assembly',
            'base_uom' => 'lm',
            'item_description' => ['Stud'],
            'item_qty_per_unit' => ['not-a-number'],
            'item_cost' => ['12.00'],
        ]);

        $response->assertRedirect('/app/assemblies/create');
        $this->assertSame(0, Assembly::where('company_id', $a['company']->id)->count());
    }

    public function test_update_replaces_the_items_wholesale(): void
    {
        $a = $this->makeCompanyWithOwner('AsmUpdate');
        $assembly = Assembly::create([
            'company_id' => $a['company']->id,
            'name' => 'Old Name',
            'base_uom' => 'lm',
        ]);
        AssemblyItem::create([
            'assembly_id' => $assembly->id,
            'description' => 'Old item',
            'item_type' => 'material',
            'uom' => 'each',
            'qty_per_unit' => 1,
            'unit_cost' => 5,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/assemblies/'.$assembly->id, [
            'name' => 'New Name',
            'base_uom' => 'sqm',
            'item_description' => ['New item A', 'New item B'],
            'item_qty_per_unit' => ['2', '3'],
            'item_cost' => ['10', '20'],
        ]);

        $response->assertRedirect('/app/assemblies');
        $assembly->refresh();
        $this->assertSame('New Name', $assembly->name);
        $this->assertSame('sqm', $assembly->base_uom);
        $items = AssemblyItem::where('assembly_id', $assembly->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $items);
        $this->assertSame('New item A', $items[0]->description);
        $this->assertSame('New item B', $items[1]->description);
    }

    public function test_cross_tenant_edit_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('AsmCrossEditA');
        $b = $this->makeCompanyWithOwner('AsmCrossEditB');
        $othersAssembly = Assembly::create([
            'company_id' => $b['company']->id,
            'name' => "B's Assembly",
            'base_uom' => 'lm',
        ]);

        $response = $this->actingAs($a['owner'])->get('/app/assemblies/'.$othersAssembly->id.'/edit');

        $response->assertStatus(404);
    }

    public function test_cross_tenant_update_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('AsmCrossUpdateA');
        $b = $this->makeCompanyWithOwner('AsmCrossUpdateB');
        $othersAssembly = Assembly::create([
            'company_id' => $b['company']->id,
            'name' => "B's Assembly",
            'base_uom' => 'lm',
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/assemblies/'.$othersAssembly->id, [
            'name' => 'Hijacked Name',
            'base_uom' => 'lm',
            'item_description' => ['Item'],
            'item_qty_per_unit' => ['1'],
        ]);

        $response->assertStatus(404);
        $othersAssembly->refresh();
        $this->assertSame("B's Assembly", $othersAssembly->name);
    }

    public function test_cross_tenant_destroy_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('AsmCrossDestroyA');
        $b = $this->makeCompanyWithOwner('AsmCrossDestroyB');
        $othersAssembly = Assembly::create([
            'company_id' => $b['company']->id,
            'name' => "B's Assembly",
            'base_uom' => 'lm',
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/assemblies/'.$othersAssembly->id.'/delete');

        $response->assertStatus(404);
        $this->assertNotNull(Assembly::find($othersAssembly->id));
    }

    public function test_destroy_removes_the_assembly_and_its_items(): void
    {
        $a = $this->makeCompanyWithOwner('AsmDestroy');
        $assembly = Assembly::create([
            'company_id' => $a['company']->id,
            'name' => 'To Delete',
            'base_uom' => 'lm',
        ]);
        AssemblyItem::create([
            'assembly_id' => $assembly->id,
            'description' => 'Item',
            'item_type' => 'material',
            'uom' => 'each',
            'qty_per_unit' => 1,
            'unit_cost' => 5,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/assemblies/'.$assembly->id.'/delete');

        $response->assertRedirect('/app/assemblies');
        $this->assertNull(Assembly::find($assembly->id));
        $this->assertSame(0, AssemblyItem::where('assembly_id', $assembly->id)->count());
    }

    public function test_create_and_edit_forms_render(): void
    {
        $a = $this->makeCompanyWithOwner('AsmForms');
        $assembly = Assembly::create([
            'company_id' => $a['company']->id,
            'name' => 'Standard Interior Wall',
            'base_uom' => 'lm',
        ]);
        AssemblyItem::create([
            'assembly_id' => $assembly->id,
            'description' => '2x4 stud',
            'item_type' => 'material',
            'uom' => 'each',
            'qty_per_unit' => 0.67,
            'unit_cost' => 12,
        ]);

        $this->actingAs($a['owner'])->get('/app/assemblies/create')->assertOk();
        $this->actingAs($a['owner'])->get('/app/assemblies/'.$assembly->id.'/edit')
            ->assertOk()
            ->assertSee('Standard Interior Wall')
            ->assertSee('2x4 stud');
    }

    /**
     * The estimate create/edit pages embed the assembly picker (see
     * app.partials.assembly-picker) — this renders both end to end with a
     * real assembly in scope, proving the "Insert assembly" button, the
     * picker's data-items JSON, and the assembly-items-picked listener all
     * make it into the page with no view/JS error.
     */
    public function test_estimate_create_page_renders_the_assembly_picker(): void
    {
        $a = $this->makeCompanyWithOwner('AsmPickerRender');
        $assembly = Assembly::create([
            'company_id' => $a['company']->id,
            'name' => 'Standard Interior Wall',
            'base_uom' => 'lm',
        ]);
        AssemblyItem::create([
            'assembly_id' => $assembly->id,
            'description' => '2x4 stud',
            'item_type' => 'material',
            'uom' => 'each',
            'qty_per_unit' => 0.67,
            'unit_cost' => 12,
        ]);

        $response = $this->actingAs($a['owner'])->get('/app/estimates/create');

        $response->assertOk();
        $response->assertSee('open-assembly-picker', false);
        $response->assertSee('assembly-items-picked', false);
        $response->assertSee('Standard Interior Wall');
        $response->assertSee('qtyPerUnit', false);
    }

    public function test_index_lists_only_this_companys_assemblies(): void
    {
        $a = $this->makeCompanyWithOwner('AsmIndexA');
        $b = $this->makeCompanyWithOwner('AsmIndexB');
        Assembly::create(['company_id' => $a['company']->id, 'name' => 'Mine', 'base_uom' => 'lm']);
        Assembly::create(['company_id' => $b['company']->id, 'name' => 'Not mine', 'base_uom' => 'lm']);

        $response = $this->actingAs($a['owner'])->get('/app/assemblies');

        $response->assertOk();
        $response->assertSee('Mine');
        $response->assertDontSee('Not mine');
    }

    /**
     * Simulates exactly what the assembly-picker's JS posts once a user
     * applies an assembly at some quantity: multiple item_description[]/
     * item_qty[]/item_cost[]/... entries, each qty already scaled
     * client-side as qty_per_unit * appliedQty rounded to 2dp — here, a
     * "Standard Interior Wall" assembly (stud 0.67/lm, drywall 0.5/lm,
     * paint 0.3/lm) applied at 20 lm, so the posted quantities are 13.40,
     * 10.00 and 6.00. No new server-side code is involved: this proves the
     * existing EstimateController::store() line-item path (the same one
     * every hand-typed row already uses) persists them correctly.
     */
    public function test_estimate_store_persists_assembly_expanded_line_items(): void
    {
        $a = $this->makeCompanyWithOwner('AsmEstimate');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            'title' => 'Villa Interior Walls',
            'item_description' => ['2x4 stud', 'Drywall sheet', 'Paint'],
            'item_description_ar' => ['', '', ''],
            'item_type' => ['material', 'material', 'material'],
            'item_qty' => ['13.40', '10.00', '6.00'],
            'item_uom' => ['each', 'sheet', 'L'],
            'item_cost' => ['12.00', '45.00', '30.00'],
            'item_optional' => ['0', '0', '0'],
        ]);

        $response->assertRedirect();
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $items = EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get();
        $this->assertCount(3, $items);
        $this->assertSame('2x4 stud', $items[0]->description);
        $this->assertSame('13.40', $items[0]->qty);
        $this->assertSame('10.00', $items[1]->qty);
        $this->assertSame('6.00', $items[2]->qty);
        // subtotal = 13.40*12 + 10*45 + 6*30 = 160.80 + 450 + 180 = 790.80
        $this->assertSame('790.80', $estimate->subtotal);
    }
}
