<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to InvoiceController::store() for the
 * invoice create form — line-item qty/unit_price/discount, the header
 * discount_amount/other_charges/retention_percent fields, due_date/date_of_supply,
 * and invoice_number — none of which had any type, range, or length check before
 * (every number was only ever coerced with (float)/max()/min(), and the two dates
 * were stored exactly as submitted with no calendar-date check at all). Mirrors
 * EstimateNumericValidationTest's own conventions and bounds-testing style, applied
 * here to invoices instead of estimates.
 *
 * Wrapped in DatabaseTransactions, matching EstimateNumericValidationTest's own
 * convention, so none of this touches the real dev sqlite database.
 */
class InvoiceNumericValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithOwnerAndClient(string $tag): array
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

        $client = Client::create([
            'company_id' => $company->id,
            'name' => "{$tag} Client",
        ]);

        return compact('company', 'owner', 'client');
    }

    private function basePayload(array $a): array
    {
        return [
            'client_id' => $a['client']->id,
            'item_description' => ['Foundation works'],
            'item_qty' => [2],
            'item_price' => [5000],
            'apply_vat' => '0',
        ];
    }

    public function test_store_rejects_non_numeric_quantity(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvQtyNaN');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'item_qty' => ['not-a-number'],
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_zero_quantity(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvQtyZero');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'item_qty' => [0],
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_quantity_above_max_bound(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvQtyMax');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'item_qty' => [100000000],
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_unit_price(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvPriceNeg');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'item_price' => [-100],
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_unit_price_as_zero(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvPriceBlank');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'item_price' => [''],
        ]);

        $response->assertRedirect();
        $invoice = Invoice::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($invoice);
        $item = InvoiceItem::where('invoice_id', $invoice->id)->first();
        $this->assertSame('0.00', $item->unit_price);
    }

    public function test_store_rejects_negative_line_discount(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvLineDiscNeg');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'item_discount' => ['-20'],
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_non_numeric_header_discount(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvHeaderDiscNaN');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'discount_amount' => 'abc',
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_other_charges(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvOtherChargesNeg');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'other_charges' => '-5',
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_retention_percent_above_100(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvRetentionHigh');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'retention_percent' => '150',
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_due_date(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvDueDateBad');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'due_date' => '2026-13-40',
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_date_of_supply(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvSupplyDateBad');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'date_of_supply' => 'not-a-date',
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_invoice_number_over_30_characters(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvNumberLong');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'invoice_number' => str_repeat('X', 31),
        ]);

        $response->assertRedirect('/app/invoices/create');
        $this->assertSame(0, Invoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_a_valid_payload_and_truncates_overlong_item_fields(): void
    {
        $a = $this->makeCompanyWithOwnerAndClient('InvHappy');

        $response = $this->actingAs($a['owner'])->post('/app/invoices', [
            ...$this->basePayload($a),
            'item_code' => [str_repeat('C', 60)],
            'item_unit' => [str_repeat('U', 30)],
            'item_discount' => ['0'],
            'discount_amount' => '0',
            'other_charges' => '0',
            'retention_percent' => '10',
        ]);

        $response->assertRedirect();
        $invoice = Invoice::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(10.0, (float) $invoice->retention_percent);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->first();
        $this->assertSame(50, strlen($item->item_code));
        $this->assertSame(20, strlen($item->unit));
    }
}
