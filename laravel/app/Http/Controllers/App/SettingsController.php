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
            'default_markup_percent' => (float) $request->input('default_markup_percent', 0),
            'default_retention_percent' => (float) $request->input('default_retention_percent', 0),
            'contractor_classification' => $request->input('contractor_classification', ''),
            'contractor_classification_number' => trim((string) $request->input('contractor_classification_number', '')),
            'client_portal_enabled' => $request->boolean('client_portal_enabled'),
        ];

        $logo = $request->file('logo');
        if ($logo && $logo->isValid()) {
            $mime = $logo->getMimeType();
            if (!isset(self::ALLOWED_LOGO_TYPES[$mime])) {
                return $this->redirectWithFlash('/app/settings', 'error', 'Logo must be a JPG, PNG, or WEBP image.');
            }
            if ($logo->getSize() > 3 * 1024 * 1024) {
                return $this->redirectWithFlash('/app/settings', 'error', 'Logo must be smaller than 3MB.');
            }
            $filename = 'company-' . $companyId . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_LOGO_TYPES[$mime];
            $logo->move(public_path('uploads/logos'), $filename);
            $data['logo_path'] = "/uploads/logos/{$filename}";
        }

        $docError = $this->handleDocUpload($request, 'cr_document', $companyId, 'cr_document_path', $data);
        $docError = $docError ?: $this->handleDocUpload($request, 'vat_document', $companyId, 'vat_document_path', $data);
        if ($docError) {
            return $this->redirectWithFlash('/app/settings', 'error', $docError);
        }

        Company::whereKey($companyId)->update($data);

        $this->flash('success', 'Company settings updated.');
        return redirect('/app/settings');
    }

    /** Any logged-in company user can change their own password — not gated by manage_company_settings. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $current = (string) $request->input('current_password');
        $new = (string) $request->input('new_password');
        $confirm = (string) $request->input('new_password_confirm');

        if (!Hash::check($current, $user->password)) {
            return $this->redirectWithFlash('/app/settings', 'error', 'Your current password is incorrect.');
        }
        if (strlen($new) < 8) {
            return $this->redirectWithFlash('/app/settings', 'error', 'New password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            return $this->redirectWithFlash('/app/settings', 'error', 'New password and confirmation do not match.');
        }

        $user->update(['password' => Hash::make($new)]);
        $this->flash('success', 'Password updated.');
        return redirect('/app/settings');
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
