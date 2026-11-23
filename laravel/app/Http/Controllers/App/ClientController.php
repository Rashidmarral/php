<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\PhoneNumber;
use App\Support\Zatca\ZatcaXmlGenerator;
use App\Support\ZatcaAddressValidator;
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
        [$contact, $contactError] = $this->contactFields($request);
        if ($contactError) {
            return $this->redirectWithFlash('/app/clients/create', 'error', $contactError);
        }
        [$data, $error] = $this->b2bData($request);
        if ($error) {
            return $this->redirectWithFlash('/app/clients/create', 'error', $error);
        }
        Client::create([
            'company_id' => Auth::user()->company_id,
            'name' => mb_substr($name, 0, 150),
            'name_ar' => mb_substr(trim((string) $request->input('name_ar', '')), 0, 150),
            ...$contact,
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
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/clients/' . $id . '/edit', 'error', t('user.clients.name_required'));
        }
        [$contact, $contactError] = $this->contactFields($request);
        if ($contactError) {
            return $this->redirectWithFlash('/app/clients/' . $id . '/edit', 'error', $contactError);
        }
        [$data, $error] = $this->b2bData($request);
        if ($error) {
            return $this->redirectWithFlash('/app/clients/' . $id . '/edit', 'error', $error);
        }
        $client->update([
            'name' => mb_substr($name, 0, 150),
            'name_ar' => mb_substr(trim((string) $request->input('name_ar', '')), 0, 150),
            ...$contact,
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
     * Pulls and validates the plain contact fields (email, phone, address) shared
     * by store()/update() — previously saved completely as-is with no trimming or
     * format check at all. email is rejected outright when present but malformed,
     * the same filter_var(FILTER_VALIDATE_EMAIL) rule TeamController::store()/
     * AdminProfileController::update() already use for their own email fields.
     * phone is rejected when present but not a recognizable Saudi mobile number,
     * via the shared App\Support\PhoneNumber::isValidSaudiMobile() — the same
     * +966 5x xxx xxxx format this form's own placeholder has always shown.
     * address has no format to check, only a length cap matching its column.
     *
     * @return array{0: array<string, string>, 1: ?string} [fields, errorMessage]
     */
    private function contactFields(Request $request): array
    {
        $email = trim((string) $request->input('email', ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [[], t('common.email_invalid')];
        }

        $phone = trim((string) $request->input('phone', ''));
        if (!PhoneNumber::isValidSaudiMobile($phone)) {
            return [[], t('common.phone_invalid')];
        }

        return [[
            'email' => mb_substr($email, 0, 150),
            'phone' => mb_substr($phone, 0, 30),
            'address' => mb_substr(trim((string) $request->input('address', '')), 0, 255),
        ], null];
    }

    /**
     * Pulls the client-level VAT/CR/structured-address fields out of the
     * request, mirroring SettingsController::update()'s plain
     * trim-and-collect style for Company's own equivalents. vat_number is
     * format-validated against ZatcaXmlGenerator::VAT_NUMBER_PATTERN — 15
     * digits, starting and ending with '3', the same rule
     * ZatcaXmlGenerator::isB2bEligible() checks before treating this
     * client as a standard/B2B buyer. building_number/postal_code/
     * additional_number/cr_number are format-validated too, via the
     * shared App\Support\ZatcaAddressValidator also used by
     * SettingsController::update() for Company's identical fields. Any
     * invalid value is rejected outright rather than silently saved,
     * since a malformed National Address field is exactly the kind of
     * mistake that would otherwise go unnoticed until a real ZATCA
     * submission failed.
     *
     * @return array{0: array<string, string>, 1: ?string} [fields, errorMessage]
     */
    private function b2bData(Request $request): array
    {
        $vatNumber = trim((string) $request->input('vat_number', ''));
        if ($vatNumber !== '' && preg_match(ZatcaXmlGenerator::VAT_NUMBER_PATTERN, $vatNumber) !== 1) {
            return [[], 'VAT number must be exactly 15 digits, starting and ending with 3 (ZATCA format).'];
        }

        $crNumber = trim((string) $request->input('cr_number', ''));
        $buildingNumber = trim((string) $request->input('building_number', ''));
        $postalCode = trim((string) $request->input('postal_code', ''));
        $additionalNumber = trim((string) $request->input('additional_number', ''));

        $addressError = ZatcaAddressValidator::validate($buildingNumber, $postalCode, $additionalNumber, $crNumber);
        if ($addressError) {
            return [[], $addressError];
        }

        return [[
            'vat_number' => $vatNumber,
            'cr_number' => $crNumber,
            'building_number' => $buildingNumber,
            'street_name' => trim((string) $request->input('street_name', '')),
            'district' => trim((string) $request->input('district', '')),
            'city' => trim((string) $request->input('city', '')),
            'postal_code' => $postalCode,
            'additional_number' => $additionalNumber,
        ], null];
    }

    private function findOwned(int $id): Client
    {
        $client = Client::find($id);
        abort_if(!$client || $client->company_id !== Auth::user()->company_id, 404, 'Client not found.');
        return $client;
    }
}
