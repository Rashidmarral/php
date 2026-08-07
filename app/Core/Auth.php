<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    /** Roles assignable to a company team member by the owner/admin. 'owner' is fixed to the account creator. */
    public const ASSIGNABLE_ROLES = [
        'admin' => 'Admin — full access except billing',
        'estimator' => 'Estimator — projects, clients, estimates & schedule',
        'accountant' => 'Accountant — invoices, payments & reports',
        'viewer' => 'Viewer — read-only access',
    ];

    public const ROLE_LABELS = ['owner' => 'Owner'] + self::ASSIGNABLE_ROLES;

    public const ROLE_SHORT_LABELS = [
        'owner' => 'Owner', 'admin' => 'Admin', 'estimator' => 'Estimator',
        'accountant' => 'Accountant', 'viewer' => 'Viewer',
    ];

    public static function attempt(string $email, string $password): bool
    {
        $user = User::first('email', strtolower(trim($email)));
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if ($user['status'] !== 'active') {
            return false;
        }
        self::login($user);
        return true;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        static $cached = null;
        if (!self::check()) {
            return null;
        }
        if ($cached === null) {
            $cached = User::find((int) $_SESSION['user_id']);
        }
        return $cached;
    }

    public static function isSuperAdmin(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'super_admin';
    }

    public static function isCompanyOwner(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'owner';
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function roleLabel(): string
    {
        return self::ROLE_LABELS[self::role()] ?? ucfirst((string) self::role());
    }

    public static function roleShortLabel(): string
    {
        return self::ROLE_SHORT_LABELS[self::role()] ?? ucfirst((string) self::role());
    }

    /**
     * Ability checks for company-panel users. 'owner'/'admin' can do everything except
     * billing is owner-only. 'estimator'/'accountant' get day-to-day write access to
     * operational data. 'viewer' is read-only everywhere.
     */
    public static function can(string $ability): bool
    {
        if (self::isSuperAdmin()) {
            return true;
        }
        $role = self::role();
        return match ($ability) {
            'manage_billing' => $role === 'owner',
            'manage_team', 'manage_company_settings', 'manage_business_setup' => in_array($role, ['owner', 'admin'], true),
            'write' => in_array($role, ['owner', 'admin', 'estimator', 'accountant'], true),
            default => false,
        };
    }

    public static function requireAbility(string $ability): void
    {
        if (!self::can($ability)) {
            $_SESSION['flash']['error'][] = 'Your role (' . self::roleLabel() . ') does not have permission to do that.';
            Controller::redirect('/app');
        }
    }

    public static function companyId(): ?int
    {
        $u = self::user();
        return $u['company_id'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Controller::redirect('/login');
        }
    }

    public static function requireSuperAdmin(): void
    {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            die('Forbidden: admin access only.');
        }
    }

    public static function requireCompanyUser(): void
    {
        self::requireLogin();
        if (self::isSuperAdmin()) {
            Controller::redirect('/admin');
        }
        self::blockIfTrialExpired();
        self::blockIfPastDue();
    }

    /**
     * A company whose trial has ended (status still 'trial' and trial_ends_at in the past)
     * is redirected to Billing on every page except Billing itself and logout — otherwise
     * an expired trial would grant unlimited free access forever, since nothing else in the
     * app checks subscription state.
     */
    private static function blockIfTrialExpired(): void
    {
        $companyId = self::companyId();
        if (!$companyId || self::onExemptPath()) {
            return;
        }
        $company = \App\Models\Company::find($companyId);
        if (!$company || $company['status'] !== 'trial' || empty($company['trial_ends_at'])) {
            return;
        }
        if (strtotime($company['trial_ends_at']) >= strtotime(date('Y-m-d'))) {
            return;
        }
        $_SESSION['flash']['error'][] = 'Your trial has ended. Choose a plan to continue using BuildXact Saudi.';
        Controller::redirect('/app/billing');
    }

    /**
     * A company whose automatic renewal has failed repeatedly (see cron/daily_tasks.php) is
     * marked 'past_due' and, like an expired trial, redirected to Billing until they update
     * their payment method or pay another way.
     */
    private static function blockIfPastDue(): void
    {
        $companyId = self::companyId();
        if (!$companyId || self::onExemptPath()) {
            return;
        }
        $company = \App\Models\Company::find($companyId);
        if (!$company || $company['status'] !== 'past_due') {
            return;
        }
        $_SESSION['flash']['error'][] = 'We couldn\'t renew your subscription. Please update your payment method to continue.';
        Controller::redirect('/app/billing');
    }

    private static function onExemptPath(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        return str_starts_with($path, '/app/billing') || $path === '/logout';
    }
}
