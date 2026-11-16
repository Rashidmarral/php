<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the "Use this design for every document type" fix: a company that
 * customizes only ONE document type's template (e.g. Estimate) previously had
 * every other type (Invoice, Credit Note, Purchase Order, ...) silently fall
 * back to the old plain 6-preset look — a real, user-reported inconsistency.
 * copyToAllTypes() propagates one template's full visual design onto every
 * other document type in one action.
 */
class InvoiceTemplateCopyToAllTest extends TestCase
{
    use DatabaseTransactions;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Al Rashid Construction Co.', 'email' => 'alrashid@example.com']);
        $owner = User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner-tpl@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    public function test_copy_to_all_creates_a_matching_default_template_for_every_other_document_type(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->makeOwner();

        $source = InvoiceTemplate::create([
            'company_id' => $company->id,
            'name' => 'Estimate Brown',
            'name_ar' => 'عرض بني',
            'document_type' => 'estimate',
            'preset_key' => 'bold',
            'accent_color' => '#8a5a1f',
            'table_header_color' => '#8a5a1f',
            'totals_color' => '#8a5a1f',
            'layout' => 'bilingual',
            'density' => 'compact',
            'language_mode' => 'bilingual',
            'table_direction' => 'rtl',
            'page_size' => 'a4',
            'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->post("/app/settings/invoice-templates/template/{$source->id}/copy-to-all");
        $response->assertRedirect();

        foreach (['invoice', 'credit_note', 'debit_note', 'purchase_order', 'quick_estimate', 'payment_certificate', 'zakat'] as $type) {
            $tpl = InvoiceTemplate::where('company_id', $company->id)->where('document_type', $type)->where('is_default', true)->first();
            $this->assertNotNull($tpl, "expected a default template for {$type}");
            $this->assertSame('#8a5a1f', $tpl->accent_color);
            $this->assertSame('bilingual', $tpl->layout);
            $this->assertSame($type, $tpl->document_type, 'the type keeps its own identity, only the design is shared');
        }

        // The source itself (estimate) is untouched and still the one default for its own type.
        $this->assertSame(1, InvoiceTemplate::where('company_id', $company->id)->where('document_type', 'estimate')->where('is_default', true)->count());
    }

    public function test_copy_to_all_overwrites_an_existing_default_rather_than_duplicating_it(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->makeOwner();

        $source = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Source', 'document_type' => 'estimate',
            'accent_color' => '#112233', 'layout' => 'card', 'is_default' => true,
        ]);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Old Invoice Look', 'document_type' => 'invoice',
            'accent_color' => '#ff0000', 'layout' => 'card', 'is_default' => true,
        ]);

        $this->actingAs($owner)->post("/app/settings/invoice-templates/template/{$source->id}/copy-to-all");

        $invoiceTemplates = InvoiceTemplate::where('company_id', $company->id)->where('document_type', 'invoice')->get();
        $this->assertCount(1, $invoiceTemplates, 'no duplicate row — the existing one was updated in place');
        $this->assertSame('#112233', $invoiceTemplates->first()->accent_color);
        $this->assertTrue((bool) $invoiceTemplates->first()->is_default);
    }

    public function test_copy_to_all_is_blocked_for_a_user_without_manage_company_settings(): void
    {
        ['company' => $company] = $this->makeOwner();
        $viewer = User::create([
            'company_id' => $company->id, 'name' => 'Viewer', 'email' => 'viewer-tpl@example.com',
            'password' => Hash::make('password'), 'role' => 'viewer', 'status' => 'active',
        ]);
        $source = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Source', 'document_type' => 'estimate',
            'accent_color' => '#112233', 'layout' => 'card', 'is_default' => true,
        ]);

        $this->actingAs($viewer)->post("/app/settings/invoice-templates/template/{$source->id}/copy-to-all");

        $this->assertSame(0, InvoiceTemplate::where('company_id', $company->id)->where('document_type', 'invoice')->count());
    }

    public function test_copy_to_all_is_blocked_across_companies(): void
    {
        ['company' => $companyA] = $this->makeOwner();
        $companyB = Company::create(['name' => 'Company B', 'email' => 'b@example.com']);
        $ownerB = User::create([
            'company_id' => $companyB->id, 'name' => 'Owner B', 'email' => 'owner-b-tpl@example.com',
            'password' => Hash::make('password'), 'role' => 'owner', 'status' => 'active',
        ]);
        $sourceA = InvoiceTemplate::create([
            'company_id' => $companyA->id, 'name' => 'Source A', 'document_type' => 'estimate',
            'accent_color' => '#112233', 'layout' => 'card', 'is_default' => true,
        ]);

        $response = $this->actingAs($ownerB)->post("/app/settings/invoice-templates/template/{$sourceA->id}/copy-to-all");

        $response->assertNotFound();
    }
}
