<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 1 of bringing the PDF invoice template system up to the reference
 * product's richness: introduces a proper per-company, per-document-type
 * template model (App\Models\InvoiceTemplate) alongside — not yet replacing
 * — the existing Company::invoice_template string column. That column and
 * Company::activeInvoiceTemplate() are left completely unchanged here, and
 * so is the Invoice Templates settings gallery (SettingsController::
 * invoiceTemplates()/activateInvoiceTemplate()): a later stage rebuilds that
 * UI on top of this table and only then retires the old column. Until then,
 * a company with no row here simply has no customized template yet, which
 * Company::activeInvoiceTemplateFor() reports as null (see that method).
 *
 * Modeled on the Daftari reference product's invoice_templates table for
 * field breadth, adapted to this app's own document types and brand
 * identity (see App\Support\InvoiceTemplatePresets for the new presets).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            // This app's actual document-PDF types, confirmed against each
            // one's own controller pdf()/download action: EstimateController,
            // InvoiceController, PurchaseOrderController, CreditNoteController/
            // DebitNoteController, PaymentCertificateController, QuickEstimate's
            // PDF action, and ZakatController::pdf().
            $table->string('document_type', 24);
            // Which App\Support\InvoiceTemplatePresets entry (or, for a row
            // carried forward from the old Company::invoice_template column,
            // which legacy visual template key — modern/classic/minimal/bold/
            // elegant/saudi) this row started from. Free-form provenance, not
            // a strict foreign key: a template can be freely customized after
            // creation, so this only records where it began.
            $table->string('preset_key', 40)->nullable();
            $table->string('accent_color', 7)->default('#16233f');
            $table->string('table_header_color', 7)->nullable();
            $table->string('totals_color', 7)->nullable();
            /*
             * Layout family naming decision — a later stage branches its
             * rendering on this exact column, so the values are locked in
             * here and must not be casually renamed:
             *
             *   'card'       The shared rounded-card/shadow foundation that
             *                covers the visual space our existing modern/
             *                classic/minimal/bold/elegant PDF templates
             *                already occupy (see
             *                App\Support\Pdf\PdfTemplateStyles::variants()).
             *                `preset_key` is what distinguishes one
             *                card-family preset from another — `layout`
             *                only says "this is a card-style document".
             *   'bilingual'  The bordered, fully bilingual ZATCA-style
             *                layout — an upgrade path for our existing
             *                'saudi' template (see
             *                PdfTemplateStyles::saudiChrome()).
             *   'letterhead' A new family: the document prints onto a
             *                company-supplied letterhead image
             *                (letterhead_path) instead of a generated
             *                head-band or border.
             *
             * The reference product's 5 layout keys (minimal/bordered/
             * boxed/bilingual_classic/custom_letterhead) mix structural
             * family and visual preset into one enum. We deliberately split
             * that apart: `layout` here is structure only, and the visual
             * identity (color, look) lives in `preset_key` +
             * accent_color/table_header_color/totals_color instead — those
             * columns are already per-preset, not per-layout.
             */
            $table->string('layout', 20)->default('card');
            $table->string('density', 20)->default('compact');
            $table->string('language_mode', 20)->default('bilingual');
            $table->string('table_direction', 3)->default('ltr');
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_unit_labels')->default(true);
            $table->boolean('show_party_vat_number')->default(true);
            $table->boolean('show_item_description')->default(true);
            $table->boolean('show_vat_column')->default(true);
            $table->string('page_size', 10)->default('a4');
            $table->string('letterhead_path')->nullable();
            $table->string('footer_path')->nullable();
            $table->string('watermark_path')->nullable();
            $table->unsignedTinyInteger('watermark_opacity')->default(10);
            $table->text('notes_en')->nullable();
            $table->text('notes_ar')->nullable();
            $table->text('terms_en')->nullable();
            $table->text('terms_ar')->nullable();
            // One default per company PER document_type, not globally — see
            // Company::activeInvoiceTemplateFor(). Enforced at the
            // application layer (a later stage's controller), same as the
            // old single-column system enforced "one active template" by
            // only ever having one column to write to.
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'document_type']);
        });

        $this->migrateLegacyCompanyTemplates();
    }

    /**
     * Carries forward every company's existing, explicitly-chosen
     * Company::invoice_template value into a default 'invoice'
     * InvoiceTemplate row, so nobody's already-chosen look silently
     * disappears once a later stage starts preferring this table. A company
     * still sitting on the untouched 'modern' default (i.e. never made an
     * explicit choice) gets no row — Company::activeInvoiceTemplateFor()
     * returning null for it is the correct "nothing customized yet" signal,
     * identical to how a brand-new company with no rows at all behaves.
     */
    private function migrateLegacyCompanyTemplates(): void
    {
        // name/name_ar/accent_color mirror this app's own established look
        // for each legacy key (see the common.pdf_template_* translations
        // and PdfTemplateStyles::variants()/saudiChrome()) — this is not a
        // new preset, just carrying the existing choice's own identity
        // forward into the new table.
        $legacy = [
            'classic' => ['name' => 'Classic', 'name_ar' => 'كلاسيكي', 'accent_color' => '#16211f', 'layout' => 'card'],
            'minimal' => ['name' => 'Minimal', 'name_ar' => 'بسيط', 'accent_color' => '#16211f', 'layout' => 'card'],
            'bold' => ['name' => 'Bold', 'name_ar' => 'جريء', 'accent_color' => '#a8790a', 'layout' => 'card'],
            'elegant' => ['name' => 'Elegant', 'name_ar' => 'أنيق', 'accent_color' => '#8a7550', 'layout' => 'card'],
            'saudi' => ['name' => 'Saudi (ZATCA bilingual)', 'name_ar' => 'سعودي (ثنائي اللغة - زاتكا)', 'accent_color' => '#16211f', 'layout' => 'bilingual'],
        ];

        $now = now();
        $rows = [];

        // 'modern' is deliberately excluded from $legacy above: it's the
        // column's own default, so a company on it made no explicit choice
        // (see this method's docblock) — whereIn() below naturally skips it,
        // along with any stale/invalid value from before a template was
        // ever removed (Company::activeInvoiceTemplate() already treats
        // those the same as 'modern').
        $companies = DB::table('companies')
            ->whereNotNull('invoice_template')
            ->whereIn('invoice_template', array_keys($legacy))
            ->get(['id', 'invoice_template']);

        foreach ($companies as $company) {
            $preset = $legacy[$company->invoice_template];
            $rows[] = [
                'company_id' => $company->id,
                'name' => $preset['name'],
                'name_ar' => $preset['name_ar'],
                'document_type' => 'invoice',
                'preset_key' => $company->invoice_template,
                'accent_color' => $preset['accent_color'],
                'layout' => $preset['layout'],
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            DB::table('invoice_templates')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
