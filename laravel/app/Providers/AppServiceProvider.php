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
     *
     * Each closure below first asks $user->permissionOverride($ability) — a per-user
     * escape hatch (the 'permission_overrides' JSON column) that can grant or deny this
     * ONE ability for this ONE user beyond what their role would normally allow (e.g. an
     * estimator personally granted 'approve_documents' without promoting them to admin).
     * A null means "no override" and falls through to the exact same role logic that was
     * here before this feature existed — so a user with no overrides behaves identically
     * to today. This is deliberately NOT a new permission model: the overridable set is
     * exactly these 6 existing abilities, nothing more.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        Gate::define('manage_billing', fn (User $user) => $user->permissionOverride('manage_billing') ?? ($user->role === 'owner'));

        Gate::define('manage_team', fn (User $user) => $user->permissionOverride('manage_team') ?? in_array($user->role, ['owner', 'admin'], true));
        Gate::define('manage_company_settings', fn (User $user) => $user->permissionOverride('manage_company_settings') ?? in_array($user->role, ['owner', 'admin'], true));
        Gate::define('manage_business_setup', fn (User $user) => $user->permissionOverride('manage_business_setup') ?? in_array($user->role, ['owner', 'admin'], true));
        Gate::define('approve_documents', fn (User $user) => $user->permissionOverride('approve_documents') ?? in_array($user->role, ['owner', 'admin'], true));

        Gate::define('write', fn (User $user) => $user->permissionOverride('write') ?? in_array($user->role, ['owner', 'admin', 'estimator', 'accountant'], true));
    }
}
