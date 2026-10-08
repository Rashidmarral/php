<?php

namespace App\Core\Zatca;

use chillerlan\QRCode\{QRCode, QROptions};

/**
 * ZATCA Phase 1 (Generation) compliance: the Base64 TLV-encoded QR payload
 * required on every simplified tax invoice, per ZATCA's e-invoicing
 * technical spec — 5 mandatory fields: seller name, VAT registration
 * number, invoice timestamp, invoice total (incl. VAT), and VAT total.
 *
 * @see https://zatca.gov.sa (E-Invoicing Detailed Guidelines)
 */
class Phase1Qr
{
    /** Builds the Base64 TLV payload (the string later encoded into the QR image). */
    public static function payload(string $sellerName, string $vatNumber, string $timestamp, string $total, string $vatTotal): string
    {
        $tlv = self::tlv(1, $sellerName)
            . self::tlv(2, $vatNumber)
            . self::tlv(3, $timestamp)
            . self::tlv(4, $total)
            . self::tlv(5, $vatTotal);

        return base64_encode($tlv);
    }

    private static function tlv(int $tag, string $value): string
    {
        $bytes = mb_convert_encoding($value, 'UTF-8');
        $length = strlen($bytes);
        // ZATCA's TLV length byte is a single octet (0-255); every field
        // used here (name, VAT#, ISO timestamp, formatted amounts) fits well
        // within that in practice.
        if ($length > 255) {
            $bytes = substr($bytes, 0, 255);
            $length = 255;
        }
        return chr($tag) . chr($length) . $bytes;
    }

    /** Renders the QR payload as an inline SVG data URI (works in both HTML and dompdf-rendered PDFs). */
    public static function renderSvgDataUri(string $base64Payload): string
    {
        $options = new QROptions([
            'scale' => 4,
            'quietzoneSize' => 1,
            'imageTransparent' => false,
        ]);
        return (new QRCode($options))->render($base64Payload);
    }
}
