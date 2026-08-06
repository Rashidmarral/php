<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Company;

class SettingsController extends Controller
{
    private const ALLOWED_LOGO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

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
            'phone' => $this->input('phone', ''),
            'city' => $this->input('city', ''),
            'address' => $this->input('address', ''),
            'cr_number' => $this->input('cr_number', ''),
            'vat_number' => $this->input('vat_number', ''),
            'default_markup_percent' => (float) $this->input('default_markup_percent', 0),
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

        Company::update($companyId, $data);

        $this->flash('success', 'Company settings updated.');
        self::redirect('/app/settings');
    }
}
