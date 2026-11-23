<?php

namespace App\Http\Controllers\SupplierPortal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Supplier;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Exact mirror of Portal\PortalAuthController's logic and timing-safety, for the
 * supplier/vendor portal's own `supplier` guard. Unlike the client portal, there is no
 * `companies.supplier_portal_enabled` toggle to additionally check: the client portal's own
 * column is a SEPARATE staff on/off switch beyond its plan feature flag (see
 * SettingsController::update()), but nothing elsewhere in this app depends on an equivalent
 * column existing for suppliers, so gating on `suppliers.portal_enabled` (per-supplier) plus
 * the `supplier_portal` Feature flag (per-plan) alone is enough — adding an unused company
 * column would just be speculative.
 */
class SupplierPortalAuthController extends Controller
{
    /**
     * A real bcrypt hash of an arbitrary, unrelated string — see
     * PortalAuthController's identical constant for why: it keeps Hash::check() doing its
     * normal work even for an unknown email, so the response time can't be used to
     * enumerate which supplier emails exist.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$FlrzsJLeAb4wu.bqdtSE.eFBD83GOFAewNTCKlIEHoEH6sxl8MFSO';

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('supplier')->check()) {
            return redirect('/supplier-portal');
        }
        return view('supplier-portal.login', ['pageTitle' => 'Supplier Portal']);
    }

    public function login(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $supplier = Supplier::where('email', $email)->first();
        $company = $supplier ? Company::find($supplier->company_id) : null;
        $passwordMatches = Hash::check($password, $supplier->password ?? self::DUMMY_PASSWORD_HASH);

        $ok = $supplier
            && $supplier->portal_enabled
            && $supplier->password
            && $passwordMatches
            && $company
            && Feature::allowsForCompany('supplier_portal', $company);

        if (!$ok) {
            // Platform-level only (no company_id) — same reasoning as
            // AuthController::login()'s/PortalAuthController::login()'s identical call: a
            // failed login belongs in the admin audit log, not a company's own Activity Log page.
            AuditLog::record(null, 'supplier_portal_login_failed', $supplier ? 'supplier' : null, $supplier?->id, "email attempted: {$email}");
            return $this->redirectWithFlash('/supplier-portal/login', 'error', t('supplier_portal.login_failed'));
        }

        Auth::guard('supplier')->login($supplier);
        $request->session()->regenerate();

        return redirect('/supplier-portal');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('supplier')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/supplier-portal/login');
    }
}
