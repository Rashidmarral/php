<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Support\InvoiceTemplatePresets;
use App\Support\Pdf\InvoiceTemplatePreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stage 3 of the invoice-template project: the CRUD/workflow UI for Stage 1's
 * per-company, per-document-type App\Models\InvoiceTemplate rows and Stage 2's
 * document-v2/payment-certificate-v2 rendering — replacing the OLD
 * single-preset-per-company gallery (formerly SettingsController::
 * invoiceTemplates()/activateInvoiceTemplate() + app.settings.invoice-templates).
 *
 * Workflow (adapted from the Daftari reference product's own
 * User\InvoiceTemplateController, not copied verbatim): index() lists a
 * document type's existing named templates alongside the starter presets;
 * useTemplate() clones a preset into a new, editable row and sends the user
 * straight to edit(); edit()/update() is the full customization form.
 * `layout` is deliberately NEVER accepted by update() — see update()'s own
 * comment — since it is the structural family document-v2.blade.php
 * branches its rendering on; a company wanting a different family clones a
 * different preset via useTemplate() instead.
 *
 * A standalone controller (not folded into SettingsController) because this
 * feature — per-document-type rows, file uploads, default promotion — is
 * arguably its own module, the same size/shape as SubcontractController or
 * PurchaseOrderController, even though its views still live under the
 * Settings tab bar (see resources/views/app/settings/partials/tabs.blade.php).
 */
class InvoiceTemplateController extends Controller
{
    /**
     * This app's actual document-PDF types (see the invoice_templates
     * migration's own comment) — every activeInvoiceTemplateFor() call site
     * confirmed by grep across app/Http/Controllers/App/*.
     */
    public const DOCUMENT_TYPES = [
        'estimate', 'quick_estimate', 'invoice', 'credit_note',
        'debit_note', 'purchase_order', 'payment_certificate', 'zakat',
    ];

    private const DEFAULT_DOCUMENT_TYPE = 'invoice';

    /** Locked in by the invoice_templates migration's own comment — not user-editable past creation (see update()). */
    private const LAYOUTS = ['card', 'bilingual', 'letterhead'];

    private const DENSITIES = ['compact', 'comfortable'];
    private const LANGUAGE_MODES = ['bilingual', 'english_only', 'arabic_only'];
    private const TABLE_DIRECTIONS = ['ltr', 'rtl'];
    private const PAGE_SIZES = ['a4', 'letter'];

    /** Same whitelist as SettingsController's own company-logo upload (self::ALLOWED_LOGO_TYPES there). */
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_IMAGE_BYTES = 3 * 1024 * 1024;

    /**
     * The gallery: the selected document type's own named templates
     * (default-first) as live-preview cards, plus every starter preset
     * available to clone from. $documentType comes from the route segment
     * rather than a query string per the task's own proposed route shape;
     * anything not one of the 8 known types silently falls back to the
     * default type instead of 404ing, so a stale/bookmarked bad link still
     * lands somewhere useful.
     */
    public function index(Request $request, ?string $documentType = null): View
    {
        $documentType = in_array($documentType, self::DOCUMENT_TYPES, true) ? $documentType : self::DEFAULT_DOCUMENT_TYPE;
        $company = Company::find(Auth::user()->company_id);
        $lang = app()->getLocale();

        $templates = $company->invoiceTemplates()
            ->where('document_type', $documentType)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $templatePreviews = [];
        foreach ($templates as $template) {
            $templatePreviews[$template->id] = InvoiceTemplatePreview::render($company, $documentType, $lang, $template);
        }

        $presets = InvoiceTemplatePresets::all();
        $presetPreviews = [];
        foreach ($presets as $key => $preset) {
            $presetTemplate = new InvoiceTemplate($preset + ['document_type' => $documentType]);
            $presetPreviews[$key] = InvoiceTemplatePreview::render($company, $documentType, $lang, $presetTemplate);
        }

        return view('app.settings.invoice-templates.index', [
            'documentType' => $documentType,
            'documentTypes' => self::DOCUMENT_TYPES,
            'templates' => $templates,
            'templatePreviews' => $templatePreviews,
            'presets' => $presets,
            'presetPreviews' => $presetPreviews,
            'canManage' => Auth::user()->can('manage_company_settings'),
        ]);
    }

