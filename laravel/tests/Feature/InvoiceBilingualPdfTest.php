<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\Translation;
use App\Models\User;
use App\Support\Pdf\ArabicText;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Proves the 'bilingual' InvoiceTemplate layout (document-v2.blade.php ->
 * partials/itpl-bilingual.blade.php, restyled to match Daftari's real
 * bilingual_classic markup — see InvoiceTemplateStyles::bilingualChrome()'s
 * docblock) still renders a real, structurally-correct PDF for an actual
 * company/invoice, not just that the Blade source parses.
 */
class InvoiceBilingualPdfTest extends TestCase
{
    use DatabaseTransactions;

    private function makeOwnerWithBilingualInvoice(): array
    {
        $company = Company::create([
            'name' => 'Al Rashid Construction Co.',
            'name_ar' => 'شركة الراشد للمقاولات',
            'email' => 'alrashid-bl@example.com',
            'vat_number' => '300000000000003',
            'cr_number' => '1010101010',
        ]);
        $owner = User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner-bl-pdf@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Nawaf Trading Est.',
            'name_ar' => 'مؤسسة نواف التجارية',
            'email' => 'client@example.com',
        ]);
        InvoiceTemplate::create([
            'company_id' => $company->id,
            'name' => 'Heritage Bilingual',
            'name_ar' => 'التراث ثنائي اللغة',
            'document_type' => 'invoice',
            'preset_key' => 'heritage_bilingual',
            'accent_color' => '#8a6d3b',
            'layout' => 'bilingual',
            'density' => 'compact',
            'language_mode' => 'bilingual',
            'table_direction' => 'ltr',
            'page_size' => 'a4',
            'is_default' => true,
        ]);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-BL-0001',
            'status' => 'paid',
            'total' => 1150.00,
            'vat_rate' => 15,
            'vat_amount' => 150.00,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Site mobilization',
            'description_ar' => 'تجهيز الموقع',
            'qty' => 1,
            'unit_price' => 1000.00,
            'total' => 1000.00,
        ]);

        return compact('company', 'owner', 'client', 'invoice');
    }

    public function test_bilingual_invoice_pdf_route_renders_successfully(): void
    {
        ['owner' => $owner, 'invoice' => $invoice] = $this->makeOwnerWithBilingualInvoice();

        $response = $this->actingAs($owner)->get("/app/invoices/{$invoice->id}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $body = $response->getContent();
        $this->assertStringStartsWith('%PDF', $body, 'dompdf should hand back a real PDF document');
        $this->assertGreaterThan(1000, strlen($body), 'the PDF body should not be a near-empty/broken render');
    }

    /**
     * A production invoice once generated with its regular-weight Cairo font
     * registered with no /FontDescriptor, no embedded /FontFile2, and no
     * /Widths array at all — dompdf's lazy, unlocked font-cache registration
     * had raced across two concurrent requests and left a corrupted cache
     * entry (see Pdf::output()'s own comment). The result was still a
     * well-formed, non-empty PDF (so the assertions above wouldn't have
     * caught it) — every character just fell back to one uniform width,
     * stretching the document's text out letter by letter. This inspects the
     * raw PDF object structure to make sure every font actually used to draw
     * text carries real embedded glyph data.
     */
    public function test_bilingual_invoice_pdf_embeds_real_glyph_data_for_every_font_weight_used(): void
    {
        ['owner' => $owner, 'invoice' => $invoice] = $this->makeOwnerWithBilingualInvoice();

        $response = $this->actingAs($owner)->get("/app/invoices/{$invoice->id}/pdf");
        $body = $response->getContent();

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($body, '/FontFile2'),
            'both the regular and bold Cairo weights used on this document must each embed a real font program'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\/BaseFont\s*\/cairo_(normal|bold)_[0-9a-f]+/',
            $body,
            'a bare dompdf font-cache key as BaseFont means that font failed to embed and fell back to a wrong default width for every character'
        );
    }

    public function test_bilingual_invoice_html_has_the_expected_structural_markers(): void
    {
        ['company' => $company, 'client' => $client, 'invoice' => $invoice] = $this->makeOwnerWithBilingualInvoice();
        $items = InvoiceItem::where('invoice_id', $invoice->id)->get();
        $template = $company->activeInvoiceTemplateFor('invoice');

        $html = view('pdf.document-v2', [
            'template' => 'modern',
            'lang' => 'en',
            'currency' => 'SAR',
            'docType' => 'Invoice',
            'docNumber' => $invoice->invoice_number,
            'docDate' => $invoice->created_at,
            'validUntil' => null,
            'status' => ucfirst($invoice->status),
            'issuer' => ['name' => $company->name, 'meta' => ['VAT: '.$company->vat_number]],
            'companyNameAr' => $company->name_ar,
            'companyLogo' => null,
            'billTo' => ['name' => $client->name, 'meta' => []],
            'items' => $items->map(fn ($i) => ['description' => $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'total' => $i->total])->all(),
            'subtotal' => (float) $invoice->total - (float) $invoice->vat_amount,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => $invoice->vat_rate,
            'vatAmount' => (float) $invoice->vat_amount,
            'total' => (float) $invoice->total,
            'qrCode' => 'data:image/svg+xml;base64,PHN2ZyAvPg==',
            'footerNote' => 'Generated by BuildXact',
            'invoiceTemplate' => $template,
            'company' => $company,
            'documentType' => 'invoice',
            'pageSize' => 'a4',
            'partyLabelKey' => 'pdf.bill_to',
            'partyNameAr' => $client->name_ar,
            'partyVat' => null,
            'zatcaStatus' => null,
        ])->render();

        // The thin top accent rule + unboxed header + centered title-bar
        // ported from Daftari's real bilingual_classic layout.
        $this->assertStringContainsString('itpl-bl-topbar', $html);
        $this->assertStringContainsString('itpl-bl-title-bar', $html);

        // The colored payment-status pill for a known status value.
        $this->assertStringContainsString('itpl-bl-status-pill', $html);

        // Bilingual column labels in the items table header (stacked EN/AR).
        $this->assertStringContainsString('itpl-bl-items-table', $html);
        $this->assertStringContainsString('ar-text', $html);

        // The QR code block.
        $this->assertStringContainsString('data:image/svg+xml;base64', $html);

        // The totals table with the grand total row.
        $this->assertStringContainsString('itpl-bl-totals-table', $html);
        $this->assertStringContainsString('class="grand"', $html);

        // Amount-in-words and the authorized-signature line.
        $this->assertStringContainsString('itpl-bl-words', $html);
        $this->assertStringContainsString('itpl-bl-sign-table', $html);

        // Every translated Arabic LABEL (VAT, Subtotal, Total, ...) must be
        // glyph-shaped/joined the same way Arabic DATA values (the company
        // name, the client name) already are — tFor() on its own returns
        // plain logical-order Arabic, which dompdf renders as disconnected,
        // unjoined letters rather than proper cursive script. This was a
        // real bug: $biLabel/$biStack and several direct tFor(..., 'ar')
        // calls in itpl-bilingual.blade.php (and the shared $L in
        // document-v2.blade.php/payment-certificate-v2.blade.php) called
        // tFor() without ever routing it through pdfText()/ArabicText::shape().
        $vatLabelAr = Translation::overridesFor('ar')['common.vat'] ?? __('common.vat', [], 'ar');
        $this->assertStringNotContainsString($vatLabelAr, $html, 'the Arabic VAT label must not appear in its raw, unshaped form');
        $this->assertStringContainsString(ArabicText::shape($vatLabelAr), $html, 'the Arabic VAT label must be glyph-shaped (joined) like every other Arabic label on the document');
    }
}
