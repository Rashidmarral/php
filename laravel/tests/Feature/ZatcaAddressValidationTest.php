<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Before this, building_number/postal_code/additional_number/cr_number were
 * trimmed and stored as-is on both the company-settings save
 * (SettingsController::update()) and the client save (ClientController::
 * store()/update(), via its private b2bData()) — any length/format was
 * accepted. Both now reject a malformed value through the shared
 * App\Support\ZatcaAddressValidator and flash a translated error instead of
 * silently persisting it, mirroring how the pre-existing VAT-number check
 * already behaves. Also covers a legacy `city` value that predates the new
 * Saudi-cities dropdown (App\Support\SaudiCities) still round-tripping
 * correctly through the edit form instead of being silently dropped.
 */
class ZatcaAddressValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function ownerFor(Company $company): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'Str0ng!Passw0rd',
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    // ---------- Company settings ----------

    public function test_company_settings_rejects_a_malformed_building_number(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/settings', [
            'name' => 'Acme Co',
            'building_number' => '12', // must be exactly 4 digits
            'postal_code' => '12345',
            'additional_number' => '6789',
            'cr_number' => '1010123456',
        ]);

        $response->assertRedirect('/app/settings');
        $this->assertNull($company->fresh()->building_number);
    }

    public function test_company_settings_rejects_a_malformed_postal_code(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/settings', [
            'name' => 'Acme Co',
            'building_number' => '1234',
            'postal_code' => '123', // must be exactly 5 digits
            'additional_number' => '6789',
            'cr_number' => '1010123456',
        ]);

        $response->assertRedirect('/app/settings');
        $this->assertNull($company->fresh()->postal_code);
    }

    public function test_company_settings_rejects_a_malformed_additional_number(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/settings', [
            'name' => 'Acme Co',
            'building_number' => '1234',
            'postal_code' => '12345',
            'additional_number' => '67', // must be exactly 4 digits
            'cr_number' => '1010123456',
        ]);

        $response->assertRedirect('/app/settings');
        $this->assertNull($company->fresh()->additional_number);
    }

    public function test_company_settings_rejects_a_malformed_cr_number(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/settings', [
            'name' => 'Acme Co',
            'building_number' => '1234',
            'postal_code' => '12345',
            'additional_number' => '6789',
            'cr_number' => '12345', // must be exactly 10 digits
        ]);

        $response->assertRedirect('/app/settings');
        $this->assertSame('', (string) $company->fresh()->cr_number);
    }

    public function test_company_settings_accepts_correctly_formatted_values_and_persists_them(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/settings', [
            'name' => 'Acme Co',
            'building_number' => '1234',
            'postal_code' => '12345',
            'additional_number' => '6789',
            'cr_number' => '1010123456',
            'city' => 'Riyadh',
        ]);

        $response->assertRedirect('/app/settings');
        $fresh = $company->fresh();
        $this->assertSame('1234', $fresh->building_number);
        $this->assertSame('12345', $fresh->postal_code);
        $this->assertSame('6789', $fresh->additional_number);
        $this->assertSame('1010123456', $fresh->cr_number);
        $this->assertSame('Riyadh', $fresh->city);
    }

    // ---------- Clients ----------

    public function test_client_store_rejects_a_malformed_building_number(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/clients', [
            'name' => 'Client One',
            'building_number' => '99', // must be exactly 4 digits
            'postal_code' => '12345',
            'additional_number' => '6789',
            'cr_number' => '1010123456',
        ]);

        $response->assertRedirect('/app/clients/create');
        $this->assertDatabaseMissing('clients', ['name' => 'Client One']);
    }

    public function test_client_store_rejects_a_malformed_postal_code(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/clients', [
            'name' => 'Client Two',
            'building_number' => '1234',
            'postal_code' => '9999', // must be exactly 5 digits
            'additional_number' => '6789',
            'cr_number' => '1010123456',
        ]);

        $response->assertRedirect('/app/clients/create');
        $this->assertDatabaseMissing('clients', ['name' => 'Client Two']);
    }

    public function test_client_store_rejects_a_malformed_additional_number(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/clients', [
            'name' => 'Client Three',
            'building_number' => '1234',
            'postal_code' => '12345',
            'additional_number' => '99999', // must be exactly 4 digits
            'cr_number' => '1010123456',
        ]);

        $response->assertRedirect('/app/clients/create');
        $this->assertDatabaseMissing('clients', ['name' => 'Client Three']);
    }

    public function test_client_store_rejects_a_malformed_cr_number(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/clients', [
            'name' => 'Client Four',
            'building_number' => '1234',
            'postal_code' => '12345',
            'additional_number' => '6789',
            'cr_number' => 'abc1234567', // must be exactly 10 digits
        ]);

        $response->assertRedirect('/app/clients/create');
        $this->assertDatabaseMissing('clients', ['name' => 'Client Four']);
    }

    public function test_client_store_accepts_correctly_formatted_values_and_persists_them(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->post('/app/clients', [
            'name' => 'Client Five',
            'building_number' => '1234',
            'postal_code' => '12345',
            'additional_number' => '6789',
            'cr_number' => '1010123456',
            'city' => 'Jeddah',
        ]);

        $response->assertRedirect('/app/clients');
        $this->assertDatabaseHas('clients', [
            'name' => 'Client Five',
            'building_number' => '1234',
            'postal_code' => '12345',
            'additional_number' => '6789',
            'cr_number' => '1010123456',
            'city' => 'Jeddah',
        ]);
    }

    public function test_client_update_rejects_a_malformed_cr_number_and_leaves_the_stored_value_untouched(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Existing Client',
            'cr_number' => '1010123456',
        ]);

        $response = $this->actingAs($owner)->post('/app/clients/'.$client->id, [
            'name' => 'Existing Client',
            'cr_number' => '123', // must be exactly 10 digits
        ]);

        $response->assertRedirect('/app/clients/'.$client->id.'/edit');
        $this->assertSame('1010123456', $client->fresh()->cr_number);
    }

    // ---------- Legacy city value round-trip ----------

    public function test_legacy_city_value_outside_the_canonical_list_still_round_trips_through_the_edit_form(): void
    {
        $company = Company::create(['name' => 'Acme Co', 'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com']);
        $owner = $this->ownerFor($company);
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Legacy Client',
            'city' => 'Somewhereville', // predates the Saudi-cities dropdown
        ]);

        $editResponse = $this->actingAs($owner)->get('/app/clients/'.$client->id.'/edit');
        $editResponse->assertOk();
        $editResponse->assertSee('Somewhereville', false);

        // Re-saving the form unchanged (as a browser submitting the selected
        // <option> would) must keep the same legacy value, not blank it out.
        $updateResponse = $this->actingAs($owner)->post('/app/clients/'.$client->id, [
            'name' => 'Legacy Client',
            'city' => 'Somewhereville',
        ]);
        $updateResponse->assertRedirect('/app/clients');
        $this->assertSame('Somewhereville', $client->fresh()->city);
    }

    public function test_legacy_company_city_value_outside_the_canonical_list_still_renders_in_settings(): void
    {
        $company = Company::create([
            'name' => 'Acme Co',
            'email' => 'acme-'.bin2hex(random_bytes(4)).'@example.com',
            'city' => 'Ghost Town',
        ]);
        $owner = $this->ownerFor($company);

        $response = $this->actingAs($owner)->get('/app/settings');
        $response->assertOk();
        $response->assertSee('Ghost Town', false);
    }
}
