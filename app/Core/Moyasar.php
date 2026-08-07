<?php

namespace App\Core;

/**
 * Minimal client for Moyasar's REST API (https://moyasar.com/docs/api/).
 * The card form itself is Moyasar's own hosted JS (see billing/checkout views) so raw
 * card data never touches this server — only the resulting payment ID is
 * verified here, server-side, against Moyasar's API using the secret key.
 *
 * Two credential scopes are supported:
 *  - Platform-level (Admin > Platform Settings > Payment Methods): used to charge
 *    companies for their BuildXact Saudi subscription. Money lands in the platform's
 *    own Moyasar account.
 *  - Per-company (Company > Integrations): used so a contractor's own clients can pay
 *    invoices directly into *that contractor's own* Moyasar merchant account — the
 *    platform never touches that money.
 */
class Moyasar
{
    // ---------------- Platform-level (subscription billing) ----------------

    public static function isConfigured(): bool
    {
        return Settings::get('moyasar_enabled') === '1'
            && trim((string) Settings::get('moyasar_publishable_key', '')) !== ''
            && trim((string) Settings::get('moyasar_secret_key', '')) !== '';
    }

    public static function publishableKey(): string
    {
        return (string) Settings::get('moyasar_publishable_key', '');
    }

    public static function secretKey(): string
    {
        return (string) Settings::get('moyasar_secret_key', '');
    }

    /**
     * Fetches a payment by ID from Moyasar and returns it, or null on any
     * failure. Never trust a client-supplied "paid" status without this
     * server-side re-verification.
     */
    public static function fetchPayment(string $paymentId): ?array
    {
        return self::fetchPaymentWithSecret($paymentId, self::secretKey());
    }

    /** Charges a previously-saved card token (recurring/renewal payments). Amount in halalas. */
    public static function chargeToken(string $token, int $amountHalalas, string $description): ?array
    {
        return self::post('https://api.moyasar.com/v1/payments', self::secretKey(), [
            'amount' => $amountHalalas,
            'currency' => 'SAR',
            'description' => $description,
            'source' => ['type' => 'token', 'token' => $token],
        ]);
    }

    // ---------------- Per-company (invoice payments) ----------------

    public static function isConfiguredForCompany(?array $company): bool
    {
        return $company
            && !empty($company['moyasar_enabled'])
            && trim((string) ($company['moyasar_publishable_key'] ?? '')) !== ''
            && trim((string) ($company['moyasar_secret_key'] ?? '')) !== '';
    }

    public static function fetchPaymentForCompany(string $paymentId, array $company): ?array
    {
        return self::fetchPaymentWithSecret($paymentId, (string) ($company['moyasar_secret_key'] ?? ''));
    }

    private static function fetchPaymentWithSecret(string $paymentId, string $secret): ?array
    {
        if ($secret === '' || $paymentId === '') {
            return null;
        }

        $ch = curl_init("https://api.moyasar.com/v1/payments/" . rawurlencode($paymentId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $secret . ':',
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    private static function post(string $url, string $secret, array $payload): ?array
    {
        if ($secret === '') {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_USERPWD => $secret . ':',
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = $response !== false ? json_decode($response, true) : null;
        if (!is_array($data)) {
            return null;
        }
        $data['_http_code'] = $httpCode;
        return $data;
    }
}
