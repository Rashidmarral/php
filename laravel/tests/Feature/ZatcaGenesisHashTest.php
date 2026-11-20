<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Support\Zatca\InvoiceChainer;
use App\Support\Zatca\ZatcaCryptoService;
use App\Support\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Covers a real, verified bug fix: ZatcaCryptoService::genesisHash() used to
 * return base64_encode(hash('sha256', '', true)) — the base64 of the RAW
 * 32-byte SHA-256 digest of an EMPTY string. ZATCA's actual documented
 * "first invoice" Previous Invoice Hash constant is
 * base64_encode(hash('sha256', '0', false)) — the base64 of the 64-character
 * HEX-STRING digest of the single character "0", independently confirmed via
 * ZATCA's own developer community and a published open-source ZATCA package
 * (both cite the exact same value: NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZj
 * NzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==). Using the wrong genesis
 * value fails ZATCA's PIH check on every company's very first submitted
 * invoice/credit-note/debit-note.
 */
class ZatcaGenesisHashTest extends TestCase
{
    use DatabaseTransactions;

    /** The exact value published/confirmed by ZATCA's developer community for the first invoice's PIH. */
    private const KNOWN_CORRECT_GENESIS_HASH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function test_genesis_hash_matches_the_zatca_documented_constant(): void
    {
        $service = new ZatcaCryptoService;

        $this->assertSame(self::KNOWN_CORRECT_GENESIS_HASH, $service->genesisHash());
    }

    public function test_genesis_hash_is_the_base64_of_the_hex_digest_of_the_character_zero_not_an_empty_string(): void
    {
        $service = new ZatcaCryptoService;

        $this->assertSame(base64_encode(hash('sha256', '0', false)), $service->genesisHash());
        $this->assertNotSame(base64_encode(hash('sha256', '', true)), $service->genesisHash(), 'the old, wrong value must not come back');
    }

    public function test_a_companys_very_first_invoice_is_chained_with_the_correct_genesis_pih(): void
    {
        $company = Company::create(['name' => 'Al Rashid Construction Co.', 'email' => 'zatca-genesis@example.com']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Nasser Al Otaibi']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'status' => 'unpaid', 'total' => 620000, 'vat_rate' => 0, 'vat_amount' => 0,
        ]);

        InvoiceChainer::chain($invoice, $company, $client, [['description' => 'Foundation', 'qty' => 1, 'unit_price' => 620000, 'total' => 620000]], app(ZatcaSyncService::class));

        $invoice->refresh();
        $this->assertSame(1, $invoice->zatca_icv);
        $this->assertSame(self::KNOWN_CORRECT_GENESIS_HASH, $invoice->zatca_previous_hash);
        $this->assertNotEmpty($invoice->zatca_hash);

        // A second invoice must chain to the FIRST invoice's own hash, not the genesis value again.
        $invoice2 = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0002',
            'status' => 'unpaid', 'total' => 100000, 'vat_rate' => 0, 'vat_amount' => 0,
        ]);
        InvoiceChainer::chain($invoice2, $company->fresh(), $client, [['description' => 'MEP', 'qty' => 1, 'unit_price' => 100000, 'total' => 100000]], app(ZatcaSyncService::class));
        $invoice2->refresh();

        $this->assertSame(2, $invoice2->zatca_icv);
        $this->assertSame($invoice->zatca_hash, $invoice2->zatca_previous_hash);
        $this->assertNotSame(self::KNOWN_CORRECT_GENESIS_HASH, $invoice2->zatca_previous_hash);
    }
}
