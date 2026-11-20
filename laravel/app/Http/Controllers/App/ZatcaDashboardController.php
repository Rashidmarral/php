<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Company-facing read view over this company's own ZATCA submission
 * history — the zatca_invoice_logs / zatca_credit_note_logs /
 * zatca_debit_note_logs rows App\Support\Zatca\ZatcaSyncService writes on
 * every submission attempt, which until now only existed on the invoice/
 * credit-note/debit-note rows themselves (zatca_status/zatca_response) and
 * had no dedicated screen at all — a company had no way to see its own
 * submission history, onboarding status, or a past rejection's raw ZATCA
 * response.
 *
 * Strictly scoped to Auth::user()->company_id throughout — every query
 * below filters on it explicitly, since none of the three log models carry
 * a BelongsToCompany global scope (unlike Daftari's own equivalents), the
 * same reason showLog() below must abort_if-check company ownership by
 * hand rather than relying on route-model-binding's scope to 404 it.
 *
 * Gated the same way App\Http\Controllers\App\ActivityLogController gates
 * its own read-only, internal-controls-flavored company data: an ability
 * check (here, 'manage_company_settings' — the same ability the ZATCA
 * onboarding/sync settings themselves are managed under) plus a plan
 * feature check ('zatca_phase2', the same feature
 * CreditNoteController::submitZatca()/InvoiceController::submitZatca()
 * already gate the submission actions behind).
 */
class ZatcaDashboardController extends Controller
{
    public function dashboard(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('zatca_phase2')) {
            return $redirect;
        }

        $company = Company::find(Auth::user()->company_id);
        $perPage = $this->perPage($request, 15);

        $invoiceLogs = ZatcaInvoiceLog::where('company_id', $company->id)
            ->with('invoice:id,invoice_number')
            ->latest('id')
            ->paginate($perPage, ['*'], 'invoice_page')
            ->withQueryString();

        $creditNoteLogs = ZatcaCreditNoteLog::where('company_id', $company->id)
            ->with('creditNote:id,note_number')
            ->latest('id')
            ->paginate($perPage, ['*'], 'credit_note_page')
            ->withQueryString();

        $debitNoteLogs = ZatcaDebitNoteLog::where('company_id', $company->id)
            ->with('debitNote:id,note_number')
            ->latest('id')
            ->paginate($perPage, ['*'], 'debit_note_page')
            ->withQueryString();

        $stats = [
            'cleared' => $this->countAcrossLogs($company->id, 'cleared'),
            'reported' => $this->countAcrossLogs($company->id, 'reported'),
            'failed' => $this->countAcrossLogs($company->id, 'failed'),
            'queued' => $this->countAcrossLogs($company->id, 'queued'),
        ];

        return view('app.zatca.dashboard', [
            'company' => $company,
            'stats' => $stats,
            'invoiceLogs' => $invoiceLogs,
            'creditNoteLogs' => $creditNoteLogs,
            'debitNoteLogs' => $debitNoteLogs,
        ]);
    }

    /**
     * Mirrors the admin side's equivalent detail page
     * (Admin\AdminZatcaLogController::showLog()), but company-scoped: the
     * abort_if below 404s for another company's log exactly the way
     * InvoiceController::findOwned() already 404s for another company's
     * invoice, since these log models carry no global scope of their own
     * to do it automatically.
     */
    public function showLog(string $type, int $log): View|RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_company_settings')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('zatca_phase2')) {
            return $redirect;
        }

        abort_unless(in_array($type, ['invoice', 'credit_note', 'debit_note'], true), 404);

        $companyId = Auth::user()->company_id;

        $logModel = match ($type) {
            'invoice' => ZatcaInvoiceLog::with('invoice')->findOrFail($log),
            'credit_note' => ZatcaCreditNoteLog::with('creditNote')->findOrFail($log),
            'debit_note' => ZatcaDebitNoteLog::with('debitNote')->findOrFail($log),
        };

        abort_if($logModel->company_id !== $companyId, 404, 'ZATCA log not found.');

        $document = match ($type) {
            'invoice' => $logModel->invoice,
            'credit_note' => $logModel->creditNote,
            'debit_note' => $logModel->debitNote,
        };

        return view('app.zatca.log-show', [
            'type' => $type,
            'log' => $logModel,
            'document' => $document,
        ]);
    }

    private function countAcrossLogs(int $companyId, string $status): int
    {
        return ZatcaInvoiceLog::where('company_id', $companyId)->where('status', $status)->count()
            + ZatcaCreditNoteLog::where('company_id', $companyId)->where('status', $status)->count()
            + ZatcaDebitNoteLog::where('company_id', $companyId)->where('status', $status)->count();
    }
}
