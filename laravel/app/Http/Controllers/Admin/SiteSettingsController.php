<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
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

    /** This app is KSA-only today — a single option, but keyed generically (default_country) rather than hardcoded, so a future market isn't a rewrite. */
    public const COUNTRIES = ['SA' => 'Saudi Arabia'];

    /** A short, realistic list — this is a single-currency (SAR) platform; ZATCA/VAT features assume SAR. */
    public const CURRENCIES = ['SAR', 'USD', 'AED', 'EUR'];

    public const TIMEZONES = ['Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Europe/London', 'UTC'];

    public const DATE_FORMATS = ['d/m/Y', 'm/d/Y', 'Y-m-d'];

    public const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function index(): View
    {
        return view('admin.settings.index', ['settings' => Setting::all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $vatRate = (float) $request->input('vat_rate', 15);

        Setting::set('vat_rate', (string) $vatRate);
        Setting::set('site_name', (string) $request->input('site_name', 'BuildXact Saudi'));
        Setting::set('support_email', (string) $request->input('support_email', ''));
        Setting::set('support_phone', (string) $request->input('support_phone', ''));

        $currency = $request->input('currency', 'SAR');
        Setting::set('currency', in_array($currency, self::CURRENCIES, true) ? $currency : 'SAR');

        $country = $request->input('default_country', 'SA');
        Setting::set('default_country', array_key_exists($country, self::COUNTRIES) ? $country : 'SA');

        $timezone = $request->input('default_timezone', 'Asia/Riyadh');
        Setting::set('default_timezone', in_array($timezone, self::TIMEZONES, true) ? $timezone : 'Asia/Riyadh');

        $language = $request->input('default_language', 'en');
        Setting::set('default_language', in_array($language, ['en', 'ar'], true) ? $language : 'en');

        $dateFormat = $request->input('date_format', 'd/m/Y');
        Setting::set('date_format', in_array($dateFormat, self::DATE_FORMATS, true) ? $dateFormat : 'd/m/Y');

        $timeFormat = $request->input('time_format', '24');
        Setting::set('time_format', $timeFormat === '12' ? '12' : '24');

        $fiscalYearStart = (int) $request->input('fiscal_year_start', 1);
        Setting::set('fiscal_year_start', (string) (array_key_exists($fiscalYearStart, self::MONTHS) ? $fiscalYearStart : 1));

        Setting::set('allow_new_registrations', $request->boolean('allow_new_registrations') ? '1' : '0');
        Setting::set('allow_demo_accounts', $request->boolean('allow_demo_accounts') ? '1' : '0');

        return $this->redirectWithFlash('/admin/settings', 'success', t('admin.settings.platform_updated'));
    }

    public function identity(): View
    {
        return view('admin.settings.identity', ['settings' => Setting::all()]);
    }

    public function updateIdentity(Request $request): RedirectResponse
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
            return $this->redirectWithFlash('/admin/settings/identity', 'error', $uploadError);
        }
        return $this->redirectWithFlash('/admin/settings/identity', 'success', t('admin.settings.legal_updated'));
    }

    /** Kept at its old URL so nothing bookmarked to /admin/settings/legal breaks. */
    public function legalRedirect(): RedirectResponse
    {
        return redirect('/admin/settings/identity');
    }

    /** @return string|null error message, or null on success/no file provided */
    private function handleUpload(Request $request, string $field, array $allowedTypes, string $settingKey, int $defaultMaxMb): ?string
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
        $maxMb = $this->effectiveMaxUploadMb($defaultMaxMb);
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return ucfirst(str_replace('_', ' ', $field)) . " must be smaller than {$maxMb}MB.";
        }
        $filename = $field . '-' . bin2hex(random_bytes(6)) . '.' . $allowedTypes[$mime];
        $file->move(public_path('uploads/platform'), $filename);
        Setting::set($settingKey, "/uploads/platform/{$filename}");
        return null;
    }

    /**
     * The admin-configured Settings → Storage → "Max upload size (MB)" only ever TIGHTENS a given
     * upload's own built-in limit, never loosens it — e.g. setting it to 5 shrinks the 10MB document
     * cap to 5MB but leaves the 3MB logo cap alone. This makes it a real, enforced ceiling without
     * risking an admin accidentally opening up a much larger upload than a field was built for.
     */
    public static function effectiveMaxUploadMb(int $defaultMb): int
    {
        $configured = (int) Setting::get('max_upload_size_mb', '0');
        return $configured > 0 ? min($configured, $defaultMb) : $defaultMb;
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

    public function branding(): View
    {
        $settings = Setting::all();
        $colors = [];
        foreach (self::THEME_DEFAULTS as $key => $default) {
            $value = $settings[$key] ?? '';
            $colors[$key] = $value !== '' ? $value : $default;
        }
        return view('admin.settings.branding', ['colors' => $colors]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        if ($request->boolean('reset')) {
            foreach (array_keys(self::THEME_DEFAULTS) as $key) {
                Setting::set($key, '');
            }
            return $this->redirectWithFlash('/admin/settings/branding', 'success', t('admin.settings.theme_reset'));
        }

        foreach (array_keys(self::THEME_DEFAULTS) as $key) {
            $value = trim((string) $request->input($key, ''));
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                Setting::set($key, $value);
            }
        }

        return $this->redirectWithFlash('/admin/settings/branding', 'success', t('admin.settings.theme_updated'));
    }

    /** Kept at its old URL so nothing bookmarked to /admin/settings/theme breaks. */
    public function themeRedirect(): RedirectResponse
    {
        return redirect('/admin/settings/branding');
    }

    public function signup(): View
    {
        return view('admin.settings.signup', ['settings' => Setting::all()]);
    }

    public function updateSignup(Request $request): RedirectResponse
    {
        $trialDays = max(0, (int) $request->input('trial_days', 14));
        Setting::set('trial_days', (string) $trialDays);

        return $this->redirectWithFlash('/admin/settings/signup', 'success', t('admin.settings.signup_updated'));
    }

    public function maintenance(): View
    {
        return view('admin.settings.maintenance', ['settings' => Setting::all()]);
    }

    public function updateMaintenance(Request $request): RedirectResponse
    {
        Setting::set('maintenance_mode', $request->boolean('maintenance_mode') ? '1' : '0');
        Setting::set('maintenance_message_en', trim((string) $request->input('maintenance_message_en', '')));
        Setting::set('maintenance_message_ar', trim((string) $request->input('maintenance_message_ar', '')));

        return $this->redirectWithFlash('/admin/settings/maintenance', 'success', t('admin.settings.maintenance_updated'));
    }

    /** "Features" — every optional platform capability that's off by default: AI, WhatsApp, SMS. */
    public function features(): View
    {
        return view('admin.settings.features', ['settings' => Setting::all()]);
    }

    public function updateFeatures(Request $request): RedirectResponse
    {
        Setting::set('ai_enabled', $request->boolean('ai_enabled') ? '1' : '0');
        Setting::set('ai_model', trim((string) $request->input('ai_model', '')));
        $apiKey = trim((string) $request->input('ai_api_key', ''));
        if ($apiKey !== '') {
            Setting::set('ai_api_key', $apiKey);
        }

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

        return $this->redirectWithFlash('/admin/settings/features', 'success', t('admin.settings.features_updated'));
    }

    /** Kept at their old URLs so nothing bookmarked to /admin/settings/ai or /notifications breaks. */
    public function aiRedirect(): RedirectResponse
    {
        return redirect('/admin/settings/features');
    }

    public function notificationsRedirect(): RedirectResponse
    {
        return redirect('/admin/settings/features');
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

    public function storage(): View
    {
        return view('admin.settings.storage', [
            'settings' => Setting::all(),
            'disk' => config('filesystems.default'),
            'appUsageBytes' => $this->directorySize(storage_path('app')),
            'uploadsUsageBytes' => $this->directorySize(public_path('uploads')),
        ]);
    }

    public function updateStorage(Request $request): RedirectResponse
    {
        $maxMb = max(0, (int) $request->input('max_upload_size_mb', 0));
        Setting::set('max_upload_size_mb', (string) $maxMb);

        return $this->redirectWithFlash('/admin/settings/storage', 'success', t('admin.settings.storage_updated'));
    }

    /**
     * A plain recursive sum of file sizes — computed fresh on each page load rather than cached or
     * polled. storage/app and public/uploads are modest directories for this app, so this is cheap;
     * it isn't meant to scale to a directory with millions of files.
     */
    private function directorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $bytes += $file->getSize();
            }
        }
        return $bytes;
    }

    public function system(): View
    {
        return view('admin.settings.system', [
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environment' => app()->environment(),
            'debugMode' => (bool) config('app.debug'),
            'dbDriver' => config('database.default'),
            'dbConnection' => config('database.connections.' . config('database.default') . '.database'),
        ]);
    }

    /** Safe, commonly-needed cache maintenance — gated by admin.super at the route level, same as every other destructive-ish admin action. */
    public function clearCache(): RedirectResponse
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return $this->redirectWithFlash('/admin/settings/system', 'success', t('admin.settings.cache_cleared'));
    }

    public function clearViews(): RedirectResponse
    {
        Artisan::call('view:clear');

        return $this->redirectWithFlash('/admin/settings/system', 'success', t('admin.settings.views_cleared'));
    }
}
