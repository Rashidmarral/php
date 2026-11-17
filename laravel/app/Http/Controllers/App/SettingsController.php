<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ApprovalChainStep;
use App\Models\Company;
use App\Models\User;
use App\Support\ZatcaAddressValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'cr_number' => trim((string) $request->input('cr_number', '')),
            'vat_number' => $request->input('vat_number', ''),
            'building_number' => trim((string) $request->input('building_number', '')),
            'street_name' => trim((string) $request->input('street_name', '')),
            'district' => trim((string) $request->input('district', '')),
            'postal_code' => trim((string) $request->input('postal_code', '')),
            'additional_number' => trim((string) $request->input('additional_number', '')),
        ];

        $addressError = ZatcaAddressValidator::validate(
            $data['building_number'],
            $data['postal_code'],
            $data['additional_number'],
            $data['cr_number']
        );
        if ($addressError) {
            return $this->redirectWithFlash('/app/settings', 'error', $addressError);
        }

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

        // Same kind of asset as the logo above, same upload rules — this is
        // the company stamp/seal Stage 2's 'card' and 'bilingual' PDF layout
        // partials already read from $company->stamp_path (see those
        // partials' own comments); the stamp column's migration is Stage 4's.
        $stamp = $request->file('stamp');
        if ($stamp && $stamp->isValid()) {
            $mime = $stamp->getMimeType();
            if (!isset(self::ALLOWED_LOGO_TYPES[$mime])) {
                return $this->redirectWithFlash('/app/settings', 'error', t('user.settings.stamp_type_invalid'));
            }
            if ($stamp->getSize() > 3 * 1024 * 1024) {
                return $this->redirectWithFlash('/app/settings', 'error', t('user.settings.stamp_max_size'));
            }
            $filename = 'company-' . $companyId . '-stamp-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_LOGO_TYPES[$mime];
            $stamp->move(public_path('uploads/stamps'), $filename);
            $data['stamp_path'] = "/uploads/stamps/{$filename}";
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

        // Validated against the same fixed 1-5 grade set the legal.blade.php <select> actually
        // offers — never trusted raw from the request, same "never a plain pass-through for a
        // closed enum field" rule this app applies to every other select.
        $classification = (string) $request->input('contractor_classification', '');
        if ($classification !== '' && !in_array($classification, ['1', '2', '3', '4', '5'], true)) {
            return $this->redirectWithFlash('/app/settings/legal', 'error', t('user.settings.classification_grade_invalid'));
        }

        $data = [
            'contractor_classification' => $classification,
            'contractor_classification_number' => mb_substr(trim((string) $request->input('contractor_classification_number', '')), 0, 50),
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

    /** Business tab: pricing defaults, client portal, and the feature-gated approval-workflow toggles (plus each toggle's optional multi-step chain refinement). */
    public function business(): View
    {
        $company = Company::find(Auth::user()->company_id);
        return view('app.settings.business', [
            'company' => $company->toArray(),
            'estimateChainSteps' => $company->approvalChainFor('estimate'),
            'invoiceChainSteps' => $company->approvalChainFor('invoice'),
        ]);
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;

        // decimal(5,2) columns: rejected (not silently cast/clamped) when non-numeric or
        // out of range — the same (float)-cast-with-no-check gap closed in every prior batch.
        // Markup has no business-logic ceiling below the column's own (999.99); retention can
        // never sensibly exceed 100%.
        $defaultMarkup = $this->validatedNumberOrDefault($request->input('default_markup_percent', 0), 0, 999.99, 0.0);
        if ($defaultMarkup === null) {
            return $this->redirectWithFlash('/app/settings/business', 'error', t('user.settings.default_markup_invalid'));
        }
        $defaultRetention = $this->validatedNumberOrDefault($request->input('default_retention_percent', 0), 0, 100, 0.0);
        if ($defaultRetention === null) {
            return $this->redirectWithFlash('/app/settings/business', 'error', t('user.settings.default_retention_invalid'));
        }

        $data = [
            'default_markup_percent' => $defaultMarkup,
            'default_retention_percent' => $defaultRetention,
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
     * Saves each document type's ordered chain of approval steps — a purely
     * additive refinement of the require_estimate_approval/require_invoice_approval
     * toggles above, never a separate on/off switch: a document type's posted
     * steps are only ever persisted when that type's own toggle is on and the
     * plan still includes approval_workflow, exactly like updateBusiness()'s own
     * guard around those toggles. Posting an empty/all-blank set of steps for a
     * document type is how a company goes back to the plain single-step flow for
     * it, which already behaves correctly on its own (ApprovalChain::startIfChained()
     * no-ops whenever approvalChainFor() comes back empty).
     */
    public function updateApprovalChain(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);
        $planAllows = \App\Support\Feature::allows('approval_workflow');

        $requiresApproval = [
            'estimate' => $planAllows && $company->requiresEstimateApproval(),
            'invoice' => $planAllows && $company->requiresInvoiceApproval(),
        ];

        foreach ($requiresApproval as $documentType => $enabled) {
            ApprovalChainStep::where('company_id', $companyId)->where('document_type', $documentType)->delete();

            if (!$enabled) {
                continue;
            }

            $roles = (array) $request->input($documentType . '_step_role', []);
            $labels = (array) $request->input($documentType . '_step_label', []);
            $labelsAr = (array) $request->input($documentType . '_step_label_ar', []);

            $now = now();
            $rows = [];
            $order = 1;
            foreach ($roles as $i => $role) {
                if ($order > ApprovalChainStep::MAX_STEPS) {
                    break;
                }
                $role = trim((string) $role);
                if (!in_array($role, ApprovalChainStep::ROLES, true)) {
                    continue;
                }
                $rows[] = [
                    'company_id' => $companyId,
                    'document_type' => $documentType,
                    'step_order' => $order,
                    'role_required' => $role,
                    'label' => mb_substr(trim((string) ($labels[$i] ?? '')), 0, 255) ?: null,
                    'label_ar' => mb_substr(trim((string) ($labelsAr[$i] ?? '')), 0, 255) ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $order++;
            }
            if ($rows) {
                ApprovalChainStep::insert($rows);
            }
        }

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
        // bcrypt (Laravel's default hasher) silently truncates anything past 72 bytes, so an
        // unbounded input wouldn't error — it would just hash to the same value as a 72-byte
        // prefix, which is confusing rather than dangerous, but still worth rejecting outright.
        if (strlen($new) > 128) {
            return $this->redirectWithFlash('/app/settings/security', 'error', t('user.settings.new_password_max_length'));
        }
        if ($new !== $confirm) {
            return $this->redirectWithFlash('/app/settings/security', 'error', t('user.settings.new_password_mismatch'));
        }

        $user->update(['password' => Hash::make($new)]);

        // Matches AuthController::resetPassword()'s same reasoning: anyone who
        // already had a session open under the OLD password (a shared/public
        // computer, a stolen session) is signed out everywhere else the moment
        // it changes. The request's own current session is deliberately kept,
        // since the person doing this is sitting in it right now.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

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

    /** Rejects a non-numeric or out-of-range value instead of silently casting/clamping it — see EstimateController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected. */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $this->validatedNumber($value, $min, $max);
    }
}
