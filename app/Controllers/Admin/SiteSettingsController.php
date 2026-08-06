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
