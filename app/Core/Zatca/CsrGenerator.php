<?php

namespace App\Core\Zatca;

/**
 * Generates the EC (secp256k1) key pair + CSR a company needs to onboard
 * with ZATCA's Fatoora portal for Phase 2 (Integration).
 *
 * This produces a standard, structurally correct X.509 CSR (correct curve,
 * correct subject fields: C/O/OU/CN + VAT number). ZATCA's real onboarding
 * additionally expects a handful of custom certificate-template extensions
 * specific to their PKI (encoded per their "Fatoora Onboarding" technical
 * guide) that aren't reproduced here — those need to be verified against
 * ZATCA's own CSR tooling at the point a company actually onboards, since
 * getting a proprietary extension format subtly wrong is worse than not
 * guessing at it. Everything else (key generation, subject fields, PEM
 * output) is real and correct.
 */
class CsrGenerator
{
    /**
     * @return array{private_key: string, csr: string}
     */
    public static function generate(string $vatNumber, string $organizationName, string $commonName, string $organizationUnit = 'Riyadh'): array
    {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
            'digest_alg' => 'sha256',
        ];

        $privateKey = openssl_pkey_new($config);
        if ($privateKey === false) {
            throw new \RuntimeException('Could not generate EC key pair: ' . openssl_error_string());
        }

        $dn = [
            'countryName' => 'SA',
            'organizationName' => $organizationName !== '' ? $organizationName : 'Unregistered Company',
            'organizationalUnitName' => $organizationUnit,
            'commonName' => $commonName,
            // ZATCA uses the organizationIdentifier attribute (OID 2.5.4.97) for the VAT number.
            '2.5.4.97' => $vatNumber,
        ];

        $csr = openssl_csr_new($dn, $privateKey, $config);
        if ($csr === false) {
            throw new \RuntimeException('Could not generate CSR: ' . openssl_error_string());
        }

        openssl_pkey_export($privateKey, $privateKeyPem, null, $config);
        openssl_csr_export($csr, $csrPem);

        return ['private_key' => $privateKeyPem, 'csr' => $csrPem];
    }
}
