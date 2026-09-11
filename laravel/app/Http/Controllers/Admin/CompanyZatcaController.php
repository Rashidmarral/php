<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Zatca\ZatcaApiClient;
use App\Support\Zatca\ZatcaCryptoService;
use App\Support\Zatca\ZatcaSyncService;
use App\Support\Zatca\ZatcaXadesSigner;
use App\Support\Zatca\ZatcaXmlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Admin-side ZATCA Phase 2 onboarding wizard for a company: generate a CSR
 * (an EC key pair + ZATCA's custom-extension CSR), exchange it plus an OTP
 * (from that company's own Fatoora portal account) for a compliance CSID,
 * run ZATCA's required compliance-check sample submissions, then exchange
 * the compliance CSID for the long-lived production CSID that live
 * clearance/reporting submissions authenticate with.
 *
 * The step order and API calls here are ported from Daftri's
 * App\Http\Controllers\User\ZatcaController (that app runs onboarding from
 * the company owner's own account; BuildXact runs it from the platform
 * admin side instead, matching this controller's original scope).
 */
class CompanyZatcaController extends Controller
{
    public function show(int $id): View
    {
        return view('admin.companies.zatca', ['company' => Company::findOrFail($id)]);
    }

    /** Environment + CSR-subject-field + B2B/B2C sync settings, all in one form. */
    public function updateSettings(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $environment = in_array($request->input('zatca_environment'), ['developer', 'simulation', 'production'], true)
            ? $request->input('zatca_environment') : 'developer';

        $syncB2b = $request->boolean('zatca_sync_b2b');
        $syncB2c = $request->boolean('zatca_sync_b2c');
        if (!$syncB2b && !$syncB2c) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Enable at least one of Standard (B2B) or Simplified (B2C) invoicing.');
        }

        $company->update([
            'zatca_environment' => $environment,
            'zatca_sync_b2b' => $syncB2b,
            'zatca_sync_b2c' => $syncB2c,
            'zatca_egs_serial' => trim((string) $request->input('zatca_egs_serial')) ?: null,
            'zatca_common_name' => trim((string) $request->input('zatca_common_name')) ?: null,
            'zatca_organization_unit_name' => trim((string) $request->input('zatca_organization_unit_name')) ?: null,
            'zatca_business_category' => trim((string) $request->input('zatca_business_category')) ?: null,
        ]);

        return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'success', 'ZATCA settings updated.');
    }

    public function generateCsr(int $id, ZatcaCryptoService $crypto): RedirectResponse
    {
        $company = Company::findOrFail($id);

        if (empty($company->vat_number)) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', "Set the company's VAT number (in their Settings) before generating a CSR.");
        }

        try {
            $result = $crypto->generateCsr($company);

            $company->update([
                'zatca_csr' => $result['csr'],
                'zatca_private_key' => $result['private_key'],
                'zatca_status' => 'csr_generated',
                'zatca_last_error' => null,
            ]);
            $this->flash('success', 'CSR generated. Copy the CSR into the ZATCA Fatoora portal to request an OTP, then continue below.');
        } catch (Throwable $e) {
            $company->update(['zatca_last_error' => $e->getMessage()]);
            $this->flash('error', 'CSR generation failed: ' . $e->getMessage() . ' — this is almost always a server-side OpenSSL issue (missing openssl.cnf, or an OpenSSL build without the secp256k1 curve ZATCA requires), not the company\'s data.');
        }
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }

    public function requestComplianceCsid(Request $request, int $id, ZatcaApiClient $api): RedirectResponse
    {
        $company = Company::findOrFail($id);

        if (empty($company->zatca_csr)) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Generate a CSR first.');
        }
        $otp = trim((string) $request->input('otp'));
        if ($otp === '') {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', "Enter the OTP from this company's Fatoora portal account.");
        }

        try {
            $response = $api->issueComplianceCsid($company->zatca_environment, $company->zatca_csr, $otp);
        } catch (Throwable $e) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Could not reach ZATCA: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            $error = 'HTTP ' . $response->status() . ': ' . $response->body();
            $company->update(['zatca_status' => 'error', 'zatca_last_error' => $error]);
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'ZATCA rejected the OTP/CSR: ' . $error);
        }

        $body = $response->json();
        $company->update([
            'zatca_compliance_request_id' => $body['requestID'] ?? $body['requestId'] ?? null,
            'zatca_compliance_csid' => $body['binarySecurityToken'] ?? null,
            'zatca_compliance_secret' => $body['secret'] ?? null,
            'zatca_status' => 'compliance_pending',
            'zatca_last_error' => null,
        ]);
        $this->flash('success', 'Compliance CSID issued by ZATCA. Run the compliance checks next.');
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }

    /**
     * Submits the compliance-check sample invoices ZATCA requires before
     * it will issue a production CSID: one per (Standard/Simplified)
     * profile the company's zatca_sync_b2b/zatca_sync_b2c settings
     * declared support for in the CSR. BuildXact has no credit/debit note
     * models, so — unlike Daftri's 6-combination run — this only covers
     * the tax-invoice (388) document type.
     */
    public function runComplianceCheck(int $id, ZatcaSyncService $sync, ZatcaCryptoService $crypto, ZatcaXmlGenerator $xml, ZatcaApiClient $api, ZatcaXadesSigner $signer): RedirectResponse
    {
        $company = Company::findOrFail($id);

        if (empty($company->zatca_compliance_csid) || empty($company->zatca_compliance_secret)) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Issue a compliance CSID first.');
        }

        $profiles = array_values(array_filter([
            $company->zatca_sync_b2b ? ZatcaSyncService::STANDARD_PROFILE : null,
            $company->zatca_sync_b2c ? ZatcaSyncService::SIMPLIFIED_PROFILE : null,
        ]));
        if (!$profiles) {
            $profiles = [ZatcaSyncService::SIMPLIFIED_PROFILE];
        }

        $previousHash = $crypto->genesisHash();
        $failures = [];

        foreach ($profiles as $icv => $profile) {
            $uuid = $xml->newUuid();
            // One shared instant for both the XML's IssueDate/IssueTime and
            // the QR code's own timestamp (KSA-25) — computed once, in
            // UTC explicitly, and reused for both — see
            // ZatcaXmlGenerator::generateComplianceSample()'s docblock.
            $issuedAt = now('UTC');
            $unsignedXml = $xml->generateComplianceSample($company, $profile, $previousHash, $uuid, $icv + 1, $issuedAt);
            $hash = $signer->contentHash($unsignedXml);

            [$xmlString] = $sync->buildSignedPayload($company, $unsignedXml, $hash, $company->zatca_compliance_csid, $issuedAt, 4.60, 0.60);

            try {
                $response = $api->checkComplianceInvoice(
                    $company->zatca_environment, $company->zatca_compliance_csid, $company->zatca_compliance_secret,
                    base64_encode($xmlString), $hash, $uuid
                );
            } catch (Throwable $e) {
                return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Could not reach ZATCA: ' . $e->getMessage());
            }

            // A prior successful run already satisfied this exact
            // profile — ZATCA remembers that and refuses to accept it
            // again, but that's not a failure, it's already done.
            $alreadyCompliant = !$response->successful() && Str::contains($response->body(), 'Submitted before');

            if (!$response->successful() && !$alreadyCompliant) {
                $failures[] = ($profile === ZatcaSyncService::STANDARD_PROFILE ? 'Standard invoice' : 'Simplified invoice') . ': HTTP ' . $response->status() . ' — ' . $response->body();
            }

            $previousHash = $hash;
        }

        if ($failures) {
            $error = 'Compliance check failed: ' . implode(' | ', $failures);
            $company->update(['zatca_status' => 'error', 'zatca_last_error' => $error]);
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', $error);
        }

        $company->update(['zatca_status' => 'compliance_verified', 'zatca_last_error' => null]);
        $this->flash('success', 'Compliance checks passed. You can now request the production CSID.');
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }

    public function requestProductionCsid(int $id, ZatcaApiClient $api): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $eligible = $company->zatca_status === 'compliance_verified'
            || ($company->zatca_status === 'onboarded' && !$company->zatca_production_csid);

        if (!$eligible) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Complete the compliance checks first.');
        }

        try {
            $response = $api->issueProductionCsid(
                $company->zatca_environment, $company->zatca_compliance_csid, $company->zatca_compliance_secret,
                (string) $company->zatca_compliance_request_id
            );
        } catch (Throwable $e) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Could not reach ZATCA: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            $error = 'HTTP ' . $response->status() . ': ' . $response->body();
            $company->update(['zatca_status' => 'failed', 'zatca_last_error' => $error]);
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Could not issue the production CSID: ' . $error);
        }

        $body = $response->json();
        $company->update([
            'zatca_production_request_id' => $body['requestID'] ?? $body['requestId'] ?? null,
            'zatca_production_csid' => $body['binarySecurityToken'] ?? null,
            'zatca_production_secret' => $body['secret'] ?? null,
            'zatca_status' => 'onboarded',
            'zatca_linked_at' => now(),
            'zatca_last_error' => null,
        ]);
        $this->flash('success', 'Production CSID issued. This company can now submit invoices to ZATCA.');
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }

    public function testConnection(int $id, ZatcaApiClient $api): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $result = $api->testConnection($company->zatca_environment);

        return $this->redirectWithFlash(
            '/admin/companies/' . $company->id . '/zatca',
            $result['reachable'] ? 'success' : 'error',
            $result['reachable']
                ? "ZATCA {$company->zatca_environment} gateway is reachable (HTTP {$result['http_status']}, {$result['latency_ms']} ms)."
                : "Could not reach the ZATCA {$company->zatca_environment} gateway: {$result['error']}"
        );
    }

    public function resetOnboarding(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $company->update([
            'zatca_status' => 'not_started',
            'zatca_csr' => null,
            'zatca_private_key' => null,
            'zatca_compliance_request_id' => null,
            'zatca_compliance_csid' => null,
            'zatca_compliance_secret' => null,
            'zatca_production_request_id' => null,
            'zatca_production_csid' => null,
            'zatca_production_secret' => null,
            'zatca_linked_at' => null,
            'zatca_last_error' => null,
        ]);
        $this->flash('success', 'ZATCA onboarding reset. The company can restart from CSR generation.');
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }
}
