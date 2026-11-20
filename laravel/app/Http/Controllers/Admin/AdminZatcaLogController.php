<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCreditNoteToZatca;
use App\Jobs\SyncDebitNoteToZatca;
use App\Jobs\SyncInvoiceToZatca;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\View\View;

/**
 * Platform-wide, cross-company read view over every ZATCA submission ever
 * attempted — the dedicated zatca_invoice_logs / zatca_credit_note_logs /
 * zatca_debit_note_logs history tables (see those migrations' docblocks),
 * which until now nothing in the admin panel surfaced at all.
 *
 * Deliberately a separate controller from CompanyZatcaController: that one
 * owns the per-company onboarding wizard (CSR/OTP/compliance/production-
 * CSID — see its own class docblock); this one is strictly observe+retry
 * across every company's submission history. Same split Daftari's own
 * Admin\ZatcaController draws against its User\ZatcaController, ported
 * here trimmed to what BuildXact's schema actually has: no Subscription/
 * Plan "has_zatca_phase2" concept (BuildXact gates Phase 2 via
 * App\Support\Feature instead), and no TaxRate/VatExemptionReason/
 * PrepaymentInvoice (explicitly out of scope for this port). None of the
 * three log models carry a BelongsToCompany global scope (unlike Daftari's
 * own), so every query here is plain Eloquent with no
 * withoutGlobalScopes() needed.
 *
 * retry() dispatches the existing queued App\Jobs\Sync*ToZatca job rather
 * than calling ZatcaSyncService synchronously — matching how this app
 * already auto-submits newly created documents, and keeping this
 * admin-support action consistent with that one submission path.
 */
