<?php

namespace App\Support\Zatca;

/**
 * Client for ZATCA's e-invoicing gateway (https://gw-fatoora.zatca.gov.sa).
 * Endpoint paths and request/response shapes follow ZATCA's published
 * "E-Invoicing Integration Guidelines". This makes real HTTP calls — it
 * will only succeed against a company that has genuinely started ZATCA
 * onboarding (valid OTP from their own Fatoora portal account) or has a
 * real issued CSID. There is no way to fabricate a working response.
 */
class ApiClient
{
    private const BASE_URLS = [
        'sandbox' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
        'simulation' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
        'production' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
    ];

    public function __construct(private string $environment = 'sandbox')
    {
    }

    /** Exchanges a CSR + OTP (from the company's own Fatoora portal) for a compliance CSID. */
    public function issueComplianceCsid(string $csrBase64, string $otp): array
    {
        return $this->request('POST', '/compliance', ['csr' => $csrBase64], null, ['OTP' => $otp]);
    }

    /** Exchanges a compliance CSID for a production CSID (after passing compliance checks). */
    public function issueProductionCsid(string $complianceRequestId, string $complianceCsid, string $complianceSecret): array
    {
        return $this->request(
            'POST',
            '/production/csids',
            ['compliance_request_id' => $complianceRequestId],
            [$complianceCsid, $complianceSecret]
        );
    }

    /** Reports a standard/simplified invoice (Phase 2). */
    public function reportInvoice(string $invoiceBase64, string $uuid, string $hash, string $csid, string $secret): array
    {
        return $this->request(
            'POST',
            '/invoices/reporting/single',
            ['invoiceHash' => $hash, 'uuid' => $uuid, 'invoice' => $invoiceBase64],
            [$csid, $secret]
        );
    }

    private function request(string $method, string $path, array $body, ?array $basicAuth, array $extraHeaders = []): array
    {
        $baseUrl = self::BASE_URLS[$this->environment] ?? self::BASE_URLS['sandbox'];
        $headers = array_merge(['Content-Type: application/json', 'Accept: application/json', 'Accept-Version: V2'], self::formatHeaders($extraHeaders));

        $ch = curl_init($baseUrl . $path);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ];
        if ($basicAuth) {
            $options[CURLOPT_USERPWD] = $basicAuth[0] . ':' . $basicAuth[1];
        }
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => "Connection to ZATCA failed: {$curlError}"];
        }

        $data = json_decode((string) $response, true);
        return [
            'ok' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $data,
            'raw' => $response,
        ];
    }

    private static function formatHeaders(array $extra): array
    {
        $out = [];
        foreach ($extra as $key => $value) {
            $out[] = "{$key}: {$value}";
        }
        return $out;
    }
}
