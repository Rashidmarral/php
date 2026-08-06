<?php

namespace App\Core;

/**
 * Minimal client for Moyasar's REST API (https://moyasar.com/docs/api/).
 * The card form itself is Moyasar's own hosted JS (see billing view) so raw
 * card data never touches this server — only the resulting payment ID is
 * verified here, server-side, against Moyasar's API using the secret key.
 */
class Moyasar
{
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

    /**
     * Fetches a payment by ID from Moyasar and returns it, or null on any
     * failure. Never trust a client-supplied "paid" status without this
     * server-side re-verification.
     */
    public static function fetchPayment(string $paymentId): ?array
    {
        $secret = (string) Settings::get('moyasar_secret_key', '');
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
}
