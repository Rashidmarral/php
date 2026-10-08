<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Zatca\ZatcaXmlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $generator = new ZatcaXmlGenerator();
        $clients = Client::where('company_id', Auth::user()->company_id)->orderBy('name')->get();

        return view('app.clients.index', [
            // is_b2b drives the B2B badge in the list — same eligibility
            // check ZatcaXmlGenerator/ZatcaSyncService use for real
            // invoices, so the badge never disagrees with what actually
            // gets submitted to ZATCA.
            'clients' => $clients->map(fn ($c) => [...$c->toArray(), 'is_b2b' => $generator->isB2bEligible($c)])->all(),
        ]);
    }

    public function create(): View
    {
        return view('app.clients.form', ['client' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/clients/create', 'error', t('user.clients.name_required'));
        }
        [$data, $error] = $this->b2bData($request);
        if ($error) {
            return $this->redirectWithFlash('/app/clients/create', 'error', $error);
        }
        Client::create([
            'company_id' => Auth::user()->company_id,
            'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'address' => $request->input('address', ''),
            ...$data,
        ]);
        return $this->redirectWithFlash('/app/clients', 'success', t('user.clients.added'));
    }

    public function edit(int $id): View
    {
        return view('app.clients.form', ['client' => $this->findOwned($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $client = $this->findOwned($id);
        [$data, $error] = $this->b2bData($request);
        if ($error) {
            return $this->redirectWithFlash('/app/clients/' . $id . '/edit', 'error', $error);
        }
        $client->update([
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'address' => $request->input('address', ''),
            ...$data,
        ]);
        return $this->redirectWithFlash('/app/clients', 'success', t('user.clients.updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        return $this->redirectWithFlash('/app/clients', 'success', t('user.clients.removed'));
    }

    public function enablePortal(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('client_portal')) {
            return $redirect;
        }
        $client = $this->findOwned($id);

        if (empty($client->email)) {
            return $this->redirectWithFlash('/app/clients', 'error', t('user.clients.email_required_for_portal'));
        }

        $tempPassword = bin2hex(random_bytes(4));
        $client->update([
            'portal_enabled' => true,
            'password' => Hash::make($tempPassword),
        ]);

        return $this->redirectWithFlash('/app/clients', 'success', t('user.clients.portal_enabled', ['email' => $client->email, 'password' => $tempPassword]));
    }

    public function disablePortal(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->update(['portal_enabled' => false]);
        return $this->redirectWithFlash('/app/clients', 'success', t('user.clients.portal_disabled'));
    }

    /**
     * Pulls the client-level VAT/CR/structured-address fields out of the
     * request, mirroring SettingsController::update()'s plain
     * trim-and-collect style for Company's own equivalents. vat_number is
     * the one field format-validated (ZatcaXmlGenerator::
     * VAT_NUMBER_PATTERN — 15 digits, starting and ending with '3', the
     * same rule ZatcaXmlGenerator::isB2bEligible() checks before treating
     * this client as a standard/B2B buyer) — an invalid value is rejected
     * outright rather than silently saved and silently falling back to
     * simplified invoicing, since that's the kind of mistake that would
     * otherwise go unnoticed until a real ZATCA submission failed.
     *
     * @return array{0: array<string, string>, 1: ?string} [fields, errorMessage]
     */
    private function b2bData(Request $request): array
    {
        $vatNumber = trim((string) $request->input('vat_number', ''));
        if ($vatNumber !== '' && preg_match(ZatcaXmlGenerator::VAT_NUMBER_PATTERN, $vatNumber) !== 1) {
            return [[], 'VAT number must be exactly 15 digits, starting and ending with 3 (ZATCA format).'];
        }

        return [[
            'vat_number' => $vatNumber,
            'cr_number' => trim((string) $request->input('cr_number', '')),
            'building_number' => trim((string) $request->input('building_number', '')),
            'street_name' => trim((string) $request->input('street_name', '')),
            'district' => trim((string) $request->input('district', '')),
            'city' => trim((string) $request->input('city', '')),
            'postal_code' => trim((string) $request->input('postal_code', '')),
            'additional_number' => trim((string) $request->input('additional_number', '')),
        ], null];
    }

    private function findOwned(int $id): Client
    {
        $client = Client::find($id);
        abort_if(!$client || $client->company_id !== Auth::user()->company_id, 404, 'Client not found.');
        return $client;
    }
}
