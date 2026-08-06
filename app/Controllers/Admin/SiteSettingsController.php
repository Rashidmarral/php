<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Settings;

class SiteSettingsController extends Controller
{
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
}
