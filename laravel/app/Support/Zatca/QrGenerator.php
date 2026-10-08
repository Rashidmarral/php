<?php

namespace App\Support\Zatca;

use chillerlan\QRCode\{QRCode, QROptions};

/**
 * Builds the Base64 TLV (tag-length-value) QR payload ZATCA requires on
 * every invoice, in both its forms:
 *
 *  - Phase 1 ("Generation"): 5 mandatory fields (seller name, VAT number,
 *    timestamp, invoice total, VAT total) — required on every invoice
 *    regardless of Phase 2 onboarding status. This supersedes the old
 *    App\Support\Zatca\Phase1Qr (identical tag semantics/encoding; folded
 *    in here so Phase 1 and Phase 2 payload-building live in one place).
 *  - Phase 2 ("Integration"): the same 5 fields plus 4 more — the
 *    invoice hash, the EGS's ECDSA signature over that hash, its public
 *    key, and ZATCA's own certificate signature — ported field-for-field
 *    (including the exact raw-vs-base64 convention per tag, which is easy
 *    to get subtly wrong and silently corrupt tag 8) from Daftri's
 *    App\Services\ZatcaQrGenerator::buildTlvPayloadPhase2().
 *
 * Rendering (QR image/SVG) uses BuildXact's existing chillerlan/php-qrcode
 * dependency rather than adding Daftri's endroid/qr-code — both do the
 * same job (encode a payload string into a scannable QR) and BuildXact
 * already ships the former, so this avoids a second QR library purely for
 * PNG vs SVG output; only the *payload* logic (the part ZATCA's validator
 * actually cares about) is what needed to match Daftri exactly.
 */
class QrGenerator
{
    /** Builds the Base64 TLV payload for the Phase 1 (5-tag) QR. */
    public static function payload(string $sellerName, string $vatNumber, string $timestamp, string $total, string $vatTotal): string
    {
        $tlv = self::tlv(1, $sellerName)
            . self::tlv(2, $vatNumber)
            . self::tlv(3, $timestamp)
            . self::tlv(4, $total)
            . self::tlv(5, $vatTotal);

        return base64_encode($tlv);
    }

    /**
     * The full 9-tag Phase 2 QR payload: the 5 basic tags plus the
     * invoice hash, an ECDSA signature over that hash, the signing public
     * key, and ZATCA's own cryptographic stamp for this document (QR tag
     * 9 — a property of the CSID certificate itself, not of any
     * individual invoice, so it never changes between invoices signed
     * under the same certificate). Returns null if any field would
     * overflow the single-byte TLV length ZATCA's format uses.
     */
    public static function buildTlvPayloadPhase2(
        string $sellerName,
        string $vatNumber,
        \DateTimeInterface $issuedAt,
        float $invoiceTotal,
        float $vatTotal,
        string $invoiceHashBase64,
        string $signatureBase64,
        string $publicKeyRaw,
        string $certificateSignatureRaw
    ): ?string {
        // Tags 6 and 7 (invoice hash, signature) are written as their
        // base64 *text*, but tag 8 (public key) is the raw DER
        // SubjectPublicKeyInfo bytes — not base64 text of them — same as
        // tag 9. Confirmed against a commercially available,
        // independently-verified-working ZATCA Phase 2 reference
        // implementation (Ultimate POS's ZATCA module): its
        // Cert509XParser::getCertificatePublicKeyEncoded() (despite the
        // name) returns base64_decode($publicKeyPem) — raw bytes — and
        // that's what it feeds straight into its own QR TLV builder,
        // exactly like its already-raw certificate-signature tag. Getting
        // this wrong (embedding the public key as base64 *text* instead
        // of raw bytes) silently corrupts the QR's own copy of the public
        // key while leaving ds:X509Certificate elsewhere in the signed
        // XML untouched — surfacing only as ZATCA's "ECDSA Public Key
        // does not match with qr code ECDSA public key", and only on
        // simplified/B2C documents (ZATCA does not cryptographically
        // re-validate the QR contents for standard/B2B invoices, only for
        // simplified ones).
        $tags = [
            1 => $sellerName,
            2 => $vatNumber,
            3 => $issuedAt->format('Y-m-d\TH:i:s\Z'),
            4 => number_format($invoiceTotal, 2, '.', ''),
            5 => number_format($vatTotal, 2, '.', ''),
            6 => $invoiceHashBase64,
            7 => $signatureBase64,
            8 => $publicKeyRaw,
            9 => $certificateSignatureRaw,
        ];

        $binary = '';
        foreach ($tags as $tag => $value) {
            $length = mb_strlen($value, '8bit');

            if ($length > 255) {
                return null;
            }

            $binary .= chr($tag) . chr($length) . $value;
        }

        return base64_encode($binary);
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
