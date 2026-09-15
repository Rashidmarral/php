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
}
