<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Ability checks for company-panel users. 'owner'/'admin' can do everything except
     * billing is owner-only. 'estimator'/'accountant' get day-to-day write access to
     * operational data. 'viewer' is read-only everywhere. Platform super admins bypass
     * every check (Gate::before).
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        Gate::define('manage_billing', fn (User $user) => $user->role === 'owner');

        Gate::define('manage_team', fn (User $user) => in_array($user->role, ['owner', 'admin'], true));
        Gate::define('manage_company_settings', fn (User $user) => in_array($user->role, ['owner', 'admin'], true));
        Gate::define('manage_business_setup', fn (User $user) => in_array($user->role, ['owner', 'admin'], true));
        Gate::define('approve_documents', fn (User $user) => in_array($user->role, ['owner', 'admin'], true));

        Gate::define('write', fn (User $user) => in_array($user->role, ['owner', 'admin', 'estimator', 'accountant'], true));
    }
}
