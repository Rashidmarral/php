<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyItem;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Plan;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TakeoffPage;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers linking a company Assembly to a "length"/"area" TakeoffMeasurement
 * (takeoff_measurements.assembly_id) and how that expands into multiple scaled
 * EstimateItem rows on conversion — see TakeoffController::addMeasurement()'s
 * server-computed unit_cost override and convertToEstimate()/addAssemblyLineItems()'s
 * qty = round(qty_per_unit * value, 2) scaling, mirroring
 * app.partials.assembly-picker's client-side math for the Estimate-side flow.
 */
class TakeoffAssemblyTest extends TestCase
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
            'feature_flags' => json_encode(['takeoff' => true]),
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

    private function makeTakeoffWithPage(int $companyId, string $name = 'Plan A'): array
    {
        $takeoff = Takeoff::create(['company_id' => $companyId, 'name' => $name]);
        $page = TakeoffPage::create([
            'takeoff_id' => $takeoff->id,
            'page_number' => 1,
            'image_path' => '/uploads/takeoffs/x/x.jpg',
            'scale_px_per_unit' => 1,
            'scale_unit' => 'm',
        ]);

        return [$takeoff, $page];
    }

    /** "Standard Interior Wall": framing + drywall + paint, computed unit cost 16.00 per lm. */
    private function makeWallAssembly(int $companyId): Assembly
    {
        $assembly = Assembly::create([
            'company_id' => $companyId,
            'name' => 'Standard Interior Wall',
            'base_uom' => 'lm',
            'is_active' => true,
        ]);

        AssemblyItem::create(['assembly_id' => $assembly->id, 'description' => 'Framing', 'item_type' => 'material', 'uom' => 'lm', 'qty_per_unit' => 1, 'unit_cost' => 5, 'sort_order' => 0]);
        AssemblyItem::create(['assembly_id' => $assembly->id, 'description' => 'Drywall', 'item_type' => 'material', 'uom' => 'sheet', 'qty_per_unit' => 2, 'unit_cost' => 3, 'sort_order' => 1]);
        AssemblyItem::create(['assembly_id' => $assembly->id, 'description' => 'Paint', 'item_type' => 'material', 'uom' => 'L', 'qty_per_unit' => 0.5, 'unit_cost' => 10, 'sort_order' => 2]);

        return $assembly;
    }

    public function test_measurement_with_no_assembly_converts_1_to_1_exactly_as_before(): void
    {
        $a = $this->makeCompanyWithOwner('AsmNone');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'length', 'label' => 'North Wall', 'value' => 10, 'unit_cost' => 25,
        ])->assertStatus(200);
        $measurement = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'North Wall')->first();
        $this->assertNull($measurement->assembly_id);
        $this->assertSame('25.00', $measurement->unit_cost);

        $response = $this->actingAs($a['owner'])->post("/app/takeoffs/{$takeoff->id}/convert");
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $response->assertRedirect('/app/estimates/'.$estimate->id);

        $items = EstimateItem::where('estimate_id', $estimate->id)->get();
        $this->assertCount(1, $items);
        $item = $items->first();
        $this->assertSame('North Wall (10.00 m)', $item->description);
        $this->assertSame('10.00', $item->qty);
        $this->assertSame('25.00', $item->unit_cost);
        $this->assertSame('250.00', $item->total);
        $this->assertNull($item->section_title);
    }

    public function test_measurement_with_assembly_auto_fills_unit_cost_and_expands_on_conversion(): void
    {
        $a = $this->makeCompanyWithOwner('AsmLink');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);
        $assembly = $this->makeWallAssembly($a['company']->id);

        // A client-submitted unit_cost of 999 must be ignored in favor of the assembly's
        // own computed cost (1*5 + 2*3 + 0.5*10 = 16) — the field is read-only on the
        // client while an assembly is selected, and the server is the authority anyway.
        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'length', 'label' => 'South Wall', 'value' => 10, 'unit_cost' => 999,
            'assembly_id' => $assembly->id,
        ]);
        $response->assertStatus(200)->assertJson(['ok' => true]);

        $measurement = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'South Wall')->first();
        $this->assertSame($assembly->id, $measurement->assembly_id);
        $this->assertSame('16.00', $measurement->unit_cost);
        $this->assertSame('160.00', $measurement->total_cost); // 10 * 16

        $response = $this->actingAs($a['owner'])->post("/app/takeoffs/{$takeoff->id}/convert");
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $response->assertRedirect('/app/estimates/'.$estimate->id);
        $this->assertSame('160.00', $estimate->total);

        $items = EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get();
        $this->assertCount(3, $items, 'One EstimateItem per AssemblyItem.');

        $framing = $items->firstWhere('description', 'Framing');
        $this->assertNotNull($framing);
        $this->assertSame('10.00', $framing->qty); // 1 * 10
        $this->assertSame('5.00', $framing->unit_cost);
        $this->assertSame('50.00', $framing->total);
        $this->assertSame('South Wall', $framing->section_title);

        $drywall = $items->firstWhere('description', 'Drywall');
        $this->assertSame('20.00', $drywall->qty); // 2 * 10
        $this->assertSame('3.00', $drywall->unit_cost);
        $this->assertSame('60.00', $drywall->total);
        $this->assertSame('South Wall', $drywall->section_title);

        $paint = $items->firstWhere('description', 'Paint');
        $this->assertSame('5.00', $paint->qty); // 0.5 * 10
        $this->assertSame('10.00', $paint->unit_cost);
        $this->assertSame('50.00', $paint->total);
        $this->assertSame('South Wall', $paint->section_title);

        $this->assertSame(50.0 + 60.0 + 50.0, (float) $framing->total + (float) $drywall->total + (float) $paint->total);
    }

    public function test_count_measurement_cannot_have_an_assembly_linked(): void
    {
        $a = $this->makeCompanyWithOwner('AsmCount');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);
        $assembly = $this->makeWallAssembly($a['company']->id);

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'count', 'label' => 'Fixtures', 'value' => 4, 'unit_cost' => 50,
            'assembly_id' => $assembly->id,
        ]);
        $response->assertStatus(422);
        $this->assertSame(0, TakeoffMeasurement::where('label', 'Fixtures')->count());
    }

    public function test_cutout_cannot_have_an_assembly_linked(): void
    {
        $a = $this->makeCompanyWithOwner('AsmCutout');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);
        $assembly = $this->makeWallAssembly($a['company']->id);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Bedroom Floor', 'value' => 22, 'unit_cost' => 10,
        ])->assertStatus(200);
        $parent = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Bedroom Floor')->first();

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Closet Nook', 'value' => 3.5, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $parent->id,
            'assembly_id' => $assembly->id,
        ]);
        $response->assertStatus(422);
        $this->assertSame(0, TakeoffMeasurement::where('label', 'Closet Nook')->count());
    }

    public function test_assembly_from_another_company_cannot_be_linked_to_a_measurement(): void
    {
        $a = $this->makeCompanyWithOwner('AsmCrossTenantA');
        $b = $this->makeCompanyWithOwner('AsmCrossTenantB');
        [$takeoffA, $pageA] = $this->makeTakeoffWithPage($a['company']->id, 'Company A Plan');
        $assemblyB = $this->makeWallAssembly($b['company']->id);

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoffA->id}/pages/{$pageA->id}/measurements", [
            'type' => 'length', 'label' => 'Sneaky Wall', 'value' => 10, 'unit_cost' => 1,
            'assembly_id' => $assemblyB->id,
        ]);
        $response->assertStatus(404);
        $this->assertSame(0, TakeoffMeasurement::where('label', 'Sneaky Wall')->count());
    }
}
