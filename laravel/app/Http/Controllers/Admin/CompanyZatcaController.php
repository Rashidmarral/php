<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CSR generation and compliance/production CSID issuance need the real ZATCA crypto/API
 * client (App\Core\Zatca\ApiClient / CsrGenerator in the original app) — ported alongside
 * the rest of the ZATCA Phase 2 business logic in a later phase. This controller covers the
 * read-only status view and the plain environment toggle, which have no such dependency.
 */
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
        return $this->redirectWithFlash('/admin/companies/' . $id . '/zatca', 'error', 'CSR generation lands with the rest of the ZATCA Phase 2 integration in a later conversion phase.');
    }

    public function requestComplianceCsid(int $id): RedirectResponse
    {
        return $this->redirectWithFlash('/admin/companies/' . $id . '/zatca', 'error', 'Compliance CSID issuance lands with the rest of the ZATCA Phase 2 integration in a later conversion phase.');
    }

    public function requestProductionCsid(int $id): RedirectResponse
    {
        return $this->redirectWithFlash('/admin/companies/' . $id . '/zatca', 'error', 'Production CSID issuance lands with the rest of the ZATCA Phase 2 integration in a later conversion phase.');
    }
}
