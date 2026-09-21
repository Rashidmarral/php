<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
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
