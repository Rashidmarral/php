<?php

namespace App\Support;

use App\Models\Setting;

/**
 * SMS delivery via Unifonic's REST SMS API — one of the most widely used SMS gateways for
 * Saudi/GCC businesses. Endpoint shape (per Unifonic's documented REST API):
 *
 *   POST https://el.cloud.unifonic.com/rest/SMS/messages
 *   Authorization: Bearer {AppSid}
 *   Content-Type: application/x-www-form-urlencoded
 *   Body: AppSid={AppSid}&SenderID={SenderID}&Body={message}&Recipient={966...}&responseType=JSON
 *
 * There is no free "share link" fallback for SMS the way WhatsApp has wa.me — sending an SMS
 * always requires a real Unifonic account (an AppSid credential and an approved SenderID), so
 * this class only exposes the real-API-call path via sendMessage().
 *
 * If the app is actually contracted with a different SMS gateway, adapt the request built in
 * sendMessage() below — the request shape (form fields, auth header) is specific to Unifonic
 * and won't match another provider's API without changes.
 */
class Sms
{
    public static function isConfigured(): bool
    {
        return Setting::get('sms_enabled') === '1'
            && trim((string) Setting::get('sms_app_sid', '')) !== ''
            && trim((string) Setting::get('sms_sender_id', '')) !== '';
    }

    /** Sends a free-form text message via Unifonic's REST SMS API. Requires a real Unifonic account. */
    public static function sendMessage(string $phone, string $message): array
    {
        $normalized = PhoneNumber::normalizeSaudi($phone);
        if ($normalized === null) {
            return ['ok' => false, 'error' => 'No valid phone number on file.'];
        }
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'SMS is not configured.'];
        }

        $appSid = Setting::get('sms_app_sid');
        $senderId = Setting::get('sms_sender_id');

        $ch = curl_init('https://el.cloud.unifonic.com/rest/SMS/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                "Authorization: Bearer {$appSid}",
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'AppSid' => $appSid,
                'SenderID' => $senderId,
                'Body' => $message,
                'Recipient' => $normalized,
                'responseType' => 'JSON',
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => "Connection to SMS gateway failed: {$curlError}"];
        }
        $data = json_decode((string) $response, true);
        return ['ok' => $httpCode >= 200 && $httpCode < 300, 'http_code' => $httpCode, 'data' => $data];
    }
}
