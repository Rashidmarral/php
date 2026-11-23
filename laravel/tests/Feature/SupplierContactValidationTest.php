<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the contact/registration-number validation added to
 * SupplierController's contactFields()/qualificationFields() — email, phone,
 * cr_number and vat_number, none of which had any format check at all
 * before (every one was just trim()'d, or not even trimmed, and stored
 * as-is). email/phone now follow the exact same rules
 * ClientContactValidationTest covers for the client form; cr_number/
 * vat_number now follow the exact same ZATCA rules
 * ClientController::b2bData() already enforces for a client's own
 * registration numbers. Mirrors InvoiceNumericValidationTest's own
 * conventions and bounds-testing style, applied here to the supplier CRM
 * form instead of invoice numbers.
 *
 * Wrapped in DatabaseTransactions, matching EstimateNumericValidationTest's
 * own convention, so none of this touches the real dev sqlite database.
 */
class SupplierContactValidationTest extends TestCase
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
            // Grants the 'suppliers' feature directly (no plan needed), the same
            // company_feature_overrides escape hatch CompanyFeatureOverrideTest covers.
            'feature_overrides' => ['suppliers' => true],
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
        $a = $this->makeCompanyWithOwner('SupplierEmailBad');

        $response = $this->actingAs($a['owner'])->post('/app/suppliers', [
            'name' => 'Test Supplier',
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect('/app/suppliers/create');
        $this->assertSame(0, Supplier::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_phone(): void
    {
        $a = $this->makeCompanyWithOwner('SupplierPhoneBad');

        $response = $this->actingAs($a['owner'])->post('/app/suppliers', [
            'name' => 'Test Supplier',
            'phone' => 'abc-not-a-phone',
        ]);

        $response->assertRedirect('/app/suppliers/create');
        $this->assertSame(0, Supplier::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_cr_number_with_wrong_digit_count(): void
    {
        $a = $this->makeCompanyWithOwner('SupplierCrBad');

        $response = $this->actingAs($a['owner'])->post('/app/suppliers', [
            'name' => 'Test Supplier',
            'cr_number' => '12345',
        ]);

        $response->assertRedirect('/app/suppliers/create');
        $this->assertSame(0, Supplier::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_vat_number_not_matching_zatca_format(): void
    {
        $a = $this->makeCompanyWithOwner('SupplierVatBad');

        $response = $this->actingAs($a['owner'])->post('/app/suppliers', [
            'name' => 'Test Supplier',
            'vat_number' => '123456789012345', // 15 digits but doesn't start/end with 3
        ]);

        $response->assertRedirect('/app/suppliers/create');
        $this->assertSame(0, Supplier::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_valid_cr_and_vat_numbers(): void
    {
        $a = $this->makeCompanyWithOwner('SupplierCrVatGood');

        $response = $this->actingAs($a['owner'])->post('/app/suppliers', [
            'name' => 'Test Supplier',
            'cr_number' => '1010123456',
            'vat_number' => '312345678901233',
            'phone' => '0551234567',
            'email' => 'supplier@example.com',
        ]);

        $response->assertRedirect('/app/suppliers');
        $supplier = Supplier::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($supplier);
        $this->assertSame('1010123456', $supplier->cr_number);
        $this->assertSame('312345678901233', $supplier->vat_number);
    }

    public function test_update_rejects_blank_name(): void
    {
        $a = $this->makeCompanyWithOwner('SupplierUpdateNameBlank');
        $supplier = Supplier::create(['company_id' => $a['company']->id, 'name' => 'Original Supplier']);

        $response = $this->actingAs($a['owner'])->post('/app/suppliers/'.$supplier->id, [
            'name' => '   ',
        ]);

        $response->assertRedirect('/app/suppliers/'.$supplier->id.'/edit');
        $this->assertSame('Original Supplier', $supplier->refresh()->name);
    }

    public function test_update_rejects_malformed_phone(): void
    {
        $a = $this->makeCompanyWithOwner('SupplierUpdatePhoneBad');
        $supplier = Supplier::create(['company_id' => $a['company']->id, 'name' => 'Original Supplier']);

        $response = $this->actingAs($a['owner'])->post('/app/suppliers/'.$supplier->id, [
            'name' => 'Updated Supplier',
            'phone' => '999',
        ]);

        $response->assertRedirect('/app/suppliers/'.$supplier->id.'/edit');
        $this->assertSame('Original Supplier', $supplier->refresh()->name);
    }
}
