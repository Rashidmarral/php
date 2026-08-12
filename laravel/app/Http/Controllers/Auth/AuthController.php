<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
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
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();
        $trialDays = (int) (\App\Models\Setting::get('trial_days') ?? 14);

        [$company, $user] = DB::transaction(function () use ($data, $plan, $trialDays) {
            $company = Company::create([
                'name' => $data['company_name'],
                'email' => strtolower(trim($data['email'])),
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

        return redirect('/app')->with('flash.success', ['Welcome to BuildXact Saudi! Your ' . $trialDays . '-day trial has started.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
