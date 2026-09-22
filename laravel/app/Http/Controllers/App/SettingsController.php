<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const ALLOWED_LOGO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const ALLOWED_DOC_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** Profile tab: company identity (name/logo/contact) plus the ZATCA-compliant National Address. */
    public function index(): View
    {
        return view('app.settings.index', ['company' => Company::find(Auth::user()->company_id)->toArray()]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;
        $data = [
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'phone' => $request->input('phone', ''),
            'city' => $request->input('city', ''),
            'address' => $request->input('address', ''),
            'cr_number' => $request->input('cr_number', ''),
            'vat_number' => $request->input('vat_number', ''),
            'building_number' => trim((string) $request->input('building_number', '')),
            'street_name' => trim((string) $request->input('street_name', '')),
            'district' => trim((string) $request->input('district', '')),
            'postal_code' => trim((string) $request->input('postal_code', '')),
            'additional_number' => trim((string) $request->input('additional_number', '')),
        ];

        $logo = $request->file('logo');
        if ($logo && $logo->isValid()) {
            $mime = $logo->getMimeType();
            if (!isset(self::ALLOWED_LOGO_TYPES[$mime])) {
                return $this->redirectWithFlash('/app/settings', 'error', t('user.settings.logo_type_invalid'));
            }
            if ($logo->getSize() > 3 * 1024 * 1024) {
                return $this->redirectWithFlash('/app/settings', 'error', t('user.settings.logo_max_size'));
            }
            $filename = 'company-' . $companyId . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_LOGO_TYPES[$mime];
            $logo->move(public_path('uploads/logos'), $filename);
            $data['logo_path'] = "/uploads/logos/{$filename}";
        }

        Company::whereKey($companyId)->update($data);

        $this->flash('success', t('user.settings.company_updated'));
        return redirect('/app/settings');
    }

    /** Legal tab: CR/VAT certificate uploads plus the Muqawil contractor classification. */
    public function legal(): View
    {
        return view('app.settings.legal', ['company' => Company::find(Auth::user()->company_id)->toArray()]);
    }

    public function updateLegal(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;
        $data = [
            'contractor_classification' => $request->input('contractor_classification', ''),
            'contractor_classification_number' => trim((string) $request->input('contractor_classification_number', '')),
        ];

        $docError = $this->handleDocUpload($request, 'cr_document', $companyId, 'cr_document_path', $data);
        $docError = $docError ?: $this->handleDocUpload($request, 'vat_document', $companyId, 'vat_document_path', $data);
        if ($docError) {
            return $this->redirectWithFlash('/app/settings/legal', 'error', $docError);
        }

        Company::whereKey($companyId)->update($data);

        $this->flash('success', t('user.settings.company_updated'));
        return redirect('/app/settings/legal');
    }

    /** Business tab: pricing defaults, client portal, and the feature-gated approval-workflow toggles. */
    public function business(): View
    {
        return view('app.settings.business', ['company' => Company::find(Auth::user()->company_id)->toArray()]);
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;
        $data = [
            'default_markup_percent' => (float) $request->input('default_markup_percent', 0),
            'default_retention_percent' => (float) $request->input('default_retention_percent', 0),
            'client_portal_enabled' => $request->boolean('client_portal_enabled'),
        ];

        // Approval-workflow toggles are only ever persisted when the plan still
        // includes the feature — a company on a plan without it can't flip
        // these on from a stale form, and downgraded plans stop enforcing
        // (Company::requiresEstimateApproval()/requiresInvoiceApproval() also
        // re-check the feature at use time).
        if (\App\Support\Feature::allows('approval_workflow')) {
            $data['require_estimate_approval'] = $request->boolean('require_estimate_approval');
            $data['require_invoice_approval'] = $request->boolean('require_invoice_approval');
        }

        Company::whereKey($companyId)->update($data);

        $this->flash('success', t('user.settings.company_updated'));
        return redirect('/app/settings/business');
    }

    /**
     * Invoice Templates tab: a gallery of the 6 selectable PDF layouts (see
     * App\Support\Pdf\PdfTemplateStyles), each rendered live through the same
     * pdf.document view every real invoice/estimate/etc. uses — with this
     * company's own real name/logo/VAT/CR and a realistic sample line-item set
     * standing in for one of its own documents — so the previews are genuine
     * renders of that template's look, not static screenshots.
     */
    public function invoiceTemplates(): View
    {
        $company = Company::find(Auth::user()->company_id);
        $lang = app()->getLocale();
        $sample = $this->sampleInvoicePreviewData($company, $lang);

        $previews = [];
        foreach (Company::INVOICE_TEMPLATES as $key) {
            $html = view('pdf.document', array_merge($sample, ['template' => $key]))->render();
            // pdf.document's CSS relies on @page for its page margins, which a
            // browser only honors when actually printing — on screen (this iframe
            // preview) the body would otherwise keep the browser's own default
            // margin, throwing off templates whose head-band uses a negative
            // margin to bleed to the (print) page edge. Zeroing it here only
            // affects this on-screen preview, never the real PDF output.
            $html = str_replace('</head>', '<style>body{margin:0}</style></head>', $html);
            $previews[$key] = $html;
        }

        return view('app.settings.invoice-templates', [
            'previews' => $previews,
            'activeTemplate' => $company->activeInvoiceTemplate(),
        ]);
    }

    /** Sets the company-wide default PDF template — the fallback every document-PDF action uses when a download has no ?template= override. */
    public function activateInvoiceTemplate(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $template = (string) $request->input('template');
        if (!in_array($template, Company::INVOICE_TEMPLATES, true)) {
            return $this->redirectWithFlash('/app/settings/invoice-templates', 'error', t('user.settings.invoice_template_invalid'));
        }

        Company::whereKey(Auth::user()->company_id)->update(['invoice_template' => $template]);

        $this->flash('success', t('user.settings.invoice_template_activated'));
        return redirect('/app/settings/invoice-templates');
    }

    /**
     * Placeholder invoice data for the gallery previews above: the company's own
     * real branding fields, plus a fixed, realistic sample line-item set standing
     * in for a real document (this app has no other "sample data for preview"
     * convention to reuse, and a company's real invoices may not exist yet or may
     * not be representative). Deliberately mirrors the field shape
     * InvoiceController::pdf() builds for the real thing.
     */
    private function sampleInvoicePreviewData(Company $company, string $lang): array
    {
        $items = [
            ['description' => 'Excavation & sitework', 'qty' => 1, 'unit_price' => 12000, 'total' => 12000],
            ['description' => 'Concrete foundation', 'qty' => 1, 'unit_price' => 28500, 'total' => 28500],
            ['description' => 'Site supervision (monthly)', 'qty' => 3, 'unit_price' => 4000, 'total' => 12000],
        ];
        $subtotal = (float) array_sum(array_column($items, 'total'));
        $vatRate = 15;
        $vatAmount = round($subtotal * $vatRate / 100, 2);

        return [
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'فاتورة' : 'Invoice',
            'docNumber' => 'INV-0001',
            'docDate' => now(),
            'status' => $lang === 'ar' ? 'مرسلة' : 'Sent',
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, $company->vat_number ? 'VAT: ' . $company->vat_number : null, $company->cr_number ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            // Browser preview only — a plain public URL the iframe can load directly,
            // unlike the 'file://' + public_path() form the real dompdf-rendered PDF uses.
            'companyLogo' => $company->logo_path ?: null,
            'billTo' => ['name' => 'Al-Fahd Trading Est.', 'meta' => ['billing@alfahd.example.com', '+966 11 234 5678']],
            'items' => $items,
            'subtotal' => $subtotal,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => $vatRate,
            'vatAmount' => $vatAmount,
            'total' => $subtotal + $vatAmount,
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة ' . Setting::siteName() : 'Generated by ' . Setting::siteName(),
        ];
    }

    /** Security tab: the change-your-own-password form. */
    public function security(): View
    {
        return view('app.settings.security');
    }

    /** Any logged-in company user can change their own password — not gated by manage_company_settings. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $current = (string) $request->input('current_password');
        $new = (string) $request->input('new_password');
        $confirm = (string) $request->input('new_password_confirm');

        if (!Hash::check($current, $user->password)) {
            return $this->redirectWithFlash('/app/settings/security', 'error', t('user.settings.current_password_incorrect'));
        }
        if (strlen($new) < 8) {
            return $this->redirectWithFlash('/app/settings/security', 'error', t('user.settings.new_password_min_length'));
        }
        if ($new !== $confirm) {
            return $this->redirectWithFlash('/app/settings/security', 'error', t('user.settings.new_password_mismatch'));
        }

        $user->update(['password' => Hash::make($new)]);
        $this->flash('success', t('user.settings.password_updated'));
        return redirect('/app/settings/security');
    }

    /** @param array $data by reference — sets $column on success */
    private function handleDocUpload(Request $request, string $field, int $companyId, string $column, array &$data): ?string
    {
        $file = $request->file($field);
        if (!$file || !$file->isValid()) {
            return null;
        }
        $mime = $file->getMimeType();
        if (!isset(self::ALLOWED_DOC_TYPES[$mime])) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be a PDF, JPG, or PNG file.';
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be smaller than 10MB.';
        }
        $filename = "company-{$companyId}-{$field}-" . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_DOC_TYPES[$mime];
        $file->move(public_path('uploads/company-documents'), $filename);
        $data[$column] = "/uploads/company-documents/{$filename}";
        return null;
    }
}
