<?php

namespace Tests\Feature;

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
 * Covers cutout measurements: subtracting one area measurement (e.g. a closet nook, a
 * window opening) from another (e.g. a bedroom floor, a wall) via
 * parent_measurement_id/is_cutout/gross_value on TakeoffMeasurement. See
 * TakeoffController::addMeasurement()/deleteMeasurement()/recomputeParentForCutouts()
 * for the write-path math this exercises, and convertToEstimate()'s exclusion of cutout
 * rows as their own line items.
 */
class TakeoffCutoutTest extends TestCase
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

    public function test_creating_a_cutout_reduces_the_parents_net_value_and_sets_gross_value(): void
    {
        $a = $this->makeCompanyWithOwner('CutoutBasic');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);

        // Parent: a 22 m² bedroom floor at 10 SAR/m².
        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Bedroom Floor', 'value' => 22, 'unit_cost' => 10,
        ])->assertStatus(200);
        $parent = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Bedroom Floor')->first();
        $this->assertNull($parent->gross_value);

        // Cutout: a 3.5 m² closet nook, cut from the bedroom floor.
        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Closet Nook', 'value' => 3.5, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $parent->id,
        ]);
        $response->assertStatus(200)->assertJson(['ok' => true]);

        $cutout = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Closet Nook')->first();
        $this->assertNotNull($cutout);
        $this->assertTrue((bool) $cutout->is_cutout);
        $this->assertSame($parent->id, $cutout->parent_measurement_id);
        $this->assertSame('3.500', $cutout->value);

        $parent->refresh();
        $this->assertSame('22.000', $parent->gross_value);
        $this->assertSame('18.500', $parent->value);
        $this->assertSame('185.00', $parent->total_cost); // 18.5 * 10
    }

    public function test_deleting_a_cutout_restores_the_parents_original_value(): void
    {
        $a = $this->makeCompanyWithOwner('CutoutDelete');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Bedroom Floor', 'value' => 22, 'unit_cost' => 10,
        ])->assertStatus(200);
        $parent = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Bedroom Floor')->first();

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Closet Nook', 'value' => 3.5, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $parent->id,
        ])->assertStatus(200);
        $cutout = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Closet Nook')->first();

        $parent->refresh();
        $this->assertSame('18.500', $parent->value);

        $this->actingAs($a['owner'])->post("/app/takeoffs/{$takeoff->id}/measurements/{$cutout->id}/delete")
            ->assertRedirect();

        $this->assertNull(TakeoffMeasurement::find($cutout->id));
        $parent->refresh();
        $this->assertSame('22.000', $parent->value);
        $this->assertSame('220.00', $parent->total_cost);
    }

    public function test_a_second_cutout_on_the_same_parent_stacks_correctly(): void
    {
        $a = $this->makeCompanyWithOwner('CutoutStack');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Great Room Floor', 'value' => 50, 'unit_cost' => 8,
        ])->assertStatus(200);
        $parent = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Great Room Floor')->first();

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Column One', 'value' => 2, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $parent->id,
        ])->assertStatus(200);
        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Column Two', 'value' => 1.5, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $parent->id,
        ])->assertStatus(200);

        $parent->refresh();
        $this->assertSame('50.000', $parent->gross_value);
        // 50 - (2 + 1.5) = 46.5
        $this->assertSame('46.500', $parent->value);
        $this->assertSame('372.00', $parent->total_cost); // 46.5 * 8
        $this->assertSame(2, TakeoffMeasurement::where('parent_measurement_id', $parent->id)->where('is_cutout', true)->count());
    }

    public function test_cutout_on_a_length_measurement_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('CutoutOnLength');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'length', 'label' => 'North Wall', 'value' => 10, 'unit_cost' => 5,
        ])->assertStatus(200);
        $wall = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'North Wall')->first();

        // Cutting a length measurement itself: rejected because type must be 'area' to be a cutout.
        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'length', 'label' => 'Bad Cutout', 'value' => 2, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $wall->id,
        ]);
        $response->assertStatus(422);
        $this->assertSame(0, TakeoffMeasurement::where('label', 'Bad Cutout')->count());

        // Cutting FROM a length measurement: rejected because the parent must be type 'area'.
        $response2 = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Bad Cutout 2', 'value' => 2, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $wall->id,
        ]);
        $response2->assertStatus(422);
        $this->assertSame(0, TakeoffMeasurement::where('label', 'Bad Cutout 2')->count());
    }

    public function test_cutout_on_a_count_measurement_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('CutoutOnCount');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'count', 'label' => 'Fixtures', 'value' => 4, 'unit_cost' => 50,
        ])->assertStatus(200);
        $fixtures = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Fixtures')->first();

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Bad Cutout', 'value' => 1, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $fixtures->id,
        ]);
        $response->assertStatus(422);
        $this->assertSame(0, TakeoffMeasurement::where('label', 'Bad Cutout')->count());
    }

    public function test_cutout_targeting_a_parent_on_a_different_page_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('CutoutCrossPage');
        $takeoff = Takeoff::create(['company_id' => $a['company']->id, 'name' => 'Two Page Plan']);
        $page1 = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 1, 'image_path' => '/uploads/takeoffs/x/1.jpg', 'scale_px_per_unit' => 1, 'scale_unit' => 'm']);
        $page2 = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 2, 'image_path' => '/uploads/takeoffs/x/2.jpg', 'scale_px_per_unit' => 1, 'scale_unit' => 'm']);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page1->id}/measurements", [
            'type' => 'area', 'label' => 'Page One Room', 'value' => 20, 'unit_cost' => 10,
        ])->assertStatus(200);
        $parent = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Page One Room')->first();

        // Attempt a cutout on page 2 targeting a parent that lives on page 1.
        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page2->id}/measurements", [
            'type' => 'area', 'label' => 'Cross Page Cutout', 'value' => 2, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $parent->id,
        ]);
        $response->assertStatus(404);
        $this->assertSame(0, TakeoffMeasurement::where('label', 'Cross Page Cutout')->count());

        $parent->refresh();
        $this->assertSame('20.000', $parent->value);
        $this->assertNull($parent->gross_value);
    }

    public function test_cutout_targeting_another_companys_measurement_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('CutoutCrossTenantA');
        $b = $this->makeCompanyWithOwner('CutoutCrossTenantB');

        [$takeoffA, $pageA] = $this->makeTakeoffWithPage($a['company']->id, 'Company A Plan');
        [$takeoffB, $pageB] = $this->makeTakeoffWithPage($b['company']->id, 'Company B Plan');

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoffA->id}/pages/{$pageA->id}/measurements", [
            'type' => 'area', 'label' => 'Company A Room', 'value' => 30, 'unit_cost' => 10,
        ])->assertStatus(200);
        $parentA = TakeoffMeasurement::where('takeoff_id', $takeoffA->id)->where('label', 'Company A Room')->first();

        // Company B's owner tries to attach a cutout, on their OWN takeoff/page, that
        // targets company A's measurement as the parent — must 404, never touch it.
        $response = $this->actingAs($b['owner'])->postJson("/app/takeoffs/{$takeoffB->id}/pages/{$pageB->id}/measurements", [
            'type' => 'area', 'label' => 'Sneaky Cutout', 'value' => 2, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $parentA->id,
        ]);
        $response->assertStatus(404);
        $this->assertSame(0, TakeoffMeasurement::where('label', 'Sneaky Cutout')->count());

        $parentA->refresh();
        $this->assertSame('30.000', $parentA->value);
        $this->assertNull($parentA->gross_value);
    }

    public function test_convert_to_estimate_excludes_cutouts_as_line_items_but_reflects_net_value(): void
    {
        $a = $this->makeCompanyWithOwner('CutoutConvert');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Living Room Floor', 'value' => 40, 'unit_cost' => 15,
        ])->assertStatus(200);
        $parent = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Living Room Floor')->first();

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'area', 'label' => 'Fireplace Hearth', 'value' => 5, 'unit_cost' => 0,
            'is_cutout' => '1', 'parent_measurement_id' => $parent->id,
        ])->assertStatus(200);

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'count', 'label' => 'Light Fixtures', 'value' => 3, 'unit_cost' => 20,
        ])->assertStatus(200);

        $parent->refresh();
        $this->assertSame('525.00', $parent->total_cost); // (40 - 5) * 15

        $response = $this->actingAs($a['owner'])->post("/app/takeoffs/{$takeoff->id}/convert");

        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $response->assertRedirect('/app/estimates/'.$estimate->id);

        // Total = parent's adjusted net total_cost (525) + the count line (60) = 585;
        // the cutout itself contributes nothing of its own.
        $this->assertSame('585.00', $estimate->total);

        $items = EstimateItem::where('estimate_id', $estimate->id)->get();
        $this->assertCount(2, $items, 'The cutout must not become its own estimate line item.');
        $this->assertTrue($items->contains(fn ($i) => str_contains($i->description, 'Living Room Floor')));
        $this->assertTrue($items->contains(fn ($i) => str_contains($i->description, 'Light Fixtures')));
        $this->assertFalse($items->contains(fn ($i) => str_contains($i->description, 'Fireplace Hearth')));

        $livingRoomItem = $items->first(fn ($i) => str_contains($i->description, 'Living Room Floor'));
        $this->assertSame('35.00', $livingRoomItem->qty); // net value, not the gross 40
        $this->assertSame('525.00', $livingRoomItem->total);
    }
}
