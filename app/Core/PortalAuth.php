<?php

namespace App\Core;

use App\Models\Client;
use App\Models\Company;

class PortalAuth
{
    public static function attempt(string $email, string $password): bool
    {
        $client = Client::first('email', strtolower(trim($email)));
        if (!$client || empty($client['portal_enabled']) || empty($client['password_hash'])) {
            return false;
        }
        if (!password_verify($password, $client['password_hash'])) {
            return false;
        }
        $company = Company::find((int) $client['company_id']);
        if (!$company || empty($company['client_portal_enabled'])) {
            return false;
        }
        self::login($client);
        return true;
    }

    public static function login(array $client): void
    {
        session_regenerate_id(true);
        $_SESSION['portal_client_id'] = $client['id'];
    }

    public static function logout(): void
    {
        unset($_SESSION['portal_client_id']);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION['portal_client_id']);
    }

    public static function client(): ?array
    {
        static $cached = null;
        if (!self::check()) {
            return null;
        }
        if ($cached === null) {
            $cached = Client::find((int) $_SESSION['portal_client_id']);
        }
        return $cached;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Controller::redirect('/portal/login');
        }
    }
}
