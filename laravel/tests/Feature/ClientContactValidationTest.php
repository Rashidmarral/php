<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the contact-field validation added to ClientController's new
 * contactFields() — email and phone, which were previously saved exactly as
 * submitted (update() didn't even trim them) with no format check at all.
 * email now rejects anything filter_var(FILTER_VALIDATE_EMAIL) rejects; phone
 * now rejects anything that isn't a recognizable Saudi mobile number via
 * App\Support\PhoneNumber::isValidSaudiMobile(). Mirrors
 * InvoiceNumericValidationTest's own conventions and bounds-testing style,
 * applied here to the client CRM form instead of invoice numbers.
 *
 * Wrapped in DatabaseTransactions, matching EstimateNumericValidationTest's
 * own convention, so none of this touches the real dev sqlite database.
 */
class ClientContactValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithOwner(string $tag): array
    {
        $company = Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag).'@example.com',
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

    public function test_store_rejects_malformed_email(): void
    {
        $a = $this->makeCompanyWithOwner('ClientEmailBad');

        $response = $this->actingAs($a['owner'])->post('/app/clients', [
            'name' => 'Test Client',
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect('/app/clients/create');
        $this->assertSame(0, Client::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_phone(): void
    {
        $a = $this->makeCompanyWithOwner('ClientPhoneBad');

        $response = $this->actingAs($a['owner'])->post('/app/clients', [
            'name' => 'Test Client',
            'phone' => '12345',
        ]);

        $response->assertRedirect('/app/clients/create');
        $this->assertSame(0, Client::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_local_saudi_mobile_format_and_normalizes_length(): void
    {
        $a = $this->makeCompanyWithOwner('ClientPhoneGood');

        $response = $this->actingAs($a['owner'])->post('/app/clients', [
            'name' => 'Test Client',
            'phone' => '0501234567',
        ]);

        $response->assertRedirect();
        $client = Client::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($client);
        $this->assertSame('0501234567', $client->phone);
    }

    public function test_store_accepts_international_saudi_mobile_format_with_spaces(): void
    {
        $a = $this->makeCompanyWithOwner('ClientPhoneIntl');

        $response = $this->actingAs($a['owner'])->post('/app/clients', [
            'name' => 'Test Client',
            'phone' => '+966 55 123 4567',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Client::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_email_and_phone(): void
    {
        $a = $this->makeCompanyWithOwner('ClientBlankContact');

        $response = $this->actingAs($a['owner'])->post('/app/clients', [
            'name' => 'Test Client',
        ]);

        $response->assertRedirect('/app/clients');
        $this->assertSame(1, Client::where('company_id', $a['company']->id)->count());
    }

    public function test_update_rejects_malformed_email(): void
    {
        $a = $this->makeCompanyWithOwner('ClientUpdateEmailBad');
        $client = Client::create(['company_id' => $a['company']->id, 'name' => 'Original Name']);

        $response = $this->actingAs($a['owner'])->post('/app/clients/'.$client->id, [
            'name' => 'Updated Name',
            'email' => 'still-not-an-email',
        ]);

        $response->assertRedirect('/app/clients/'.$client->id.'/edit');
        $this->assertSame('Original Name', $client->refresh()->name);
    }

    public function test_update_rejects_blank_name(): void
    {
        $a = $this->makeCompanyWithOwner('ClientUpdateNameBlank');
        $client = Client::create(['company_id' => $a['company']->id, 'name' => 'Original Name']);

        $response = $this->actingAs($a['owner'])->post('/app/clients/'.$client->id, [
            'name' => '   ',
        ]);

        $response->assertRedirect('/app/clients/'.$client->id.'/edit');
        $this->assertSame('Original Name', $client->refresh()->name);
    }

    public function test_store_truncates_overlong_name_and_email(): void
    {
        $a = $this->makeCompanyWithOwner('ClientOverlong');

        // A 190-char but still filter_var-valid address (64-char local part, two
        // 60-char domain labels) — long enough to exceed the 150-char email
        // column, while staying inside RFC's own 64-char local-part limit so
        // the format check itself doesn't reject it first.
        $longEmail = str_repeat('a', 64).'@'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.com';

        $response = $this->actingAs($a['owner'])->post('/app/clients', [
            'name' => str_repeat('N', 200),
            'email' => $longEmail,
        ]);

        $response->assertRedirect('/app/clients');
        $client = Client::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($client);
        $this->assertSame(150, mb_strlen($client->name));
        $this->assertSame(150, mb_strlen($client->email));
    }
}
