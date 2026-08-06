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
        ], 'layouts/app');
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