    /**
     * Clones a starter preset into a new, named, editable row for the
     * company + document type, then sends the user straight to edit() —
     * mirrors Daftari's useTemplate()'s $isFirst logic: the very first
     * template a company creates for a document type becomes its default
     * automatically, since Company::activeInvoiceTemplateFor() otherwise
     * has nothing to return for that type.
     */
    public function useTemplate(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $data = $request->validate([
            'preset' => ['required', Rule::in(array_keys(InvoiceTemplatePresets::all()))],
            'document_type' => ['required', Rule::in(self::DOCUMENT_TYPES)],
        ]);

        $company = Company::find(Auth::user()->company_id);
        $preset = InvoiceTemplatePresets::find($data['preset']);
        $isFirst = $company->invoiceTemplates()->where('document_type', $data['document_type'])->count() === 0;

        $template = $company->invoiceTemplates()->create([
            'name' => $preset['name'],
            'name_ar' => $preset['name_ar'],
            'document_type' => $data['document_type'],
            'preset_key' => $preset['preset_key'],
            'accent_color' => $preset['accent_color'],
            'layout' => $preset['layout'],
            'is_default' => $isFirst,
        ]);

        $this->flash('success', t('user.settings.invoice_template_created'));
        return redirect('/app/settings/invoice-templates/template/' . $template->id . '/edit');
    }

    /** The full customization form, plus a preview reflecting the template's last-saved state (see update()'s own comment on why not true live-as-you-type). */
    public function edit(int $id): View
    {
        $template = $this->findOwned($id);
        $company = Company::find(Auth::user()->company_id);
        $preview = InvoiceTemplatePreview::render($company, $template->document_type, app()->getLocale(), $template);

        return view('app.settings.invoice-templates.edit', [
            'template' => $template,
            'preview' => $preview,
            'densities' => self::DENSITIES,
            'languageModes' => self::LANGUAGE_MODES,
            'tableDirections' => self::TABLE_DIRECTIONS,
            'pageSizes' => self::PAGE_SIZES,
            'canManage' => Auth::user()->can('manage_company_settings'),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $template = $this->findOwned($id);

        // 'layout' is deliberately NOT among the validated/writable fields
        // here — see this controller's own class docblock and the
        // invoice_templates migration's comment: it is the structural
        // family document-v2.blade.php branches its whole rendering on, so
        // letting it change post-creation would silently break every other
        // customization made against the original family's markup. The
        // edit view never even submits a `layout` input for this reason.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'table_header_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'remove_table_header_color' => ['nullable', 'boolean'],
            'totals_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'remove_totals_color' => ['nullable', 'boolean'],
            'density' => ['required', Rule::in(self::DENSITIES)],
            'language_mode' => ['required', Rule::in(self::LANGUAGE_MODES)],
            'table_direction' => ['required', Rule::in(self::TABLE_DIRECTIONS)],
            'page_size' => ['required', Rule::in(self::PAGE_SIZES)],
            'watermark_opacity' => ['nullable', 'integer', 'between:1,100'],
            'notes_en' => ['nullable', 'string', 'max:2000'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'terms_en' => ['nullable', 'string', 'max:4000'],
            'terms_ar' => ['nullable', 'string', 'max:4000'],
        ]);

        $data['show_logo'] = $request->boolean('show_logo');
        $data['show_unit_labels'] = $request->boolean('show_unit_labels');
        $data['show_party_vat_number'] = $request->boolean('show_party_vat_number');
        $data['show_item_description'] = $request->boolean('show_item_description');
        $data['show_vat_column'] = $request->boolean('show_vat_column');
        $data['watermark_opacity'] = $data['watermark_opacity'] ?? $template->watermark_opacity;
        $data['table_header_color'] = $request->boolean('remove_table_header_color') ? null : ($data['table_header_color'] ?? $template->table_header_color);
        $data['totals_color'] = $request->boolean('remove_totals_color') ? null : ($data['totals_color'] ?? $template->totals_color);
        unset($data['remove_table_header_color'], $data['remove_totals_color']);

        $editPath = '/app/settings/invoice-templates/template/' . $id . '/edit';

        $error = $this->handleImageUpload($request, 'letterhead', $template, 'letterhead_path', $data)
            ?? $this->handleImageUpload($request, 'footer', $template, 'footer_path', $data)
            ?? $this->handleImageUpload($request, 'watermark', $template, 'watermark_path', $data);
        if ($error) {
            return $this->redirectWithFlash($editPath, 'error', $error);
        }

        $template->update($data);

        $this->flash('success', t('user.settings.invoice_template_saved'));
        return redirect($editPath);
    }

    /**
     * Deletes a template; if it was the document type's default, promotes
     * another remaining template for that same type (if any) so the type
     * isn't silently left with no default while other customized templates
     * for it still exist — Company::activeInvoiceTemplateFor() would
     * otherwise fall all the way back to the old 6-preset system even
     * though the company clearly still wants a customized look.
     */
    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $template = $this->findOwned($id);
        $documentType = $template->document_type;
        $companyId = $template->company_id;
        $wasDefault = (bool) $template->is_default;

        foreach (['letterhead_path', 'footer_path', 'watermark_path'] as $column) {
            if ($template->{$column}) {
                @unlink(public_path($template->{$column}));
            }
        }

        $template->delete();

        if ($wasDefault) {
            $promoted = InvoiceTemplate::where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->orderBy('id')
                ->first();
            $promoted?->update(['is_default' => true]);
        }

        $this->flash('success', t('user.settings.invoice_template_deleted'));
        return redirect('/app/settings/invoice-templates/' . $documentType);
    }

