<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TakeoffPage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric validation added to TakeoffController::store()/calibrate()/
 * addMeasurement() — the takeoff name's length cap, and calibrate()'s pixel/real
 * length plus addMeasurement()'s value/unit_cost, none of which had any type or
 * range check beyond a bare <= 0 comparison before (each was only ever (float)-cast).
 * Mirrors EstimateNumericValidationTest's own shape.
 *
 * calibrate()/addMeasurement() are now page-scoped (TakeoffPdfConverterTest /
 * TakeoffMultiPageTest cover the multi-page takeoff_pages machinery itself), so these
 * tests create a bare TakeoffPage directly rather than going through store()'s upload
 * flow, which is exactly what the numeric validation here doesn't depend on.
 */
class TakeoffNumericValidationTest extends TestCase
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

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['takeoff' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag) . '-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    public function test_store_truncates_overlong_name(): void
    {
        $a = $this->makeCompanyWithOwner('NameLong');

        $response = $this->actingAs($a['owner'])->post('/app/takeoffs', [
            'name' => str_repeat('A', 300),
        ]);

        $response->assertRedirect();
        $takeoff = Takeoff::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($takeoff);
        $this->assertSame(150, mb_strlen($takeoff->name));
    }

    public function test_calibrate_rejects_non_numeric_pixel_distance(): void
    {
        $a = $this->makeCompanyWithOwner('CalibrateNaN');
        $takeoff = Takeoff::create(['company_id' => $a['company']->id, 'name' => 'Plan A']);
        $page = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 1, 'image_path' => '/uploads/takeoffs/x/x.jpg', 'scale_px_per_unit' => 1, 'scale_unit' => 'm']);

        $response = $this->actingAs($a['owner'])->postJson('/app/takeoffs/' . $takeoff->id . '/pages/' . $page->id . '/calibrate', [
            'pixel_distance' => 'abc',
            'real_length' => 10,
        ]);

        $response->assertStatus(422);
        $page->refresh();
        $this->assertSame('1.000000', $page->scale_px_per_unit);
    }

    public function test_calibrate_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('CalibrateHappy');
        $takeoff = Takeoff::create(['company_id' => $a['company']->id, 'name' => 'Plan A']);
        $page = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 1, 'image_path' => '/uploads/takeoffs/x/x.jpg', 'scale_px_per_unit' => 1, 'scale_unit' => 'm']);

        $response = $this->actingAs($a['owner'])->postJson('/app/takeoffs/' . $takeoff->id . '/pages/' . $page->id . '/calibrate', [
            'pixel_distance' => 200,
            'real_length' => 10,
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $page->refresh();
        $this->assertSame('20.000000', $page->scale_px_per_unit);
    }

    public function test_add_measurement_rejects_non_numeric_value(): void
    {
        $a = $this->makeCompanyWithOwner('MeasureNaN');
        $takeoff = Takeoff::create(['company_id' => $a['company']->id, 'name' => 'Plan A']);
        $page = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 1, 'image_path' => '/uploads/takeoffs/x/x.jpg', 'scale_px_per_unit' => 1, 'scale_unit' => 'm']);

        $response = $this->actingAs($a['owner'])->postJson('/app/takeoffs/' . $takeoff->id . '/pages/' . $page->id . '/measurements', [
            'type' => 'length',
            'value' => 'abc',
            'unit_cost' => 5,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, TakeoffMeasurement::where('takeoff_id', $takeoff->id)->count());
    }

    public function test_add_measurement_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('MeasureHappy');
        $takeoff = Takeoff::create(['company_id' => $a['company']->id, 'name' => 'Plan A']);
        $page = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 1, 'image_path' => '/uploads/takeoffs/x/x.jpg', 'scale_px_per_unit' => 1, 'scale_unit' => 'm']);

        $response = $this->actingAs($a['owner'])->postJson('/app/takeoffs/' . $takeoff->id . '/pages/' . $page->id . '/measurements', [
            'type' => 'length',
            'value' => 12.5,
            'unit_cost' => 20,
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $measurement = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->first();
        $this->assertNotNull($measurement);
        $this->assertSame('250.00', $measurement->total_cost);
        $this->assertSame($page->id, $measurement->takeoff_page_id);
    }
}
