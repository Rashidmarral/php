<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Settings;

class SiteSettingsController extends Controller
{
    private const ALLOWED_LOGO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const ALLOWED_DOC_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function index(): void
    {
        $this->view('admin/settings/index', [
            'pageTitle' => 'Platform Settings',
            'settings' => Settings::all(),
        ], 'layouts/admin');
    }

    public function update(): void
    {
        $this->verifyCsrf();

        $trialDays = (int) $this->input('trial_days', 14);
        if ($trialDays < 0) {
            $trialDays = 0;
        }
        $vatRate = (float) $this->input('vat_rate', 15);

        Settings::set('trial_days', (string) $trialDays);
        Settings::set('vat_rate', (string) $vatRate);
        Settings::set('currency', (string) $this->input('currency', 'SAR'));
        Settings::set('site_name', (string) $this->input('site_name', 'BuildXact Saudi'));
        Settings::set('support_email', (string) $this->input('support_email', ''));
        Settings::set('support_phone', (string) $this->input('support_phone', ''));

        $this->flash('success', 'Platform settings updated.');
        self::redirect('/admin/settings');
    }

    public function legal(): void
    {
        $this->view('admin/settings/legal', [
            'pageTitle' => 'Legal & Branding',
            'settings' => Settings::all(),
        ], 'layouts/admin');
    }

    public function updateLegal(): void
    {
        $this->verifyCsrf();

        foreach (['platform_legal_name_en', 'platform_legal_name_ar', 'platform_vat_number', 'platform_cr_number',
                  'platform_building_number', 'platform_street_name', 'platform_district', 'platform_city',
                  'platform_postal_code', 'platform_additional_number'] as $key) {
            Settings::set($key, trim((string) $this->input($key, '')));
        }

        $uploadError = $this->handleUpload('logo', self::ALLOWED_LOGO_TYPES, 'platform_logo_path', 3);
        $uploadError = $uploadError ?: $this->handleUpload('cr_document', self::ALLOWED_DOC_TYPES, 'platform_cr_document_path', 10);
        $uploadError = $uploadError ?: $this->handleUpload('vat_document', self::ALLOWED_DOC_TYPES, 'platform_vat_document_path', 10);

        if ($uploadError) {
            $this->flash('error', $uploadError);
        } else {
            $this->flash('success', 'Legal & branding settings updated.');
        }
        self::redirect('/admin/settings/legal');
    }

    /** @return string|null error message, or null on success/no file provided */
    private function handleUpload(string $field, array $allowedTypes, string $settingKey, int $maxMb): ?string
    {
        if (empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $mime = mime_content_type($_FILES[$field]['tmp_name']);
        if (!isset($allowedTypes[$mime])) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be a ' . implode('/', array_unique($allowedTypes)) . ' file.';
        }
        if ($_FILES[$field]['size'] > $maxMb * 1024 * 1024) {
            return ucfirst(str_replace('_', ' ', $field)) . " must be smaller than {$maxMb}MB.";
        }
        $dir = BASE_PATH . '/public/uploads/platform';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = $field . '-' . bin2hex(random_bytes(6)) . '.' . $allowedTypes[$mime];
        move_uploaded_file($_FILES[$field]['tmp_name'], "{$dir}/{$filename}");
        Settings::set($settingKey, "/uploads/platform/{$filename}");
        return null;
    }

    public function notifications(): void
    {
        $this->view('admin/settings/notifications', [
            'pageTitle' => 'Notifications',
            'settings' => Settings::all(),
        ], 'layouts/admin');
    }

    public function updateNotifications(): void
    {
        $this->verifyCsrf();

        Settings::set('whatsapp_enabled', $this->input('whatsapp_enabled') ? '1' : '0');
        Settings::set('whatsapp_phone_number_id', trim((string) $this->input('whatsapp_phone_number_id', '')));
        $token = trim((string) $this->input('whatsapp_access_token', ''));
        if ($token !== '') {
            Settings::set('whatsapp_access_token', $token);
        }

        $this->flash('success', 'Notification settings updated.');
        self::redirect('/admin/settings/notifications');
    }

    public function payments(): void
    {
        $this->view('admin/settings/payments', [
            'pageTitle' => 'Payment Settings',
            'settings' => Settings::all(),
        ], 'layouts/admin');
    }

    public function updatePayments(): void
    {
        $this->verifyCsrf();

        Settings::set('bank_transfer_enabled', $this->input('bank_transfer_enabled') ? '1' : '0');
        Settings::set('bank_name', (string) $this->input('bank_name', ''));
        Settings::set('bank_account_name', (string) $this->input('bank_account_name', ''));
        Settings::set('bank_iban', (string) $this->input('bank_iban', ''));
        Settings::set('bank_account_number', (string) $this->input('bank_account_number', ''));

        Settings::set('moyasar_enabled', $this->input('moyasar_enabled') ? '1' : '0');
        Settings::set('moyasar_publishable_key', (string) $this->input('moyasar_publishable_key', ''));
        $secret = trim((string) $this->input('moyasar_secret_key', ''));
        if ($secret !== '') {
            Settings::set('moyasar_secret_key', $secret);
        }

        $this->flash('success', 'Payment settings updated.');
        self::redirect('/admin/settings/payments');
    }
}
