<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Core\Moyasar;
use App\Models\Company;

class IntegrationController extends Controller
{
    public function __construct()
    {
        Feature::requireOrRedirect('integrations');
    }

    public function index(): void
    {
        $company = Company::find(Auth::companyId());
        $this->view('user/integrations/index', [
            'pageTitle' => 'Integrations',
            'company' => $company,
            'moyasarConfigured' => Moyasar::isConfigured(),
            'clientMoyasarConfigured' => Moyasar::isConfiguredForCompany($company),
        ], 'layouts/app');
    }

    public function updateClientPayments(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_company_settings');

        $companyId = Auth::companyId();
        $data = [
            'moyasar_enabled' => $this->input('moyasar_enabled') ? 1 : 0,
            'moyasar_publishable_key' => trim((string) $this->input('moyasar_publishable_key', '')),
        ];
        $secret = trim((string) $this->input('moyasar_secret_key', ''));
        if ($secret !== '') {
            $data['moyasar_secret_key'] = $secret;
        }

        Company::update($companyId, $data);
        $this->flash('success', 'Client payment settings updated.');
        self::redirect('/app/integrations');
    }

    public function updateGoogleSheets(): void
    {
        $this->verifyCsrf();
        if (!Auth::isCompanyOwner()) {
            $this->flash('error', 'Only the company owner can change integration settings.');
            self::redirect('/app/integrations');
        }

        $url = trim((string) $this->input('price_sync_url'));
        if ($url !== '') {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $host = parse_url($url, PHP_URL_HOST);
            if ($scheme !== 'https' || !in_array(strtolower((string) $host), ['docs.google.com', 'sheets.googleapis.com'], true)) {
                $this->flash('error', 'Please paste a valid https://docs.google.com link (published to web as CSV).');
                self::redirect('/app/integrations');
            }
        }

        Company::update(Auth::companyId(), ['price_sync_url' => $url]);
        $this->flash('success', 'Google Sheets link saved.');
        self::redirect('/app/integrations');
    }
}
