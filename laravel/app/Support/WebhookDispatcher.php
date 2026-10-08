<?php

namespace App\Support;

use App\Models\Webhook;

/**
 * Fires outbound webhooks for integration events (estimate.created, estimate.signed,
 * invoice.created, invoice.paid). Each request carries an HMAC-SHA256 signature
 * (X-Webhook-Signature) over the raw JSON body, keyed by the webhook's own secret, so
 * the receiver can verify the payload wasn't tampered with in transit.
 *
 * Dispatched synchronously with a short timeout — same fire-and-forget pattern as
 * Mailer/WhatsApp elsewhere in this app. A slow/unreachable endpoint only costs a few
 * seconds on the triggering request, never breaks it (failures are swallowed).
 */
class WebhookDispatcher
{
    public static function dispatch(int $companyId, string $event, array $payload): void
    {
        $webhooks = Webhook::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $w) => in_array($event, $w->eventsList(), true));

        foreach ($webhooks as $webhook) {
            self::send($webhook, $event, $payload);
        }
    }

    private static function send(Webhook $webhook, string $event, array $payload): void
    {
        $body = json_encode([
            'event' => $event,
            'sent_at' => now()->toAtomString(),
            'data' => $payload,
        ]);
        $signature = hash_hmac('sha256', $body, $webhook->secret);

        $ch = curl_init($webhook->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Event: ' . $event,
                'X-Webhook-Signature: ' . $signature,
            ],
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $webhook->update([
            'last_triggered_at' => now(),
            'last_status' => $error !== '' ? 'error' : ($httpCode >= 200 && $httpCode < 300 ? 'ok' : "http_{$httpCode}"),
        ]);
    }
}
