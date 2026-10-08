<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Zatca\ApiClient;
use App\Core\Zatca\CsrGenerator;
use App\Models\Company;

class CompanyZatcaController extends Controller
{
    public function show(string $id): void
    {
        $company = $this->findCompany($id);
        $this->view('admin/companies/zatca', ['pageTitle' => $company['name'] . ' — ZATCA', 'company' => $company], 'layouts/admin');
    }

    public function updateEnvironment(string $id): void
    {
        $this->verifyCsrf();
        $company = $this->findCompany($id);
        $env = $this->input('zatca_environment') === 'production' ? 'production' : 'sandbox';
        Company::update($company['id'], ['zatca_environment' => $env]);
        $this->flash('success', 'ZATCA environment updated.');
        self::redirect('/admin/companies/' . $company['id'] . '/zatca');
    }

    public function generateCsr(string $id): void
    {
        $this->verifyCsrf();
        $company = $this->findCompany($id);

        if (empty($company['vat_number'])) {
            $this->flash('error', 'Set the company\'s VAT number (in their Settings) before generating a CSR.');
            self::redirect('/admin/companies/' . $company['id'] . '/zatca');
        }

        try {
            $commonName = preg_replace('/[^A-Za-z0-9]+/', '-', $company['name']) . '-EGS-1';
            $result = CsrGenerator::generate($company['vat_number'], $company['name'], $commonName, $company['city'] ?: 'Riyadh');

            Company::update($company['id'], [
                'zatca_csr' => $result['csr'],
                'zatca_private_key' => $result['private_key'],
                'zatca_status' => 'csr_generated',
                'zatca_last_error' => null,
            ]);
            $this->flash('success', 'CSR generated. Next, get an OTP from this company\'s Fatoora portal account and request the compliance CSID.');
        } catch (\Throwable $e) {
            Company::update($company['id'], ['zatca_last_error' => $e->getMessage()]);
            $this->flash('error', 'CSR generation failed: ' . $e->getMessage());
        }
        self::redirect('/admin/companies/' . $company['id'] . '/zatca');
    }

    public function requestComplianceCsid(string $id): void
    {
        $this->verifyCsrf();
        $company = $this->findCompany($id);

        if (empty($company['zatca_csr'])) {
            $this->flash('error', 'Generate a CSR first.');
            self::redirect('/admin/companies/' . $company['id'] . '/zatca');
        }
        $otp = trim((string) $this->input('otp'));
        if ($otp === '') {
            $this->flash('error', 'Enter the OTP from this company\'s Fatoora portal account.');
            self::redirect('/admin/companies/' . $company['id'] . '/zatca');
        }

        $client = new ApiClient($company['zatca_environment'] ?: 'sandbox');
        $result = $client->issueComplianceCsid(base64_encode($company['zatca_csr']), $otp);

        if (!empty($result['ok']) && !empty($result['data']['binarySecurityToken'])) {
            Company::update($company['id'], [
                'zatca_compliance_csid' => $result['data']['binarySecurityToken'],
                'zatca_compliance_secret' => $result['data']['secret'] ?? null,
                'zatca_status' => 'compliance_csid',
                'zatca_last_error' => null,
            ]);
            $this->flash('success', 'Compliance CSID issued by ZATCA.');
        } else {
            $error = $result['error'] ?? json_encode($result['data'] ?? $result);
            Company::update($company['id'], ['zatca_status' => 'error', 'zatca_last_error' => $error]);
            $this->flash('error', 'ZATCA rejected the request: ' . $error);
        }
        self::redirect('/admin/companies/' . $company['id'] . '/zatca');
    }

    public function requestProductionCsid(string $id): void
    {
        $this->verifyCsrf();
        $company = $this->findCompany($id);

        if (empty($company['zatca_compliance_csid'])) {
            $this->flash('error', 'Request a compliance CSID first.');
            self::redirect('/admin/companies/' . $company['id'] . '/zatca');
        }

        $client = new ApiClient($company['zatca_environment'] ?: 'sandbox');
        $result = $client->issueProductionCsid(
            (string) $this->input('compliance_request_id', ''),
            $company['zatca_compliance_csid'],
            $company['zatca_compliance_secret'] ?? ''
        );

        if (!empty($result['ok']) && !empty($result['data']['binarySecurityToken'])) {
            Company::update($company['id'], [
                'zatca_production_csid' => $result['data']['binarySecurityToken'],
                'zatca_production_secret' => $result['data']['secret'] ?? null,
                'zatca_status' => 'active',
                'zatca_last_error' => null,
            ]);
            $this->flash('success', 'Production CSID issued. This company can now submit invoices to ZATCA.');
        } else {
            $error = $result['error'] ?? json_encode($result['data'] ?? $result);
            Company::update($company['id'], ['zatca_last_error' => $error]);
            $this->flash('error', 'ZATCA rejected the request: ' . $error);
        }
        self::redirect('/admin/companies/' . $company['id'] . '/zatca');
    }

    private function findCompany(string $id): array
    {
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }
        return $company;
    }
}
