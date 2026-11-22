<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialLibraryItem;
use App\Models\MaterialLibraryItemCityPrice;
use App\Models\MaterialLibraryPriceImportLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the per-city price-book layer on top of material_library_items: the admin
 * grid edit, CSV import, MaterialLibraryItem::effectivePriceFor(), the admin.super
 * route guard, and the company-side wiring in MaterialController (libraryIndex() /
 * importFromLibrary()). Mirrors MaterialNumericValidationTest's / CompanyDomainTest's
 * own fixture shape.
 */
class MaterialLibraryCityPriceTest extends TestCase
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

    private function makeSuperAdmin(string $tag): User
    {
        return User::create([
            'name' => "Super Admin {$tag}",
            'email' => 'super-admin-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function makeCompanyWithOwner(string $tag, ?string $city = null): array
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['materials' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
            'city' => $city,
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

    private function makeLibraryItem(array $overrides = []): MaterialLibraryItem
    {
        return MaterialLibraryItem::create([...[
            'sku' => null,
            'name' => 'Portland Cement 50kg',
            'name_ar' => null,
            'category' => 'Concrete & Steel',
            'unit' => 'bag',
            'material_cost' => 20.00,
            'labor_cost' => 5.00,
            'is_active' => true,
            'sort_order' => 0,
        ], ...$overrides]);
    }

    // ---------------------------------------------------------------------
    // 1. Admin set/clear a city price via updateCityPrices
    // ---------------------------------------------------------------------

    public function test_admin_can_set_a_city_price_and_clear_it_with_a_blank_value(): void
    {
        $admin = $this->makeSuperAdmin('SET');
        $item = $this->makeLibraryItem();

        $this->actingAs($admin)->post('/admin/material-library/city-prices', [
            'city' => 'Riyadh',
            'prices' => [$item->id => '45.00'],
        ])->assertRedirect('/admin/material-library/city-prices?city=Riyadh');

        $override = MaterialLibraryItemCityPrice::where('material_library_item_id', $item->id)->where('city', 'Riyadh')->first();
        $this->assertNotNull($override);
        $this->assertSame('45.00', $override->unit_cost);
        $this->assertSame('manual', $override->source);
        $this->assertSame($admin->id, $override->updated_by);

        // Submitting a blank value removes the existing override (reverts to base price).
        $this->actingAs($admin)->post('/admin/material-library/city-prices', [
            'city' => 'Riyadh',
            'prices' => [$item->id => ''],
        ]);

        $this->assertNull(
            MaterialLibraryItemCityPrice::where('material_library_item_id', $item->id)->where('city', 'Riyadh')->first()
        );
    }

    // ---------------------------------------------------------------------
    // 2. effectivePriceFor()
    // ---------------------------------------------------------------------

    public function test_effective_price_for_returns_override_or_falls_back_to_base_price(): void
    {
        $item = $this->makeLibraryItem(['material_cost' => 20.00]);
        MaterialLibraryItemCityPrice::create([
            'material_library_item_id' => $item->id,
            'city' => 'Jeddah',
            'unit_cost' => 33.50,
            'source' => 'manual',
        ]);

        $this->assertSame(33.50, $item->effectivePriceFor('Jeddah'));
        $this->assertSame(20.00, $item->effectivePriceFor('Riyadh'));
        $this->assertSame(20.00, $item->effectivePriceFor(null));
    }

    // ---------------------------------------------------------------------
    // 3. CSV import
    // ---------------------------------------------------------------------

    public function test_csv_import_matches_by_sku_and_name_skips_unmatched_and_reimports_without_duplicating(): void
    {
        $admin = $this->makeSuperAdmin('CSV');
        $bySku = $this->makeLibraryItem(['sku' => 'CEM-50', 'name' => 'Cement Bag']);
        $byName = $this->makeLibraryItem(['sku' => null, 'name' => 'Rebar 12mm']);

        $csv = "sku_or_name,unit_cost\nCEM-50,41.25\nRebar 12mm,60.00\nNoSuchItem,99.00\n";
        $file = UploadedFile::fake()->createWithContent('riyadh-prices.csv', $csv);

        $response = $this->actingAs($admin)->post('/admin/material-library/city-prices/import', [
            'city' => 'Riyadh',
            'csv' => $file,
        ]);
        $response->assertRedirect('/admin/material-library/city-prices?city=Riyadh');

        $this->assertSame(2, MaterialLibraryItemCityPrice::where('city', 'Riyadh')->count());
        $this->assertSame('41.25', MaterialLibraryItemCityPrice::where('material_library_item_id', $bySku->id)->where('city', 'Riyadh')->first()->unit_cost);
        $this->assertSame('60.00', MaterialLibraryItemCityPrice::where('material_library_item_id', $byName->id)->where('city', 'Riyadh')->first()->unit_cost);

        $log = MaterialLibraryPriceImportLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Riyadh', $log->city);
        $this->assertSame(2, $log->matched_count);
        $this->assertSame(1, $log->skipped_count);
        $this->assertSame($admin->id, $log->imported_by);

        // Re-importing the same file updates the same two rows rather than duplicating them.
        $file2 = UploadedFile::fake()->createWithContent('riyadh-prices.csv', $csv);
        $this->actingAs($admin)->post('/admin/material-library/city-prices/import', [
            'city' => 'Riyadh',
            'csv' => $file2,
        ]);

        $this->assertSame(2, MaterialLibraryItemCityPrice::where('city', 'Riyadh')->count());
        $this->assertSame(2, MaterialLibraryPriceImportLog::count());
    }

    // ---------------------------------------------------------------------
    // 4. Non-admin blocked from all three new admin routes
    // ---------------------------------------------------------------------

    public function test_a_company_user_cannot_reach_any_of_the_new_admin_routes(): void
    {
        $a = $this->makeCompanyWithOwner('BLOCKED');
        $item = $this->makeLibraryItem();

        $this->actingAs($a['owner'])->get('/admin/material-library/city-prices')->assertStatus(403);

        $this->actingAs($a['owner'])->post('/admin/material-library/city-prices', [
            'city' => 'Riyadh',
            'prices' => [$item->id => '45.00'],
        ])->assertStatus(403);
        $this->assertNull(MaterialLibraryItemCityPrice::where('material_library_item_id', $item->id)->first());

        $file = UploadedFile::fake()->createWithContent('x.csv', "sku_or_name,unit_cost\n");
        $this->actingAs($a['owner'])->post('/admin/material-library/city-prices/import', [
            'city' => 'Riyadh',
            'csv' => $file,
        ])->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // 5. libraryIndex() shows the city-adjusted price
    // ---------------------------------------------------------------------

    public function test_library_index_shows_city_adjusted_price_only_for_a_company_with_a_matching_city(): void
    {
        $item = $this->makeLibraryItem(['material_cost' => 20.00]);
        MaterialLibraryItemCityPrice::create([
            'material_library_item_id' => $item->id,
            'city' => 'Jeddah',
            'unit_cost' => 28.75,
            'source' => 'manual',
        ]);

        $withCity = $this->makeCompanyWithOwner('LibCity', 'Jeddah');
        $response = $this->actingAs($withCity['owner'])->get('/app/materials/library');
        $response->assertOk();
        $response->assertSee('28.75', false);

        $noCity = $this->makeCompanyWithOwner('LibNoCity', null);
        $response2 = $this->actingAs($noCity['owner'])->get('/app/materials/library');
        $response2->assertOk();
        $response2->assertDontSee('28.75', false);

        $otherCity = $this->makeCompanyWithOwner('LibOtherCity', 'Dammam');
        $response3 = $this->actingAs($otherCity['owner'])->get('/app/materials/library');
        $response3->assertOk();
        $response3->assertDontSee('28.75', false);
    }

    // ---------------------------------------------------------------------
    // 6. importFromLibrary() copies the city-adjusted material_cost
    // ---------------------------------------------------------------------

    public function test_import_from_library_uses_city_adjusted_material_cost_when_available(): void
    {
        $item = $this->makeLibraryItem(['name' => 'Rebar City Test', 'material_cost' => 20.00, 'labor_cost' => 5.00]);
        MaterialLibraryItemCityPrice::create([
            'material_library_item_id' => $item->id,
            'city' => 'Jeddah',
            'unit_cost' => 28.75,
            'source' => 'manual',
        ]);

        $withCity = $this->makeCompanyWithOwner('ImportCity', 'Jeddah');
        $this->actingAs($withCity['owner'])->post('/app/materials/library/import', [
            'item_ids' => [$item->id],
        ])->assertRedirect('/app/materials');

        $material = Material::where('company_id', $withCity['company']->id)->where('name', 'Rebar City Test')->first();
        $this->assertNotNull($material);
        $this->assertSame('28.75', $material->material_cost);
        $this->assertSame('5.00', $material->labor_cost);
        $this->assertSame('33.75', $material->unit_cost);

        $noCity = $this->makeCompanyWithOwner('ImportNoCity', null);
        $this->actingAs($noCity['owner'])->post('/app/materials/library/import', [
            'item_ids' => [$item->id],
        ])->assertRedirect('/app/materials');

        $material2 = Material::where('company_id', $noCity['company']->id)->where('name', 'Rebar City Test')->first();
        $this->assertNotNull($material2);
        $this->assertSame('20.00', $material2->material_cost);
        $this->assertSame('5.00', $material2->labor_cost);
        $this->assertSame('25.00', $material2->unit_cost);
    }
}
