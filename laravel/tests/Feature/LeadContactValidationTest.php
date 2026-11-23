<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the contact/numeric validation added to LeadController's
 * contactFields() and its new validatedNumber()/validatedNumberOrDefault()
 * helpers — email, phone and estimated_value, none of which had any
 * format/range check before (email/phone were just trim()'d or not even
 * trimmed, and estimated_value was only ever cast with (float), silently
 * coercing a bad value to 0 instead of rejecting it). email/phone follow the
 * same rules ClientContactValidationTest/SupplierContactValidationTest cover;
 * estimated_value mirrors EstimateController/InvoiceController's own
 * validatedNumber()/validatedNumberOrDefault() shape, applied here to a
 * lead's estimated_value instead of a line-item cost.
 *
 * Wrapped in DatabaseTransactions, matching EstimateNumericValidationTest's
 * own convention, so none of this touches the real dev sqlite database.
 */
class LeadContactValidationTest extends TestCase
{
    use DatabaseTransactions;

    /** See RfiSubmittalDocumentVersionTest's own docblock on why this reset is needed. */
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
        $company = Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag).'@example.com',
            // Grants the 'leads' feature directly (no plan needed), the same
            // company_feature_overrides escape hatch CompanyFeatureOverrideTest covers.
            'feature_overrides' => ['leads' => true],
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
        $a = $this->makeCompanyWithOwner('LeadEmailBad');

        $response = $this->actingAs($a['owner'])->post('/app/leads', [
            'name' => 'Test Lead',
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect('/app/leads/create');
        $this->assertSame(0, Lead::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_phone(): void
    {
        $a = $this->makeCompanyWithOwner('LeadPhoneBad');

        $response = $this->actingAs($a['owner'])->post('/app/leads', [
            'name' => 'Test Lead',
            'phone' => '123',
        ]);

        $response->assertRedirect('/app/leads/create');
        $this->assertSame(0, Lead::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_non_numeric_estimated_value(): void
    {
        $a = $this->makeCompanyWithOwner('LeadEstValNaN');

        $response = $this->actingAs($a['owner'])->post('/app/leads', [
            'name' => 'Test Lead',
            'estimated_value' => 'not-a-number',
        ]);

        $response->assertRedirect('/app/leads/create');
        $this->assertSame(0, Lead::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_estimated_value(): void
    {
        $a = $this->makeCompanyWithOwner('LeadEstValNeg');

        $response = $this->actingAs($a['owner'])->post('/app/leads', [
            'name' => 'Test Lead',
            'estimated_value' => '-500',
        ]);

        $response->assertRedirect('/app/leads/create');
        $this->assertSame(0, Lead::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_estimated_value_as_zero(): void
    {
        $a = $this->makeCompanyWithOwner('LeadEstValBlank');

        $response = $this->actingAs($a['owner'])->post('/app/leads', [
            'name' => 'Test Lead',
            'estimated_value' => '',
        ]);

        $response->assertRedirect();
        $lead = Lead::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('0.00', $lead->estimated_value);
    }

    public function test_store_accepts_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('LeadHappy');

        $response = $this->actingAs($a['owner'])->post('/app/leads', [
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
            'phone' => '+966501234567',
            'estimated_value' => '15000.50',
        ]);

        $response->assertRedirect();
        $lead = Lead::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('15000.50', $lead->estimated_value);
    }

    public function test_update_rejects_blank_name(): void
    {
        $a = $this->makeCompanyWithOwner('LeadUpdateNameBlank');
        $lead = Lead::create(['company_id' => $a['company']->id, 'name' => 'Original Lead']);

        $response = $this->actingAs($a['owner'])->post('/app/leads/'.$lead->id, [
            'name' => '   ',
        ]);

        $response->assertRedirect('/app/leads/'.$lead->id.'/edit');
        $this->assertSame('Original Lead', $lead->refresh()->name);
    }

    public function test_update_rejects_estimated_value_above_max_bound(): void
    {
        $a = $this->makeCompanyWithOwner('LeadUpdateEstValMax');
        $lead = Lead::create(['company_id' => $a['company']->id, 'name' => 'Original Lead']);

        $response = $this->actingAs($a['owner'])->post('/app/leads/'.$lead->id, [
            'name' => 'Original Lead',
            'estimated_value' => '99999999999999',
        ]);

        $response->assertRedirect('/app/leads/'.$lead->id.'/edit');
        $this->assertSame('0.00', $lead->refresh()->estimated_value);
    }
}
