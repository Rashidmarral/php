<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Webhook;
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
        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);

        return view('app.integrations.index', [
            'company' => $company->toArray(),
            'moyasarConfigured' => Moyasar::isConfigured(),
            'clientMoyasarConfigured' => Moyasar::isConfiguredForCompany($company->toArray()),
            'webhooks' => Webhook::where('company_id', $companyId)->orderByDesc('created_at')->get(),
            'webhookEvents' => Webhook::EVENTS,
            'apiTokens' => Auth::user()->tokens()->orderByDesc('created_at')->get(),
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

    // ---------------- Webhooks ----------------

    public function storeWebhook(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }
        $url = trim((string) $request->input('url'));
        $events = array_intersect((array) $request->input('events', []), array_keys(Webhook::EVENTS));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            return $this->redirectWithFlash('/app/integrations', 'error', 'Please enter a valid https:// webhook URL.');
        }
        if (empty($events)) {
            return $this->redirectWithFlash('/app/integrations', 'error', 'Choose at least one event to subscribe to.');
        }

        Webhook::create([
            'company_id' => Auth::user()->company_id,
            'url' => $url,
            'secret' => bin2hex(random_bytes(20)),
            'events' => json_encode(array_values($events)),
            'is_active' => true,
        ]);

        $this->flash('success', 'Webhook added.');
        return redirect('/app/integrations');
    }

    public function toggleWebhook(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }
        $webhook = $this->findOwnedWebhook($id);
        $webhook->update(['is_active' => !$webhook->is_active]);
        return redirect('/app/integrations');
    }

    public function destroyWebhook(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }
        $this->findOwnedWebhook($id)->delete();
        $this->flash('success', 'Webhook removed.');
        return redirect('/app/integrations');
    }

    private function findOwnedWebhook(int $id): Webhook
    {
        $webhook = Webhook::find($id);
        abort_if(!$webhook || $webhook->company_id !== Auth::user()->company_id, 404, 'Webhook not found.');
        return $webhook;
    }

    // ---------------- API tokens ----------------

    public function createApiToken(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name')) ?: 'API token';
        $plainTextToken = Auth::user()->createToken($name)->plainTextToken;

        return $this->redirectWithFlash('/app/integrations', 'success', 'API token created — copy it now, it will not be shown again: ' . $plainTextToken);
    }

    public function revokeApiToken(int $id): RedirectResponse
    {
        $token = Auth::user()->tokens()->find($id);
        abort_if(!$token, 404, 'Token not found.');
        $token->delete();

        $this->flash('success', 'API token revoked.');
        return redirect('/app/integrations');
    }
}
