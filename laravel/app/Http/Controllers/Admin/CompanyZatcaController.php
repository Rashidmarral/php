<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Zatca\ApiClient;
use App\Support\Zatca\CsrGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyZatcaController extends Controller
{
    public function show(int $id): View
    {
        return view('admin.companies.zatca', ['company' => Company::findOrFail($id)]);
    }

    public function updateEnvironment(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $env = $request->input('zatca_environment') === 'production' ? 'production' : 'sandbox';
        $company->update(['zatca_environment' => $env]);
        return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'success', 'ZATCA environment updated.');
    }

    public function generateCsr(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        if (empty($company->vat_number)) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', "Set the company's VAT number (in their Settings) before generating a CSR.");
        }

        try {
            $commonName = preg_replace('/[^A-Za-z0-9]+/', '-', $company->name) . '-EGS-1';
            $result = CsrGenerator::generate($company->vat_number, $company->name, $commonName, $company->city ?: 'Riyadh');

            $company->update([
                'zatca_csr' => $result['csr'],
                'zatca_private_key' => $result['private_key'],
                'zatca_status' => 'csr_generated',
                'zatca_last_error' => null,
            ]);
            $this->flash('success', 'CSR generated. Next, get an OTP from this company\'s Fatoora portal account and request the compliance CSID.');
        } catch (\Throwable $e) {
            $company->update(['zatca_last_error' => $e->getMessage()]);
            $this->flash('error', 'CSR generation failed: ' . $e->getMessage());
        }
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }

    public function requestComplianceCsid(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        if (empty($company->zatca_csr)) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Generate a CSR first.');
        }
        $otp = trim((string) $request->input('otp'));
        if ($otp === '') {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', "Enter the OTP from this company's Fatoora portal account.");
        }

        $client = new ApiClient($company->zatca_environment ?: 'sandbox');
        $result = $client->issueComplianceCsid(base64_encode($company->zatca_csr), $otp);

        if (!empty($result['ok']) && !empty($result['data']['binarySecurityToken'])) {
            $company->update([
                'zatca_compliance_csid' => $result['data']['binarySecurityToken'],
                'zatca_compliance_secret' => $result['data']['secret'] ?? null,
                'zatca_status' => 'compliance_csid',
                'zatca_last_error' => null,
            ]);
            $this->flash('success', 'Compliance CSID issued by ZATCA.');
        } else {
            $error = $result['error'] ?? json_encode($result['data'] ?? $result);
            $company->update(['zatca_status' => 'error', 'zatca_last_error' => $error]);
            $this->flash('error', 'ZATCA rejected the request: ' . $error);
        }
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }

    public function requestProductionCsid(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        if (empty($company->zatca_compliance_csid)) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id . '/zatca', 'error', 'Request a compliance CSID first.');
        }

        $client = new ApiClient($company->zatca_environment ?: 'sandbox');
        $result = $client->issueProductionCsid(
            (string) $request->input('compliance_request_id', ''),
            $company->zatca_compliance_csid,
            $company->zatca_compliance_secret ?? ''
        );

        if (!empty($result['ok']) && !empty($result['data']['binarySecurityToken'])) {
            $company->update([
                'zatca_production_csid' => $result['data']['binarySecurityToken'],
                'zatca_production_secret' => $result['data']['secret'] ?? null,
                'zatca_status' => 'active',
                'zatca_last_error' => null,
            ]);
            $this->flash('success', 'Production CSID issued. This company can now submit invoices to ZATCA.');
        } else {
            $error = $result['error'] ?? json_encode($result['data'] ?? $result);
            $company->update(['zatca_last_error' => $error]);
            $this->flash('error', 'ZATCA rejected the request: ' . $error);
        }
        return redirect('/admin/companies/' . $company->id . '/zatca');
    }
}
