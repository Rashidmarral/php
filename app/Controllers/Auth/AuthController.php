<?php

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Settings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            self::redirect(Auth::isSuperAdmin() ? '/admin' : '/app');
        }
        $this->view('auth/login', ['pageTitle' => 'Log in'], 'layouts/auth');
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $email = trim((string) $this->input('email'));
        $password = (string) $this->input('password');

        if (Auth::attempt($email, $password)) {
            $this->clearOld();
            self::redirect(Auth::isSuperAdmin() ? '/admin' : '/app');
        }

        $this->flash('error', 'Invalid email or password, or your account is inactive.');
        $this->withOld(['email' => $email]);
        self::redirect('/login');
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            self::redirect(Auth::isSuperAdmin() ? '/admin' : '/app');
        }
        $plans = Plan::query('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $selected = $this->input('plan', $plans[1]['slug'] ?? ($plans[0]['slug'] ?? ''));
        $selectedCycle = $this->input('cycle') === 'yearly' ? 'yearly' : 'monthly';
        $this->view('auth/register', ['pageTitle' => 'Start your free trial', 'plans' => $plans, 'selected' => $selected, 'selectedCycle' => $selectedCycle], 'layouts/auth');
    }

    public function register(): void
    {
        $this->verifyCsrf();

        $companyName = trim((string) $this->input('company_name'));
        $name = trim((string) $this->input('name'));
        $email = strtolower(trim((string) $this->input('email')));
        $phone = trim((string) $this->input('phone'));
        $city = trim((string) $this->input('city'));
        $password = (string) $this->input('password');
        $planSlug = (string) $this->input('plan');

        $errors = [];
        if ($companyName === '') $errors[] = 'Company name is required.';
        if ($name === '') $errors[] = 'Your full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (User::first('email', $email)) $errors[] = 'An account with this email already exists.';

        $plan = Plan::first('slug', $planSlug);
        if (!$plan) $errors[] = 'Please choose a valid plan.';

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->withOld(['company_name' => $companyName, 'name' => $name, 'email' => $email, 'phone' => $phone, 'city' => $city]);
            $cycleParam = $this->input('cycle') === 'yearly' ? 'yearly' : 'monthly';
            self::redirect('/register?plan=' . urlencode($planSlug) . '&cycle=' . $cycleParam);
        }

        $trialDays = (int) Settings::get('trial_days', \App\Core\Env::get('TRIAL_DAYS', 14));
        $cycle = $this->input('cycle') === 'yearly' ? 'yearly' : 'monthly';
        // A trial_days of 0 (set by the platform admin) means "no free trial" — back-date
        // trial_ends_at so the account is immediately treated as expired and routed to checkout,
        // rather than silently granting unlimited free access with a day-level rounding gap.
        $trialEndsAt = date('Y-m-d', strtotime($trialDays > 0 ? "+{$trialDays} days" : '-1 day'));

        $companyId = Company::create([
            'name' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'city' => $city,
            'status' => 'trial',
            'plan_id' => $plan['id'],
            'trial_ends_at' => $trialEndsAt,
        ]);

        $userId = User::create([
            'company_id' => $companyId,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'owner',
            'status' => 'active',
        ]);

        Subscription::create([
            'company_id' => $companyId,
            'plan_id' => $plan['id'],
            'billing_cycle' => $cycle,
            'status' => 'trialing',
            'current_period_end' => $trialEndsAt,
        ]);

        $user = User::find($userId);
        Auth::login($user);
        $this->clearOld();

        if ($trialDays <= 0) {
            $this->flash('success', "Welcome to BuildXact Saudi! This account requires a subscription to activate — choose a plan below.");
            self::redirect('/app/billing/checkout?plan=' . urlencode($plan['slug']) . '&cycle=' . $cycle);
        }
        $this->flash('success', 'Welcome to BuildXact Saudi! Your ' . $trialDays . '-day free trial has started.');
        self::redirect('/app');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        self::redirect('/');
    }
}
