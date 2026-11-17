<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PasswordReset;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Mailer;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Session key holding the id of a user who passed their password but still owes a 2FA code. Deliberately
     *  distinct from Auth's own session state — nothing about this key makes Auth::check() true. */
    private const PENDING_2FA_SESSION_KEY = '2fa_user_id';
    private const PENDING_2FA_ATTEMPTS_KEY = '2fa_attempts';
    private const MAX_2FA_ATTEMPTS = 5;

    /**
     * A real bcrypt hash of an arbitrary, unrelated string — compared against
     * on a login attempt for an email that doesn't exist, purely so
     * Hash::check() still does its normal (deliberately slow) work instead of
     * being skipped. Skipping it would make an unknown email's request
     * return measurably faster than a known email with a wrong password,
     * letting an attacker enumerate which emails have accounts by timing
     * the response alone.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$FlrzsJLeAb4wu.bqdtSE.eFBD83GOFAewNTCKlIEHoEH6sxl8MFSO';

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', strtolower(trim($credentials['email'])))->first();
        $passwordMatches = Hash::check($credentials['password'], $user->password ?? self::DUMMY_PASSWORD_HASH);

        if (!$user || !$passwordMatches || $user->status !== 'active') {
            // Platform-level only (no company_id) — this is a failed login attempt,
            // not a company's own business activity, so it belongs in the admin
            // audit log alone, not a company's Activity Log page. Never logs the
            // submitted password itself; only the email that was tried and, when
            // it matched a real account, which specific one.
            AuditLog::record(null, 'login_failed', $user ? 'user' : null, $user?->id, "email attempted: {$credentials['email']}");
            return back()->withErrors(['email' => t('auth.invalid_credentials')])->onlyInput('email');
        }

        if ($user->hasTwoFactorEnabled()) {
            // Password verified, but the account isn't authenticated yet — Auth::login() is
            // deliberately NOT called here. The pending state lives under its own session key
            // (never touched by the EnsureCompanyUser/EnsureAdminPanelAccess middleware, which
            // only ever check Auth::check()), so a session stuck at this stage cannot reach
            // /app or /admin — it is exactly as unauthenticated as no session at all.
            $request->session()->regenerate();
            $request->session()->put(self::PENDING_2FA_SESSION_KEY, $user->id);
            $request->session()->forget(self::PENDING_2FA_ATTEMPTS_KEY);

            return redirect('/login/2fa');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->isAdminStaff() ? '/admin' : '/app');
    }

    public function showTwoFactorChallenge(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has(self::PENDING_2FA_SESSION_KEY)) {
            return redirect('/login');
        }
        return view('auth.two-factor-challenge');
    }

    public function verifyTwoFactorChallenge(Request $request): RedirectResponse
    {
        $userId = $request->session()->get(self::PENDING_2FA_SESSION_KEY);
        if (!$userId) {
            return redirect('/login');
        }

        $user = User::find($userId);
        if (!$user || !$user->hasTwoFactorEnabled() || $user->status !== 'active') {
            $this->clearPendingTwoFactor($request);
            return redirect('/login')->withErrors(['email' => t('auth.invalid_credentials')]);
        }

        $code = trim((string) $request->input('code'));
        $verified = (preg_match('/^\d{6}$/', $code) && Totp::verify($user->two_factor_secret, $code))
            || $this->consumeRecoveryCode($user, $code);

        if (!$verified) {
            $attempts = (int) $request->session()->get(self::PENDING_2FA_ATTEMPTS_KEY, 0) + 1;

            if ($attempts >= self::MAX_2FA_ATTEMPTS) {
                $this->clearPendingTwoFactor($request);
                AuditLog::record(null, 'login_2fa_locked_out', 'user', $user->id, $user->email);
                return redirect('/login')->withErrors(['email' => t('auth.two_factor_too_many_attempts')]);
            }

            $request->session()->put(self::PENDING_2FA_ATTEMPTS_KEY, $attempts);
            $remaining = self::MAX_2FA_ATTEMPTS - $attempts;
            AuditLog::record(null, 'login_2fa_failed', 'user', $user->id, "{$user->email} ({$attempts}/" . self::MAX_2FA_ATTEMPTS . ')');
            return back()->withErrors(['code' => t('auth.two_factor_invalid_code', ['remaining' => $remaining])]);
        }

        $this->clearPendingTwoFactor($request);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->isAdminStaff() ? '/admin' : '/app');
    }

    private function clearPendingTwoFactor(Request $request): void
    {
        $request->session()->forget([self::PENDING_2FA_SESSION_KEY, self::PENDING_2FA_ATTEMPTS_KEY]);
    }

    /** Checks $code against the user's unused recovery codes and marks the matching one used (single-use). */
    private function consumeRecoveryCode(User $user, string $code): bool
    {
        if ($code === '') {
            return false;
        }
        $code = strtoupper($code);

        $hashedCodes = $user->two_factor_recovery_codes ?? [];
        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($hashedCodes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashedCodes)])->save();
                return true;
            }
        }
        return false;
    }

    public function showRegister(): View|RedirectResponse
    {
        if (!Setting::allowsNewRegistrations()) {
            return $this->redirectWithFlash('/pricing', 'error', t('auth.registrations_closed'));
        }
        return view('auth.register', ['plans' => Plan::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function register(Request $request): RedirectResponse
    {
        if (!Setting::allowsNewRegistrations()) {
            return $this->redirectWithFlash('/pricing', 'error', t('auth.registrations_closed'));
        }

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_name_ar' => ['nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan' => ['required', 'exists:plans,slug'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'team_size' => ['nullable', 'string', 'max:20'],
            'terms' => ['accepted'],
        ], [
            'password.confirmed' => t('auth.passwords_mismatch'),
            'terms.accepted' => t('auth.must_accept_terms'),
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();
        $trialDays = (int) (\App\Models\Setting::get('trial_days') ?? 14);

        [$company, $user] = DB::transaction(function () use ($data, $plan, $trialDays) {
            $company = Company::create([
                'name' => $data['company_name'],
                'name_ar' => $data['company_name_ar'] ?? null,
                'email' => strtolower(trim($data['email'])),
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'] ?? null,
                'cr_number' => $data['cr_number'] ?? null,
                'team_size' => $data['team_size'] ?? null,
                'status' => 'trial',
                'plan_id' => $plan->id,
                'trial_ends_at' => now()->addDays($trialDays),
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'],
                'role' => 'owner',
                'status' => 'active',
            ]);

            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'status' => 'trialing',
                'current_period_end' => now()->addDays($trialDays),
            ]);

            return [$company, $user];
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/app')->with('flash.success', [t('auth.trial_welcome', ['site' => Setting::siteName(), 'days' => $trialDays])]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function showForgotPassword(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect(Auth::user()->isAdminStaff() ? '/admin' : '/app');
        }
        return view('auth.forgot-password', ['pageTitle' => 'Reset your password']);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email')));

        // Always show the same message whether or not the account exists — confirming/denying
        // an email's existence here would let anyone enumerate registered accounts.
        $genericMessage = t('auth.password_reset_sent', ['email' => $email]);

        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? User::where('email', $email)->first() : null;
        if ($user && $user->status === 'active') {
            $token = bin2hex(random_bytes(32));
            // Only the token's digest is stored — see PasswordReset::findValid()'s
            // docblock for why. $token itself (the only thing that can satisfy that
            // lookup) goes out exclusively in the email below.
            PasswordReset::create([
                'email' => $email,
                'token' => hash('sha256', $token),
                'expires_at' => now()->addHour(),
            ]);

            if (Mailer::isConfigured()) {
                $resetUrl = rtrim((string) config('app.url'), '/') . '/reset-password/' . $token;
                Mailer::send(
                    $email,
                    $user->name,
                    'Reset your ' . Setting::siteName() . ' password',
                    "Hi {$user->name},\n\nSomeone (hopefully you) requested a password reset for your " . Setting::siteName() . " account.\n\nReset your password: {$resetUrl}\n\nThis link expires in 1 hour. If you didn't request this, you can safely ignore this email — your password won't change."
                );
            }
        }

        return $this->redirectWithFlash('/login', 'success', $genericMessage);
    }

    public function showResetPassword(string $token): View|RedirectResponse
    {
        if (!PasswordReset::findValid($token)) {
            return $this->redirectWithFlash('/forgot-password', 'error', t('auth.reset_link_invalid'));
        }
        return view('auth.reset-password', ['pageTitle' => 'Set a new password', 'token' => $token]);
    }

    public function resetPassword(Request $request, string $token): RedirectResponse
    {
        $reset = PasswordReset::findValid($token);
        if (!$reset) {
            return $this->redirectWithFlash('/forgot-password', 'error', t('auth.reset_link_invalid'));
        }

        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirm');
        if (strlen($password) < 8) {
            return $this->redirectWithFlash('/reset-password/' . $token, 'error', t('auth.password_min_length'));
        }
        if ($password !== $confirm) {
            return $this->redirectWithFlash('/reset-password/' . $token, 'error', t('auth.passwords_mismatch'));
        }

        $user = User::where('email', $reset->email)->first();
        if ($user) {
            $user->update(['password' => $password]);
            // A password reset is itself a strong signal the old password may be
            // compromised, so every session this user already holds — on any
            // device, from before this reset — is invalidated rather than left
            // valid until it naturally expires.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        }
        $reset->update(['used_at' => now()]);

        return $this->redirectWithFlash('/login', 'success', t('auth.password_reset_success'));
    }
}
