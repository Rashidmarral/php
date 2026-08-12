<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PasswordReset;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Mailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AuthController extends Controller
{
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

        if (!$user || !Hash::check($credentials['password'], $user->password) || $user->status !== 'active') {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->isAdminStaff() ? '/admin' : '/app');
    }

    public function showRegister(): View
    {
        return view('auth.register', ['plans' => Plan::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'plan' => ['required', 'exists:plans,slug'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'team_size' => ['nullable', 'string', 'max:20'],
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();
        $trialDays = (int) (\App\Models\Setting::get('trial_days') ?? 14);

        [$company, $user] = DB::transaction(function () use ($data, $plan, $trialDays) {
            $company = Company::create([
                'name' => $data['company_name'],
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

        return redirect('/app')->with('flash.success', ['Welcome to ' . Setting::siteName() . '! Your ' . $trialDays . '-day trial has started.']);
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
        $genericMessage = "If an account exists for {$email}, we've sent a password reset link to it.";

        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? User::where('email', $email)->first() : null;
        if ($user && $user->status === 'active') {
            $token = bin2hex(random_bytes(32));
            PasswordReset::create([
                'email' => $email,
                'token' => $token,
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
            return $this->redirectWithFlash('/forgot-password', 'error', 'This password reset link is invalid or has expired. Please request a new one.');
        }
        return view('auth.reset-password', ['pageTitle' => 'Set a new password', 'token' => $token]);
    }

    public function resetPassword(Request $request, string $token): RedirectResponse
    {
        $reset = PasswordReset::findValid($token);
        if (!$reset) {
            return $this->redirectWithFlash('/forgot-password', 'error', 'This password reset link is invalid or has expired. Please request a new one.');
        }

        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirm');
        if (strlen($password) < 8) {
            return $this->redirectWithFlash('/reset-password/' . $token, 'error', 'Password must be at least 8 characters.');
        }
        if ($password !== $confirm) {
            return $this->redirectWithFlash('/reset-password/' . $token, 'error', 'Passwords do not match.');
        }

        $user = User::where('email', $reset->email)->first();
        if ($user) {
            $user->update(['password' => $password]);
        }
        $reset->update(['used_at' => now()]);

        return $this->redirectWithFlash('/login', 'success', 'Your password has been reset — you can now log in.');
    }
}
