<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to CreditNoteController::store() —
 * line-item qty/unit_price and issue_date, none of which had any type or range
 * check before (qty/unit_price were only ever coerced with a bare (float) cast,
 * silently turning a non-numeric value into 0, and a qty of exactly 0 was
 * silently dropped rather than rejected; issue_date was stored exactly as
 * submitted with no calendar-date check at all). Mirrors
 * EstimateNumericValidationTest's own conventions, applied here to credit notes.
 */
class CreditNoteNumericValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithInvoice(string $tag): array
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

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => "INV-{$tag}-0001",
            'status' => 'unpaid',
            'total' => 1150.00,
            'vat_rate' => 15,
            'vat_amount' => 150.00,
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        return compact('company', 'owner', 'client', 'invoice');
    }

    private function basePayload(array $a): array
    {
        return [
            'invoice_id' => $a['invoice']->id,
            'item_description' => ['Partial return'],
            'item_qty' => [1],
            'item_price' => [100],
        ];
    }

    public function test_store_rejects_non_numeric_quantity(): void
    {
        $a = $this->makeCompanyWithInvoice('CnQtyNaN');

        $response = $this->actingAs($a['owner'])->post('/app/credit-notes', [
            ...$this->basePayload($a),
            'item_qty' => ['not-a-number'],
        ]);

        $response->assertRedirect('/app/credit-notes/create?invoice_id='.$a['invoice']->id);
        $this->assertSame(0, CreditNote::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_zero_quantity(): void
    {
        $a = $this->makeCompanyWithInvoice('CnQtyZero');

        $response = $this->actingAs($a['owner'])->post('/app/credit-notes', [
            ...$this->basePayload($a),
            'item_qty' => [0],
        ]);

        $response->assertRedirect('/app/credit-notes/create?invoice_id='.$a['invoice']->id);
        $this->assertSame(0, CreditNote::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_unit_price(): void
    {
        $a = $this->makeCompanyWithInvoice('CnPriceNeg');

        $response = $this->actingAs($a['owner'])->post('/app/credit-notes', [
            ...$this->basePayload($a),
            'item_price' => [-50],
        ]);

        $response->assertRedirect('/app/credit-notes/create?invoice_id='.$a['invoice']->id);
        $this->assertSame(0, CreditNote::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_unit_price_as_zero(): void
    {
        $a = $this->makeCompanyWithInvoice('CnPriceBlank');

        $response = $this->actingAs($a['owner'])->post('/app/credit-notes', [
            ...$this->basePayload($a),
            'item_price' => [''],
        ]);

        $response->assertRedirect();
        $creditNote = CreditNote::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($creditNote);
        $this->assertEquals(0.0, (float) $creditNote->subtotal);
    }

    public function test_store_rejects_malformed_issue_date(): void
    {
        $a = $this->makeCompanyWithInvoice('CnIssueDateBad');

        $response = $this->actingAs($a['owner'])->post('/app/credit-notes', [
            ...$this->basePayload($a),
            'issue_date' => '2026-02-30',
        ]);

        $response->assertRedirect('/app/credit-notes/create?invoice_id='.$a['invoice']->id);
        $this->assertSame(0, CreditNote::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_a_valid_payload_and_computes_totals(): void
    {
        $a = $this->makeCompanyWithInvoice('CnHappy');

        $response = $this->actingAs($a['owner'])->post('/app/credit-notes', [
            'invoice_id' => $a['invoice']->id,
            'issue_date' => '2026-11-01',
            'item_description' => ['Partial return'],
            'item_qty' => [2],
            'item_price' => [100],
        ]);

        $response->assertRedirect();
        $creditNote = CreditNote::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($creditNote);
        $this->assertEquals(200.0, (float) $creditNote->subtotal);
        $this->assertSame('2026-11-01', $creditNote->issue_date->format('Y-m-d'));
    }
}
