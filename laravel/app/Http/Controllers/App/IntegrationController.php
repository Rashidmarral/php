<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Moyasar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('integrations')) {
            return $redirect;
        }
        $company = Company::find(Auth::user()->company_id);
        return view('app.integrations.index', [
            'company' => $company->toArray(),
            'moyasarConfigured' => Moyasar::isConfigured(),
            'clientMoyasarConfigured' => Moyasar::isConfiguredForCompany($company->toArray()),
        ]);
    }

    public function updateClientPayments(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('integrations')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;
        $data = [
            'moyasar_enabled' => $request->boolean('moyasar_enabled'),
            'moyasar_publishable_key' => trim((string) $request->input('moyasar_publishable_key', '')),
        ];
        $secret = trim((string) $request->input('moyasar_secret_key', ''));
        if ($secret !== '') {
            $data['moyasar_secret_key'] = $secret;
        }

        Company::whereKey($companyId)->update($data);
        $this->flash('success', 'Client payment settings updated.');
        return redirect('/app/integrations');
    }

    public function updateGoogleSheets(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('integrations')) {
            return $redirect;
        }
        if (!Auth::user()->isCompanyOwner()) {
            return $this->redirectWithFlash('/app/integrations', 'error', 'Only the company owner can change integration settings.');
        }

        $url = trim((string) $request->input('price_sync_url'));
        if ($url !== '') {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $host = parse_url($url, PHP_URL_HOST);
            if ($scheme !== 'https' || !in_array(strtolower((string) $host), ['docs.google.com', 'sheets.googleapis.com'], true)) {
                return $this->redirectWithFlash('/app/integrations', 'error', 'Please paste a valid https://docs.google.com link (published to web as CSV).');
            }
        }

        Company::whereKey(Auth::user()->company_id)->update(['price_sync_url' => $url]);
        $this->flash('success', 'Google Sheets link saved.');
        return redirect('/app/integrations');
    }
}
