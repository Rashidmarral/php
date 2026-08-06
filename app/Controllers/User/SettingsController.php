<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Company;

class SettingsController extends Controller
{
    private const ALLOWED_LOGO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const ALLOWED_DOC_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function index(): void
    {
        $company = Company::find(Auth::companyId());
        $this->view('user/settings/index', ['pageTitle' => 'Settings', 'company' => $company], 'layouts/app');
    }

    public function update(): void
    {
        $this->verifyCsrf();
        if (!Auth::isCompanyOwner()) {
            $this->flash('error', 'Only the company owner can update company settings.');
            self::redirect('/app/settings');
        }

        $companyId = Auth::companyId();
        $data = [
            'name' => trim((string) $this->input('name')),
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'phone' => $this->input('phone', ''),
            'city' => $this->input('city', ''),
            'address' => $this->input('address', ''),
            'cr_number' => $this->input('cr_number', ''),
            'vat_number' => $this->input('vat_number', ''),
            'building_number' => trim((string) $this->input('building_number', '')),
            'street_name' => trim((string) $this->input('street_name', '')),
            'district' => trim((string) $this->input('district', '')),
            'postal_code' => trim((string) $this->input('postal_code', '')),
            'additional_number' => trim((string) $this->input('additional_number', '')),
            'default_markup_percent' => (float) $this->input('default_markup_percent', 0),
            'default_retention_percent' => (float) $this->input('default_retention_percent', 0),
            'client_portal_enabled' => $this->input('client_portal_enabled') ? 1 : 0,
        ];

        if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($_FILES['logo']['tmp_name']);
            if (!isset(self::ALLOWED_LOGO_TYPES[$mime])) {
                $this->flash('error', 'Logo must be a JPG, PNG, or WEBP image.');
                self::redirect('/app/settings');
            }
            if ($_FILES['logo']['size'] > 3 * 1024 * 1024) {
                $this->flash('error', 'Logo must be smaller than 3MB.');
                self::redirect('/app/settings');
            }
            $dir = BASE_PATH . "/public/uploads/logos";
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $filename = 'company-' . $companyId . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_LOGO_TYPES[$mime];
            move_uploaded_file($_FILES['logo']['tmp_name'], "{$dir}/{$filename}");
            $data['logo_path'] = "/uploads/logos/{$filename}";
        }

        $docError = $this->handleDocUpload('cr_document', $companyId, 'cr_document_path', $data);
        $docError = $docError ?: $this->handleDocUpload('vat_document', $companyId, 'vat_document_path', $data);
        if ($docError) {
            $this->flash('error', $docError);
            self::redirect('/app/settings');
        }

        Company::update($companyId, $data);

        $this->flash('success', 'Company settings updated.');
        self::redirect('/app/settings');
    }

    /** @param array $data by reference — sets $column on success */
    private function handleDocUpload(string $field, int $companyId, string $column, array &$data): ?string
    {
        if (empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $mime = mime_content_type($_FILES[$field]['tmp_name']);
        if (!isset(self::ALLOWED_DOC_TYPES[$mime])) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be a PDF, JPG, or PNG file.';
        }
        if ($_FILES[$field]['size'] > 10 * 1024 * 1024) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be smaller than 10MB.';
        }
        $dir = BASE_PATH . '/public/uploads/company-documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = "company-{$companyId}-{$field}-" . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_DOC_TYPES[$mime];
        move_uploaded_file($_FILES[$field]['tmp_name'], "{$dir}/{$filename}");
        $data[$column] = "/uploads/company-documents/{$filename}";
        return null;
    }
}
