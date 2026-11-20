<?php

namespace Tests\Feature;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The per-download "Language" (English/Arabic) and old 6-preset "PDF
 * Template" pickers have been removed from every document's download
 * button — the company's own InvoiceTemplate is now the single source of
 * truth for a document's language, replacing a manual per-download choice.
 * Controller::pdfLangFor() is the one place left that decides: a company
 * with no custom template still follows the viewer's own app-language
 * preference (the $fallback argument, unchanged behaviour from before),
 * while a company that HAS customized a template is driven entirely by
 * that template's language_mode/table_direction, regardless of $fallback.
 */
class PdfLangFallbackTest extends TestCase
{
    use DatabaseTransactions;

    /** Exposes the protected Controller::pdfLangFor() for direct testing. */
    private function pdfLangFor(?InvoiceTemplate $invoiceTemplate, ?string $fallback = null): string
    {
        $controller = new class extends Controller
        {
            public function call(?InvoiceTemplate $invoiceTemplate, ?string $fallback): string
            {
                return $this->pdfLangFor($invoiceTemplate, $fallback);
            }
        };

        return $controller->call($invoiceTemplate, $fallback);
    }

    public function test_no_template_falls_back_to_the_given_fallback_language(): void
    {
        $this->assertSame('en', $this->pdfLangFor(null, 'en'));
        $this->assertSame('ar', $this->pdfLangFor(null, 'ar'));
    }

    public function test_no_template_and_no_fallback_falls_back_to_the_current_app_locale(): void
    {
        app()->setLocale('ar');
        $this->assertSame('ar', $this->pdfLangFor(null));

        app()->setLocale('en');
        $this->assertSame('en', $this->pdfLangFor(null));
    }

    public function test_arabic_only_template_always_wins_over_the_fallback(): void
    {
        $template = new InvoiceTemplate(['language_mode' => 'arabic_only', 'table_direction' => 'ltr']);
        $this->assertSame('ar', $this->pdfLangFor($template, 'en'));
    }

    public function test_english_only_template_always_wins_over_the_fallback(): void
    {
        $template = new InvoiceTemplate(['language_mode' => 'english_only', 'table_direction' => 'rtl']);
        $this->assertSame('en', $this->pdfLangFor($template, 'ar'));
    }

    public function test_bilingual_template_follows_its_own_table_direction_not_the_fallback(): void
    {
        $rtl = new InvoiceTemplate(['language_mode' => 'bilingual', 'table_direction' => 'rtl']);
        $this->assertSame('ar', $this->pdfLangFor($rtl, 'en'));

        $ltr = new InvoiceTemplate(['language_mode' => 'bilingual', 'table_direction' => 'ltr']);
        $this->assertSame('en', $this->pdfLangFor($ltr, 'ar'));
    }

    /**
     * End-to-end smoke check: the real download route still works now that
     * the language picker is gone — a company with no custom template
     * downloads successfully whichever language the viewer is browsing in.
     */
    public function test_invoice_pdf_route_still_renders_successfully_with_no_lang_or_template_params(): void
    {
        $company = Company::create(['name' => 'Al Rashid Construction Co.', 'email' => 'lang-fallback@example.com']);
        $owner = User::create([
            'company_id' => $company->id, 'name' => 'Owner', 'email' => 'owner-lang-fallback@example.com',
            'password' => Hash::make('password'), 'role' => 'owner', 'status' => 'active',
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sample Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id,
            'invoice_number' => 'INV-LF-0001', 'status' => 'unpaid', 'total' => 100,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Work', 'qty' => 1, 'unit_price' => 100, 'total' => 100]);

        $response = $this->withSession(['lang' => 'ar'])->actingAs($owner)->get("/app/invoices/{$invoice->id}/pdf");

        $response->assertStatus(200);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
