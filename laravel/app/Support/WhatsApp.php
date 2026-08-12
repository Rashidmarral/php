<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Two ways to reach a client over WhatsApp:
 *
 * 1. shareLink() — a wa.me deep link that opens WhatsApp with a pre-filled message. Needs no
 *    account, no API key, no setup; works the instant a phone number is on file.
 *
 * 2. sendMessage() — a real call to Meta's WhatsApp Business Cloud API for automated, no-click
 *    notifications. Only works once a company has a real Meta WhatsApp Business Platform account.
 */
class WhatsApp
{
    public static function isConfigured(): bool
    {
        return Setting::get('whatsapp_enabled') === '1'
            && trim((string) Setting::get('whatsapp_phone_number_id', '')) !== ''
            && trim((string) Setting::get('whatsapp_access_token', '')) !== '';
    }

    /** A wa.me link that opens WhatsApp (app or web) with the message pre-filled — no API/config needed. */
    public static function shareLink(string $phone, string $message): ?string
    {
        $normalized = self::normalizePhone($phone);
        if ($normalized === null) {
            return null;
        }
        return 'https://wa.me/' . $normalized . '?text=' . rawurlencode($message);
    }

    /** Sends a free-form text message via the WhatsApp Business Cloud API. Requires real Meta credentials. */
    public static function sendMessage(string $phone, string $message): array
    {
        $normalized = self::normalizePhone($phone);
        if ($normalized === null) {
            return ['ok' => false, 'error' => 'No valid phone number on file.'];
        }
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'WhatsApp Business API is not configured.'];
        }

        $phoneNumberId = Setting::get('whatsapp_phone_number_id');
        $token = Setting::get('whatsapp_access_token');

        $ch = curl_init("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bearer {$token}"],
            CURLOPT_POSTFIELDS => json_encode([
                'messaging_product' => 'whatsapp',
                'to' => $normalized,
                'type' => 'text',
                'text' => ['body' => $message],
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => "Connection to WhatsApp failed: {$curlError}"];
        }
        $data = json_decode((string) $response, true);
        return ['ok' => $httpCode >= 200 && $httpCode < 300, 'http_code' => $httpCode, 'data' => $data];
    }

    /** Strips everything but digits and ensures a Saudi country code prefix if a local 05... number was entered. */
    private static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '966' . substr($digits, 1);
        } elseif (!str_starts_with($digits, '966') && strlen($digits) <= 10) {
            $digits = '966' . $digits;
        }
        return strlen($digits) >= 10 ? $digits : null;
    }
}