    /** Makes this row the default for its company + document type, unsetting any other row's default for that same type only — other document types' defaults are never touched. */
    public function setDefault(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $template = $this->findOwned($id);

        InvoiceTemplate::where('company_id', $template->company_id)
            ->where('document_type', $template->document_type)
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $template->update(['is_default' => true]);

        $this->flash('success', t('user.settings.invoice_template_default_set'));
        return redirect('/app/settings/invoice-templates/' . $template->document_type);
    }

    private function findOwned(int $id): InvoiceTemplate
    {
        $template = InvoiceTemplate::find($id);
        abort_if(!$template || $template->company_id !== Auth::user()->company_id, 404, 'Invoice template not found.');
        return $template;
    }

    /**
     * Validates + stores one of the three optional image uploads
     * (letterhead/footer/watermark), mirroring SettingsController's own
     * logo/legal-document upload pattern exactly: a manual mime whitelist
     * check, a size cap, then File::move() into a public/uploads subfolder
     * with a random filename — never Storage::disk('public'), which this
     * app doesn't use elsewhere. Returns a translated error string on
     * failure, or null on success (including "no file uploaded", the
     * common case). A `remove_{field}` checkbox deletes the current file
     * without requiring a new one.
     */
    private function handleImageUpload(Request $request, string $field, InvoiceTemplate $template, string $column, array &$data): ?string
    {
        $file = $request->file($field);

        if ($file && $file->isValid()) {
            $mime = $file->getMimeType();
            if (!isset(self::ALLOWED_IMAGE_TYPES[$mime])) {
                return t('user.settings.invoice_template_image_type_invalid');
            }
            if ($file->getSize() > self::MAX_IMAGE_BYTES) {
                return t('user.settings.invoice_template_image_max_size');
            }
            if ($template->{$column}) {
                @unlink(public_path($template->{$column}));
            }
            $filename = 'invoice-template-' . $template->company_id . '-' . $field . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_IMAGE_TYPES[$mime];
            $file->move(public_path('uploads/invoice-templates'), $filename);
            $data[$column] = '/uploads/invoice-templates/' . $filename;
            return null;
        }

        if ($request->boolean('remove_' . $field) && $template->{$column}) {
            @unlink(public_path($template->{$column}));
            $data[$column] = null;
        }

        return null;
    }
}
