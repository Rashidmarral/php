<?php

namespace App\Support;

use App\Models\Setting;

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
 *
 * Both scopes share the exact same Moyasar publishable/secret key pair for every source
 * type Moyasar supports — card, Apple Pay, and STC Pay alike. STC Pay here means a real,
 * distinct `source.type: "stcpay"` payment (a mobile-number + OTP wallet charge created
 * server-side via createStcPayPayment()/createStcPayPaymentForCompany()), not merely one
 * of the methods listed on the hosted card-checkout widget's `data-methods` attribute.
 */
class Moyasar
{
    // ---------------- Platform-level (subscription billing) ----------------

    public static function isConfigured(): bool
    {
        return Setting::get('moyasar_enabled') === '1'
            && trim((string) Setting::get('moyasar_publishable_key', '')) !== ''
            && trim((string) Setting::get('moyasar_secret_key', '')) !== '';
    }

    public static function publishableKey(): string
    {
        return (string) Setting::get('moyasar_publishable_key', '');
    }

    public static function secretKey(): string
    {
        return (string) Setting::get('moyasar_secret_key', '');
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

    /**
     * Creates a genuine STC Pay (mobile wallet) payment — a real `source.type: "stcpay"`
     * payment source (mobile number + OTP wallet charge), not a card token — using the
     * platform's own Moyasar credentials. See createStcPayPaymentForCompany() for the
     * per-company equivalent. Returns Moyasar's response (including whatever
     * redirect/transaction-url field it put on `source` for the customer to complete the
     * OTP/app confirmation), or null if the mobile number doesn't normalize to a Saudi
     * MSISDN or the request itself fails.
     */
    public static function createStcPayPayment(int $amountHalalas, string $description, string $mobile, string $callbackUrl): ?array
    {
        return self::createStcPayPaymentWithSecret(self::secretKey(), $amountHalalas, $description, $mobile, $callbackUrl);
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

    /** Same as createStcPayPayment(), but charged into the company's own Moyasar merchant account. */
    public static function createStcPayPaymentForCompany(array $company, int $amountHalalas, string $description, string $mobile, string $callbackUrl): ?array
    {
        return self::createStcPayPaymentWithSecret((string) ($company['moyasar_secret_key'] ?? ''), $amountHalalas, $description, $mobile, $callbackUrl);
    }

    /**
     * Shared by both STC Pay scopes above. Normalizes the mobile number first — refusing to
     * ever call out to Moyasar with something that isn't a real Saudi MSISDN — then builds
     * the request payload as a pure, separately-testable step (buildStcPayPayload()) before
     * posting it.
     */
    private static function createStcPayPaymentWithSecret(string $secret, int $amountHalalas, string $description, string $mobile, string $callbackUrl): ?array
    {
        $normalizedMobile = PhoneNumber::normalizeSaudi($mobile);
        if ($normalizedMobile === null) {
            return null;
        }

        return self::post(
            'https://api.moyasar.com/v1/payments',
            $secret,
            self::buildStcPayPayload($amountHalalas, $description, $normalizedMobile, $callbackUrl)
        );
    }

    /**
     * Builds the exact JSON body sent to POST https://api.moyasar.com/v1/payments for an
     * STC Pay source — kept as a pure function (no curl, no credentials) so the request
     * shape can be asserted on directly in tests without calling Moyasar's real API.
     * $normalizedMobile is expected to already be a normalizeSaudi() MSISDN (digits only,
     * e.g. "966501234567"); Moyasar's documented `source.mobile` format is E.164 with a
     * leading "+", so that's added here.
     */
    public static function buildStcPayPayload(int $amountHalalas, string $description, string $normalizedMobile, string $callbackUrl): array
    {
        return [
            'amount' => $amountHalalas,
            'currency' => 'SAR',
            'description' => $description,
            'callback_url' => $callbackUrl,
            'source' => [
                'type' => 'stcpay',
                'mobile' => '+' . $normalizedMobile,
                'cashier' => mb_substr($description, 0, 40),
            ],
        ];
    }

    private static function fetchPaymentWithSecret(string $paymentId, string $secret): ?array
    {
        if ($secret === '' || $paymentId === '') {
            return null;
        }

        $ch = curl_init('https://api.moyasar.com/v1/payments/' . rawurlencode($paymentId));
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
