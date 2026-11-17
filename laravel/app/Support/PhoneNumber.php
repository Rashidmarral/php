<?php

namespace App\Support;

/** Saudi phone number normalization shared by every outbound channel (WhatsApp, SMS) that needs a 966... MSISDN. */
class PhoneNumber
{
    /** Strips everything but digits and ensures a Saudi country code prefix if a local 05... number was entered. */
    public static function normalizeSaudi(string $phone): ?string
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

    /**
     * Saudi mobile MSISDN shape after normalizeSaudi(): country code 966 + mobile
     * prefix 5 + 8 more digits (12 digits total) — the same "+966 5x xxx xxxx"
     * format every phone field's placeholder already shows across this app
     * (client/supplier/lead/registration/contact forms), now actually enforced.
     */
    public const SAUDI_MOBILE_PATTERN = '/^9665\d{8}$/';

    /**
     * Whether $phone is blank (callers decide separately whether the field is
     * required) or a valid Saudi mobile number in any of the raw forms
     * normalizeSaudi() already accepts — 05XXXXXXXX, 5XXXXXXXX, +9665XXXXXXXX,
     * 9665XXXXXXXX, 009665XXXXXXXX — with or without spaces/dashes.
     */
    public static function isValidSaudiMobile(string $phone): bool
    {
        $phone = trim($phone);
        if ($phone === '') {
            return true;
        }
        $normalized = self::normalizeSaudi($phone);

        return $normalized !== null && preg_match(self::SAUDI_MOBILE_PATTERN, $normalized) === 1;
    }
}
