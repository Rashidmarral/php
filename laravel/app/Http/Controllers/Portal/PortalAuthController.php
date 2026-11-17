<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PortalAuthController extends Controller
{
    /**
     * A real bcrypt hash of an arbitrary, unrelated string — see
     * App\Http\Controllers\Auth\AuthController's identical constant for why:
     * it keeps Hash::check() doing its normal work even for an unknown
     * email, so the response time can't be used to enumerate which client
     * emails exist.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$FlrzsJLeAb4wu.bqdtSE.eFBD83GOFAewNTCKlIEHoEH6sxl8MFSO';

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('client')->check()) {
            return redirect('/portal');
        }
        return view('portal.login', ['pageTitle' => 'Client Portal']);
    }

    public function login(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $client = Client::where('email', $email)->first();
        $company = $client ? Company::find($client->company_id) : null;
        $passwordMatches = Hash::check($password, $client->password ?? self::DUMMY_PASSWORD_HASH);

        $ok = $client
            && $client->portal_enabled
            && $client->password
            && $passwordMatches
            && $company
            && $company->client_portal_enabled
            && Feature::allowsForCompany('client_portal', $company);

        if (!$ok) {
            return $this->redirectWithFlash('/portal/login', 'error', t('portal.login_failed'));
        }

        Auth::guard('client')->login($client);
        $request->session()->regenerate();

        return redirect('/portal');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/portal/login');
    }
}
