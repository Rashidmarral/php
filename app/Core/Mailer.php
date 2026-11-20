<?php

namespace App\Core;

/**
 * A minimal SMTP client (no external library) — speaks the SMTP protocol directly over a socket,
 * with STARTTLS and AUTH LOGIN support, which covers the common case of sending through a real
 * mailbox or transactional provider (Gmail, Outlook365, SendGrid, Mailgun, etc. all support this).
 * Requires real SMTP credentials configured by the admin; there is no way to send email without them.
 */
class Mailer
{
    public static function isConfigured(): bool
    {
        return Settings::get('smtp_enabled') === '1'
            && trim((string) Settings::get('smtp_host', '')) !== ''
            && trim((string) Settings::get('smtp_username', '')) !== '';
    }

    /** @return array{ok: bool, error?: string} */
    public static function send(string $toEmail, string $toName, string $subject, string $bodyText): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'SMTP is not configured.'];
        }

        $host = Settings::get('smtp_host');
        $port = (int) Settings::get('smtp_port', 587);
        $username = Settings::get('smtp_username');
        $password = Settings::get('smtp_password', '');
        $encryption = Settings::get('smtp_encryption', 'tls');
        $fromEmail = Settings::get('smtp_from_email', $username);
        $fromName = Settings::get('smtp_from_name', 'BuildXact Saudi');

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client("{$transport}{$host}:{$port}", $errno, $errstr, 15);
        if (!$socket) {
            return ['ok' => false, 'error' => "Could not connect to SMTP server: {$errstr}"];
        }

        try {
            self::expect($socket, 220);
            self::command($socket, "EHLO " . (parse_url(Env::get('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'), 250);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return ['ok' => false, 'error' => 'STARTTLS negotiation failed.'];
                }
                self::command($socket, "EHLO " . (parse_url(Env::get('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'), 250);
            }

            self::command($socket, 'AUTH LOGIN', 334);
            self::command($socket, base64_encode($username), 334);
            self::command($socket, base64_encode($password), 235);

            self::command($socket, "MAIL FROM:<{$fromEmail}>", 250);
            self::command($socket, "RCPT TO:<{$toEmail}>", 250);
            self::command($socket, 'DATA', 354);

            $headers = implode("\r\n", [
                "From: {$fromName} <{$fromEmail}>",
                "To: {$toName} <{$toEmail}>",
                "Subject: {$subject}",
                "MIME-Version: 1.0",
                "Content-Type: text/plain; charset=UTF-8",
                "Date: " . date('r'),
            ]);
            $escapedBody = preg_replace('/^\./m', '..', $bodyText);
            self::command($socket, $headers . "\r\n\r\n" . $escapedBody . "\r\n.", 250);
            self::command($socket, 'QUIT', 221);

            return ['ok' => true];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            fclose($socket);
        }
    }

    private static function command($socket, string $line, int $expectedCode): string
    {
        fwrite($socket, $line . "\r\n");
        return self::expect($socket, $expectedCode);
    }

    private static function expect($socket, int $expectedCode): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            // Multi-line SMTP replies use "code-" on all but the last line; a space marks the end.
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("SMTP error (expected {$expectedCode}, got {$code}): " . trim($response));
        }
        return $response;
    }
}
