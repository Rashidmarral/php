<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Totp;
use App\Support\Zatca\QrGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Personal two-factor authentication setup for the logged-in platform
 * admin (super_admin/support_admin) — same underlying `users` columns and
 * Totp class as App\Http\Controllers\App\SecurityController, kept as its
 * own small controller/view rather than a shared abstraction, matching
 * this codebase's existing preference (e.g. CreditNoteController vs
 * DebitNoteController) for two parallel controllers over a clever
 * common base.
 */
class SecurityController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            if (!$user->two_factor_secret) {
                $user->forceFill(['two_factor_secret' => Totp::generateSecret()])->save();
            }

            $otpauthUri = Totp::provisioningUri($user->two_factor_secret, $user->email, Setting::siteName());

            return view('admin.security.index', [
                'enabled' => false,
                'secret' => $user->two_factor_secret,
                'qr' => QrGenerator::renderSvgDataUri($otpauthUri),
            ]);
        }

        return view('admin.security.index', [
            'enabled' => true,
            'recoveryCodes' => session('2fa_recovery_codes'),
            'recoveryCodesRemaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function confirmEnable(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user->hasTwoFactorEnabled()) {
            return redirect('/admin/security');
        }
        if (!$user->two_factor_secret) {
            return $this->redirectWithFlash('/admin/security', 'error', 'Please start two-factor setup again.');
        }

        $code = trim((string) $request->input('code'));
        if (!Totp::verify($user->two_factor_secret, $code)) {
            return $this->redirectWithFlash('/admin/security', 'error', "That code didn't match — check your authenticator app and try again.");
        }

        $this->issueRecoveryCodesAndConfirm($request, $user);

        $this->flash('success', 'Two-factor authentication is now enabled on your account.');
        return redirect('/admin/security');
    }

    /** Requires the current password OR a valid authenticator code — never lets a hijacked-but-logged-in
     *  session turn 2FA off with nothing but the still-open session. */
    public function disable(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->hasTwoFactorEnabled()) {
            return redirect('/admin/security');
        }

        if (!$this->reauthenticated($request, $user)) {
            return $this->redirectWithFlash('/admin/security', 'error', 'Enter your current password or a valid authenticator code to disable two-factor authentication.');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->flash('success', 'Two-factor authentication has been disabled.');
        return redirect('/admin/security');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->hasTwoFactorEnabled()) {
            return redirect('/admin/security');
        }

        if (!$this->reauthenticated($request, $user)) {
            return $this->redirectWithFlash('/admin/security', 'error', 'Enter your current password or a valid authenticator code to regenerate recovery codes.');
        }

        $this->issueRecoveryCodes($request, $user);

        $this->flash('success', 'New recovery codes generated — your old codes no longer work.');
        return redirect('/admin/security');
    }

    private function reauthenticated(Request $request, $user): bool
    {
        $password = (string) $request->input('password', '');
        if ($password !== '' && Hash::check($password, $user->password)) {
            return true;
        }
        $code = trim((string) $request->input('code', ''));
        return $code !== '' && Totp::verify($user->two_factor_secret, $code);
    }

    /** Generates fresh recovery codes, stores them hashed (like a password — never in plaintext),
     *  and flashes the plaintext once so the setup page can show them exactly one time. */
    private function issueRecoveryCodes(Request $request, $user): void
    {
        $plainCodes = Totp::generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $plainCodes),
        ])->save();
        $request->session()->flash('2fa_recovery_codes', $plainCodes);
    }

    private function issueRecoveryCodesAndConfirm(Request $request, $user): void
    {
        $plainCodes = Totp::generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $plainCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->flash('2fa_recovery_codes', $plainCodes);
    }
}