class AdminZatcaLogController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const LOG_MODELS = [
        'invoice' => ZatcaInvoiceLog::class,
        'credit_note' => ZatcaCreditNoteLog::class,
        'debit_note' => ZatcaDebitNoteLog::class,
    ];

    /** @var array<string, string> */
    private const DOCUMENT_RELATIONS = [
        'invoice' => 'invoice',
        'credit_note' => 'creditNote',
        'debit_note' => 'debitNote',
    ];

    public function index(Request $request): View
    {
        $stats = $this->submissionStats();
        $stats['connected'] = Company::where('zatca_status', 'onboarded')->whereNotNull('zatca_production_csid')->count();
        $stats['pending_onboarding'] = Company::whereIn('zatca_status', ['csr_generated', 'compliance_pending', 'compliance_verified'])->count();
        $stats['failed_connections'] = Company::whereIn('zatca_status', ['failed', 'error'])->count();

        $query = Company::query();

        if ($request->filled('q')) {
            $term = trim((string) $request->string('q'));
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('vat_number', 'like', "%{$term}%"));
        }
        if ($request->filled('environment')) {
            $query->where('zatca_environment', $request->string('environment'));
        }
        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'connected' => $query->where('zatca_status', 'onboarded')->whereNotNull('zatca_production_csid'),
                'pending' => $query->whereIn('zatca_status', ['csr_generated', 'compliance_pending', 'compliance_verified']),
                'failed' => $query->whereIn('zatca_status', ['failed', 'error']),
                'not_connected' => $query->where('zatca_status', 'not_started'),
                default => null,
            };
        }

        $companies = $query->orderBy('name')->paginate($this->perPage($request), ['*'], 'page')->withQueryString();

        [$lastSubmission, $lastSuccessful, $failedCounts] = $this->perCompanyAggregates($companies->pluck('id'));

        // Only computed for already-onboarded companies on this page (same
        // reasoning as Daftari's own index()) — pagination caps this page
        // at $this->perPage() companies, so it stays cheap.
        $pendingCounts = $companies->getCollection()
            ->filter(fn (Company $company) => $company->isZatcaOnboarded())
            ->mapWithKeys(fn (Company $company) => [$company->id => $this->pendingCount($company)]);

        return view('admin.zatca.index', [
            'stats' => $stats,
            'companies' => $companies,
            'lastSubmission' => $lastSubmission,
            'lastSuccessful' => $lastSuccessful,
            'failedCounts' => $failedCounts,
            'pendingCounts' => $pendingCounts,
            'recentErrors' => $this->recentErrors(),
        ]);
    }

    public function logs(Request $request): View
    {
        $companies = Company::orderBy('name')->get(['id', 'name']);

        $applyFilters = function ($query) use ($request) {
            if ($request->filled('company_id')) {
                $query->where('company_id', $request->integer('company_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->string('status'));
            }
            if ($request->filled('environment')) {
                $query->where('environment', $request->string('environment'));
            }

            return $query;
        };

        $typeFilter = $request->filled('type') && array_key_exists($request->string('type')->toString(), self::LOG_MODELS)
            ? $request->string('type')->toString()
            : null;

        $perPage = $this->perPage($request);

        // Three separate paginated lists (rather than one merged/UNIONed
        // table) — the three log tables have different foreign keys and
        // no shared sortable identity to paginate over. $typeFilter narrows
        // which of the three sections the view actually renders.
        $invoiceLogs = (! $typeFilter || $typeFilter === 'invoice')
            ? $applyFilters(ZatcaInvoiceLog::with(['company:id,name', 'invoice:id,invoice_number']))
                ->latest('id')->paginate($perPage, ['*'], 'invoice_page')->withQueryString()
            : null;

        $creditNoteLogs = (! $typeFilter || $typeFilter === 'credit_note')
            ? $applyFilters(ZatcaCreditNoteLog::with(['company:id,name', 'creditNote:id,note_number']))
                ->latest('id')->paginate($perPage, ['*'], 'credit_note_page')->withQueryString()
            : null;

        $debitNoteLogs = (! $typeFilter || $typeFilter === 'debit_note')
            ? $applyFilters(ZatcaDebitNoteLog::with(['company:id,name', 'debitNote:id,note_number']))
                ->latest('id')->paginate($perPage, ['*'], 'debit_note_page')->withQueryString()
            : null;

        return view('admin.zatca.logs', compact('invoiceLogs', 'creditNoteLogs', 'debitNoteLogs', 'companies', 'typeFilter'));
    }

    public function showLog(string $type, int $log): View
    {
        [$logModel, $document] = $this->resolveLog($type, $log);

        return view('admin.zatca.log-show', [
            'type' => $type,
            'log' => $logModel,
            'document' => $document,
        ]);
    }

    /**
     * Every document for this company that has been chained for ZATCA
     * (zatca_uuid set — see InvoiceChainer) but has not yet been cleared
     * or reported — i.e. still worth watching, whether it has never been
     * submitted, or its only attempt(s) so far failed.
     */
    public function pendingDocuments(int $id): View
    {
        $company = Company::findOrFail($id);

        return view('admin.zatca.pending', [
            'company' => $company,
            'pendingInvoices' => $this->pendingInvoices($company)->load('client'),
            'pendingCreditNotes' => $this->pendingCreditNotes($company)->load('client'),
            'pendingDebitNotes' => $this->pendingDebitNotes($company)->load('client'),
        ]);
    }

    /**
     * Re-dispatches the queued sync job for this log's underlying document
     * — a brand-new submission attempt (a brand-new log row once the job
     * runs), never a resubmission of the failed one itself. Blocked once
     * the document already carries a cleared/reported outcome, the same
     * "a locked document can't be resubmitted" rule ZatcaSyncService's own
     * re-check-after-lock enforces for the regular submission path.
     */
    public function retry(string $type, int $log): RedirectResponse
    {
        [, $document] = $this->resolveLog($type, $log);

        abort_if(! $document, 404);

        if ($document->isZatcaLocked()) {
            return $this->redirectWithFlash('/admin/zatca/logs', 'error', t('admin.zatca_logs.already_locked'));
        }

        match ($type) {
            'invoice' => SyncInvoiceToZatca::dispatch($document->id),
            'credit_note' => SyncCreditNoteToZatca::dispatch($document->id),
            'debit_note' => SyncDebitNoteToZatca::dispatch($document->id),
        };

        return $this->redirectWithFlash('/admin/zatca/logs', 'success', t('admin.zatca_logs.retry_dispatched'));
    }

    /**
     * @return array{0: Model, 1: ?Model}
     */
    private function resolveLog(string $type, int $id): array
    {
        abort_unless(array_key_exists($type, self::LOG_MODELS), 404);

        $relation = self::DOCUMENT_RELATIONS[$type];
        $modelClass = self::LOG_MODELS[$type];

        $log = $modelClass::with(['company', $relation])->findOrFail($id);

        return [$log, $log->{$relation}];
    }

    private function pendingInvoices(Company $company): Collection
    {
        return Invoice::where('company_id', $company->id)
            ->whereNotNull('zatca_uuid')
            ->whereNotIn('zatca_status', ['cleared', 'reported'])
            ->orderByDesc('created_at')
            ->get();
    }

    private function pendingCreditNotes(Company $company): Collection
    {
        return CreditNote::where('company_id', $company->id)
            ->whereNotNull('zatca_uuid')
            ->whereNotIn('zatca_status', ['cleared', 'reported'])
            ->orderByDesc('created_at')
            ->get();
    }

    private function pendingDebitNotes(Company $company): Collection
    {
        return DebitNote::where('company_id', $company->id)
            ->whereNotNull('zatca_uuid')
            ->whereNotIn('zatca_status', ['cleared', 'reported'])
            ->orderByDesc('created_at')
            ->get();
    }

    private function pendingCount(Company $company): int
    {
        return $this->pendingInvoices($company)->count()
            + $this->pendingCreditNotes($company)->count()
            + $this->pendingDebitNotes($company)->count();
    }

    /**
     * "Accepted" = cleared/reported. The two failure buckets below split
     * our OWN stored error_message, not a ZATCA-native status: every
     * submitInvoice()/submitCreditNote()/submitDebitNote() call in
     * ZatcaSyncService writes 'HTTP <code>: <body>' when ZATCA actually
     * answered and rejected the document, and writes the raw exception
     * message (or the "onboarding not complete" precheck message)
     * otherwise — see ZatcaSyncService's own catch blocks.
     */
    private function submissionStats(): array
    {
        $totalSubmitted = 0;
        $accepted = 0;
        $rejected = 0;
        $failedSubmissions = 0;

        foreach (self::LOG_MODELS as $modelClass) {
            $totalSubmitted += $modelClass::count();
            $accepted += $modelClass::whereIn('status', ['cleared', 'reported'])->count();
            $rejected += $modelClass::where('status', 'failed')->where('error_message', 'like', 'HTTP %')->count();
            $failedSubmissions += $modelClass::where('status', 'failed')
                ->where(fn ($q) => $q->whereNull('error_message')->orWhere('error_message', 'not like', 'HTTP %'))
                ->count();
        }

        return [
            'total_submitted' => $totalSubmitted,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'failed_submissions' => $failedSubmissions,
        ];
    }

    /**
     * Last submission time, last successful (cleared/reported) time, and
     * failed-submission count per company, across all three log tables —
     * one grouped query per table (not N+1 per company), same pattern
     * Admin\CompanyController::index() already uses for its own per-page
     * aggregate columns.
     *
     * @return array{0: BaseCollection, 1: BaseCollection, 2: BaseCollection}
     */
    private function perCompanyAggregates(BaseCollection $companyIds): array
    {
        $lastSubmission = collect();
        $lastSuccessful = collect();
        $failedCounts = collect();

        foreach (self::LOG_MODELS as $modelClass) {
            $modelClass::whereIn('company_id', $companyIds)
                ->selectRaw("company_id, MAX(submitted_at) as last_submission, MAX(cleared_at) as last_successful, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
                ->groupBy('company_id')
                ->get()
                ->each(function ($row) use ($lastSubmission, $lastSuccessful, $failedCounts) {
                    if ($row->last_submission) {
                        $lastSubmission[$row->company_id] = max($lastSubmission[$row->company_id] ?? '', $row->last_submission);
                    }
                    if ($row->last_successful) {
                        $lastSuccessful[$row->company_id] = max($lastSuccessful[$row->company_id] ?? '', $row->last_successful);
                    }
                    $failedCounts[$row->company_id] = ($failedCounts[$row->company_id] ?? 0) + (int) $row->failed_count;
                });
        }

        return [$lastSubmission, $lastSuccessful, $failedCounts];
    }

    /**
     * Latest failed submissions across all three log tables, merged and
     * re-sorted in PHP (small N — capped below — so this is simpler and
     * safer than a raw SQL UNION across three differently-shaped tables).
     */
    private function recentErrors(int $limit = 10): BaseCollection
    {
        $referenceColumn = [
            'invoice' => ['relation' => 'invoice', 'number' => 'invoice_number', 'fk' => 'invoice_id'],
            'credit_note' => ['relation' => 'creditNote', 'number' => 'note_number', 'fk' => 'credit_note_id'],
            'debit_note' => ['relation' => 'debitNote', 'number' => 'note_number', 'fk' => 'debit_note_id'],
        ];

        $all = collect();

        foreach (self::LOG_MODELS as $type => $modelClass) {
            $relation = $referenceColumn[$type]['relation'];
            $numberField = $referenceColumn[$type]['number'];
            $fk = $referenceColumn[$type]['fk'];

            $all = $all->concat(
                $modelClass::where('status', 'failed')
                    ->with(['company:id,name', $relation])
                    ->latest('id')->take($limit)->get()
                    ->map(fn ($log) => [
                        'company' => $log->company?->name,
                        'reference' => $log->{$relation}?->{$numberField} ?? '#'.$log->{$fk},
                        'type' => $type,
                        'log_id' => $log->id,
                        'error' => $log->error_message,
                        'created_at' => $log->created_at,
                    ])
            );
        }

        return $all->sortByDesc('created_at')->take($limit)->values();
    }
}
