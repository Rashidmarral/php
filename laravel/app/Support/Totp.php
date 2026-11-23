<?php

namespace App\Support;

/**
 * RFC 6238 TOTP (Time-based One-Time Password), built from scratch on PHP's
 * built-in hash_hmac('sha1', ...) rather than pulling in a third-party TOTP
 * package — the algorithm is small and this keeps the crypto surface of a
 * security-sensitive feature auditable in one file:
 *
 *  - generateSecret(): a random 20-byte (160-bit) shared secret, the size
 *    every mainstream authenticator app (Google Authenticator, Microsoft
 *    Authenticator, Authy) expects.
 *  - base32Encode()/base32Decode(): RFC 4648 base32 — used both to display
 *    the secret for manual entry and to embed it in the otpauth:// URI,
 *    since that's the wire format the provisioning-URI spec requires.
 *  - provisioningUri(): the standard otpauth://totp/... URI that
 *    authenticator apps read out of a QR code.
 *  - currentCode()/verify(): HOTP (RFC 4226 §5.3 dynamic truncation) over
 *    the 30-second time-step counter, with verify() tolerating ±1 step
 *    (±30s) of clock drift between the server and the user's device.
 *  - generateRecoveryCodes(): one-time backup codes for account recovery
 *    if the authenticator device is lost. These are returned in plaintext
 *    exactly once by the caller (during setup) — the caller is responsible
 *    for hashing them (Hash::make(), same as passwords) before persisting,
 *    this class never stores anything itself.
 */
class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A fresh random shared secret, base32-encoded for storage/display. */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * The otpauth:// provisioning URI an authenticator app reads from a QR
     * code — see https://github.com/google/google-authenticator/wiki/Key-Uri-Format.
     * $accountName is typically the user's email; $issuer is shown above it
     * in the app (e.g. the site name) and also namespaces the label so the
     * same email under different issuers doesn't collide in the app's list.
     */
    public static function provisioningUri(string $secretBase32, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
        $query = http_build_query([
            'secret' => $secretBase32,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    /** The 6-digit code valid for the given moment (defaults to now). */
    public static function currentCode(string $secretBase32, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);
        return self::hotp(self::base32Decode($secretBase32), $counter);
    }

    /**
     * Verifies a submitted 6-digit code against the secret, accepting the
     * current time step plus/minus $window steps (default ±1, i.e. ±30s)
     * to tolerate clock drift between the server and the user's device.
     * Uses hash_equals() for a timing-safe comparison.
     */
    public static function verify(string $secretBase32, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', (string) $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $key = self::base32Decode($secretBase32);
        $counter = intdiv(time(), self::PERIOD);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($key, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * $count fresh one-time recovery codes, formatted as two 5-character
     * hex groups (e.g. "A1B2C3-D4E5F6") for readability. Returned in
     * plaintext — the caller must hash each one (Hash::make()) before
     * storing, exactly like a password, and show them to the user only
     * once (they can't be recovered/displayed again afterwards).
     */
    public static function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(5)));
            $codes[] = substr($hex, 0, 5) . '-' . substr($hex, 5, 5);
        }
        return $codes;
    }

    /** HOTP per RFC 4226 §5.3 (dynamic truncation) over the raw (decoded) key and an integer counter. */
    private static function hotp(string $rawKey, int $counter): string
    {
        // 8-byte big-endian counter, independent of host byte order ('J' = uint64, big-endian).
        $counterBytes = pack('J', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $rawKey, true);

        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        $otp = $binary % (10 ** self::DIGITS);
        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** RFC 4648 base32 encode (upper-case alphabet, '=' padding to a multiple of 8 chars). */
    public static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $bits = '';
        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        $padLength = (8 - (strlen($encoded) % 8)) % 8;
        return $encoded . str_repeat('=', $padLength);
    }

    /** RFC 4648 base32 decode — case-insensitive, tolerates missing padding and stray whitespace. */
    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[\s=]+/', '', $b32));
        if ($b32 === '') {
            return '';
        }

        $bits = '';
        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $pos = strpos(self::BASE32_ALPHABET, $b32[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }
}
