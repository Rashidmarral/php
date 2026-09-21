<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class SiteSettingsController extends Controller
{
    private const ALLOWED_LOGO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const ALLOWED_DOC_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** Public marketing pages the admin can set a hero background image/video for (see Media Library). */
    public const HERO_PAGES = [
        'home' => 'Homepage', 'about' => 'About Us', 'contact' => 'Contact',
        'support' => 'Help Center', 'security' => 'Security & Compliance',
    ];

    public function index(): View
    {
        return view('admin.settings.index', ['settings' => Setting::all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $trialDays = max(0, (int) $request->input('trial_days', 14));
        $vatRate = (float) $request->input('vat_rate', 15);

        Setting::set('trial_days', (string) $trialDays);
        Setting::set('vat_rate', (string) $vatRate);
        Setting::set('currency', (string) $request->input('currency', 'SAR'));
        Setting::set('site_name', (string) $request->input('site_name', 'BuildXact Saudi'));
        Setting::set('support_email', (string) $request->input('support_email', ''));
        Setting::set('support_phone', (string) $request->input('support_phone', ''));

        return $this->redirectWithFlash('/admin/settings', 'success', t('admin.settings.platform_updated'));
    }

    public function legal(): View
    {
        return view('admin.settings.legal', ['settings' => Setting::all()]);
    }

    public function updateLegal(Request $request): RedirectResponse
    {
        foreach ([
            'platform_legal_name_en', 'platform_legal_name_ar', 'platform_vat_number', 'platform_cr_number',
            'platform_building_number', 'platform_street_name', 'platform_district', 'platform_city',
            'platform_postal_code', 'platform_additional_number',
        ] as $key) {
            Setting::set($key, trim((string) $request->input($key, '')));
        }

        $uploadError = $this->handleUpload($request, 'logo', self::ALLOWED_LOGO_TYPES, 'platform_logo_path', 3);
        $uploadError = $uploadError ?: $this->handleUpload($request, 'cr_document', self::ALLOWED_DOC_TYPES, 'platform_cr_document_path', 10);
        $uploadError = $uploadError ?: $this->handleUpload($request, 'vat_document', self::ALLOWED_DOC_TYPES, 'platform_vat_document_path', 10);

        if ($uploadError) {
            return $this->redirectWithFlash('/admin/settings/legal', 'error', $uploadError);
        }
        return $this->redirectWithFlash('/admin/settings/legal', 'success', t('admin.settings.legal_updated'));
    }

    /** @return string|null error message, or null on success/no file provided */
    private function handleUpload(Request $request, string $field, array $allowedTypes, string $settingKey, int $maxMb): ?string
    {
        /** @var UploadedFile|null $file */
        $file = $request->file($field);
        if (!$file || !$file->isValid()) {
            return null;
        }
        $mime = $file->getMimeType();
        if (!isset($allowedTypes[$mime])) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be a ' . implode('/', array_unique($allowedTypes)) . ' file.';
        }
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return ucfirst(str_replace('_', ' ', $field)) . " must be smaller than {$maxMb}MB.";
        }
        $filename = $field . '-' . bin2hex(random_bytes(6)) . '.' . $allowedTypes[$mime];
        $file->move(public_path('uploads/platform'), $filename);
        Setting::set($settingKey, "/uploads/platform/{$filename}");
        return null;
    }

    public function header(): View
    {
        return view('admin.settings.header', ['settings' => Setting::all(), 'heroPages' => self::HERO_PAGES]);
    }

    public function updateHeader(Request $request): RedirectResponse
    {
        foreach ([
            'header_phone',
            'footer_tagline_en', 'footer_tagline_ar',
            'footer_cities_en', 'footer_cities_ar',
            'footer_bottom_note_en', 'footer_bottom_note_ar',
            'social_facebook_url', 'social_twitter_url', 'social_instagram_url',
            'social_linkedin_url', 'social_whatsapp_url',
        ] as $key) {
            Setting::set($key, trim((string) $request->input($key, '')));
        }

        foreach (array_keys(self::HERO_PAGES) as $page) {
            Setting::set("hero_image_{$page}", trim((string) $request->input("hero_image_{$page}", '')));
            Setting::set("hero_video_{$page}", trim((string) $request->input("hero_video_{$page}", '')));
        }

        return $this->redirectWithFlash('/admin/settings/header', 'success', t('admin.settings.header_updated'));
    }

    /** Default navy/amber theme — used whenever the admin hasn't overridden a color. */
    public const THEME_DEFAULTS = [
        'theme_brand' => '#16233f', 'theme_brand_dark' => '#0a1428', 'theme_brand_light' => '#edf0f8',
        'theme_accent' => '#f0932b', 'theme_accent_dark' => '#c9720f',
    ];

    public function theme(): View
    {
        $settings = Setting::all();
        $colors = [];
        foreach (self::THEME_DEFAULTS as $key => $default) {
            $value = $settings[$key] ?? '';
            $colors[$key] = $value !== '' ? $value : $default;
        }
        return view('admin.settings.theme', ['colors' => $colors]);
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        if ($request->boolean('reset')) {
            foreach (array_keys(self::THEME_DEFAULTS) as $key) {
                Setting::set($key, '');
            }
            return $this->redirectWithFlash('/admin/settings/theme', 'success', t('admin.settings.theme_reset'));
        }

        foreach (array_keys(self::THEME_DEFAULTS) as $key) {
            $value = trim((string) $request->input($key, ''));
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                Setting::set($key, $value);
            }
        }

        return $this->redirectWithFlash('/admin/settings/theme', 'success', t('admin.settings.theme_updated'));
    }

    public function ai(): View
    {
        return view('admin.settings.ai', ['settings' => Setting::all()]);
    }

    public function updateAi(Request $request): RedirectResponse
    {
        Setting::set('ai_enabled', $request->boolean('ai_enabled') ? '1' : '0');
        Setting::set('ai_model', trim((string) $request->input('ai_model', '')));
        $apiKey = trim((string) $request->input('ai_api_key', ''));
        if ($apiKey !== '') {
            Setting::set('ai_api_key', $apiKey);
        }

        return $this->redirectWithFlash('/admin/settings/ai', 'success', t('admin.settings.ai_updated'));
    }

    public function notifications(): View
    {
        return view('admin.settings.notifications', ['settings' => Setting::all()]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        Setting::set('whatsapp_enabled', $request->boolean('whatsapp_enabled') ? '1' : '0');
        Setting::set('whatsapp_phone_number_id', trim((string) $request->input('whatsapp_phone_number_id', '')));
        $token = trim((string) $request->input('whatsapp_access_token', ''));
        if ($token !== '') {
            Setting::set('whatsapp_access_token', $token);
        }

        Setting::set('sms_enabled', $request->boolean('sms_enabled') ? '1' : '0');
        Setting::set('sms_sender_id', trim((string) $request->input('sms_sender_id', '')));
        $appSid = trim((string) $request->input('sms_app_sid', ''));
        if ($appSid !== '') {
            Setting::set('sms_app_sid', $appSid);
        }

        return $this->redirectWithFlash('/admin/settings/notifications', 'success', t('admin.settings.notifications_updated'));
    }

    public function email(): View
    {
        return view('admin.settings.email', ['settings' => Setting::all()]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        Setting::set('smtp_enabled', $request->boolean('smtp_enabled') ? '1' : '0');
        Setting::set('smtp_host', trim((string) $request->input('smtp_host', '')));
        Setting::set('smtp_port', (string) (int) $request->input('smtp_port', 587));
        Setting::set('smtp_encryption', in_array($request->input('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? $request->input('smtp_encryption') : 'tls');
        Setting::set('smtp_username', trim((string) $request->input('smtp_username', '')));
        $password = (string) $request->input('smtp_password', '');
        if ($password !== '') {
            Setting::set('smtp_password', $password);
        }
        Setting::set('smtp_from_email', trim((string) $request->input('smtp_from_email', '')));
        Setting::set('smtp_from_name', trim((string) $request->input('smtp_from_name', 'BuildXact Saudi')));

        return $this->redirectWithFlash('/admin/settings/email', 'success', t('admin.settings.email_updated'));
    }

    public function payments(): View
    {
        return view('admin.settings.payments', ['settings' => Setting::all()]);
    }

    public function updatePayments(Request $request): RedirectResponse
    {
        Setting::set('bank_transfer_enabled', $request->boolean('bank_transfer_enabled') ? '1' : '0');
        Setting::set('bank_name', (string) $request->input('bank_name', ''));
        Setting::set('bank_account_name', (string) $request->input('bank_account_name', ''));
        Setting::set('bank_iban', (string) $request->input('bank_iban', ''));
        Setting::set('bank_account_number', (string) $request->input('bank_account_number', ''));

        Setting::set('moyasar_enabled', $request->boolean('moyasar_enabled') ? '1' : '0');
        Setting::set('moyasar_publishable_key', (string) $request->input('moyasar_publishable_key', ''));
        $secret = trim((string) $request->input('moyasar_secret_key', ''));
        if ($secret !== '') {
            Setting::set('moyasar_secret_key', $secret);
        }

        return $this->redirectWithFlash('/admin/settings/payments', 'success', t('admin.settings.payments_updated'));
    }
}
