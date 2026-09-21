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
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.b2b_b2c_required'));
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

        return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'success', t('admin.zatca.settings_updated'));
    }

    public function generateCsr(int $id, ZatcaCryptoService $crypto): RedirectResponse
    {
        $company = Company::findOrFail($id);

        if (empty($company->vat_number)) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.vat_number_required'));
        }

        try {
            $result = $crypto->generateCsr($company);

            $company->update([
                'zatca_csr' => $result['csr'],
                'zatca_private_key' => $result['private_key'],
                'zatca_status' => 'csr_generated',
                'zatca_last_error' => null,
            ]);
            $this->flash('success', t('admin.zatca.csr_generated'));
        } catch (Throwable $e) {
            $company->update(['zatca_last_error' => $e->getMessage()]);
            $this->flash('error', t('admin.zatca.csr_generation_failed', ['reason' => $e->getMessage()]));
        }
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }

    public function requestComplianceCsid(Request $request, int $id, ZatcaApiClient $api): RedirectResponse
    {
        $company = Company::findOrFail($id);

        if (empty($company->zatca_csr)) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.csr_required'));
        }
        $otp = trim((string) $request->input('otp'));
        if ($otp === '') {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.otp_required'));
        }

        try {
            $response = $api->issueComplianceCsid($company->zatca_environment, $company->zatca_csr, $otp);
        } catch (Throwable $e) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.connection_failed', ['reason' => $e->getMessage()]));
        }

        if (!$response->successful()) {
            $error = 'HTTP ' . $response->status() . ': ' . $response->body();
            $company->update(['zatca_status' => 'error', 'zatca_last_error' => $error]);
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.otp_rejected', ['reason' => $error]));
        }

        $body = $response->json();
        $company->update([
            'zatca_compliance_request_id' => $body['requestID'] ?? $body['requestId'] ?? null,
            'zatca_compliance_csid' => $body['binarySecurityToken'] ?? null,
            'zatca_compliance_secret' => $body['secret'] ?? null,
            'zatca_status' => 'compliance_pending',
            'zatca_last_error' => null,
        ]);
        $this->flash('success', t('admin.zatca.compliance_csid_issued'));
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
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.compliance_csid_required'));
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
                return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.connection_failed', ['reason' => $e->getMessage()]));
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
        $this->flash('success', t('admin.zatca.compliance_checks_passed'));
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }

    public function requestProductionCsid(int $id, ZatcaApiClient $api): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $eligible = $company->zatca_status === 'compliance_verified'
            || ($company->zatca_status === 'onboarded' && !$company->zatca_production_csid);

        if (!$eligible) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.compliance_checks_required'));
        }

        try {
            $response = $api->issueProductionCsid(
                $company->zatca_environment, $company->zatca_compliance_csid, $company->zatca_compliance_secret,
                (string) $company->zatca_compliance_request_id
            );
        } catch (Throwable $e) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.connection_failed', ['reason' => $e->getMessage()]));
        }

        if (!$response->successful()) {
            $error = 'HTTP ' . $response->status() . ': ' . $response->body();
            $company->update(['zatca_status' => 'failed', 'zatca_last_error' => $error]);
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', t('admin.zatca.production_csid_failed', ['reason' => $error]));
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
        $this->flash('success', t('admin.zatca.production_csid_issued'));
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
                ? t('admin.zatca.test_connection_success', ['env' => $company->zatca_environment, 'status' => $result['http_status'], 'latency' => $result['latency_ms']])
                : t('admin.zatca.test_connection_failed', ['env' => $company->zatca_environment, 'reason' => $result['error']])
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
        $this->flash('success', t('admin.zatca.onboarding_reset'));
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }
}
