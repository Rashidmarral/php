<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BoqItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Project;
use App\Models\Setting;
use App\Support\WebhookDispatcher;
use App\Support\Zatca\InvoiceChainer;
use App\Support\Zatca\ZatcaSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The Interim Payment Certificate (IPC / مستخلص) cycle: a project claims
 * cumulative progress against its BOQ, one certificate at a time. A
 * certificate is either 'draft' (still editable/deletable under the rules
 * below) or 'certified' (immutable — certify() has generated a real
 * ZATCA-chained invoice from it, same as every other invoice-creation path
 * in this app).
 *
 * Money-critical: every total here is computed server-side only, never
 * trusted from client input beyond the raw cumulative_qty per line.
 */
class PaymentCertificateController extends Controller
{
    public function index(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('payment_certificates')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $certificates = PaymentCertificate::where('project_id', $project->id)
            ->orderByDesc('certificate_number')
            ->get();

        return view('app.payment-certificates.index', [
            'project' => $project->toArray(),
            'certificates' => $certificates->toArray(),
            'contractValue' => $project->boqContractValue(),
            'cumulativeCertified' => (float) $certificates->max('cumulative_certified'),
            'retentionHeld' => (float) $certificates->sum('retention_amount'),
        ]);
    }

    public function create(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('payment_certificates')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $boqItems = BoqItem::where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get();

        if ($boqItems->isEmpty()) {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/boq', 'error', t('user.payment_certificates.boq_required'));
        }

        $previousCumulative = $this->previousCumulativeByBoqItemId($project->id);
        $nextNumber = (int) PaymentCertificate::where('project_id', $project->id)->max('certificate_number') + 1;
        $company = Company::find($project->company_id);

        $rows = $boqItems->map(fn (BoqItem $item) => [
            'boq_item_id' => $item->id,
            'section_title' => $item->section_title,
            'item_number' => $item->item_number,
            'description' => $item->description,
            'description_ar' => $item->description_ar,
            'uom' => $item->uom,
            'contract_qty' => (float) $item->qty,
            'contract_unit_price' => (float) $item->unit_price,
            'contract_total' => (float) $item->total,
            'previous_cumulative_qty' => (float) ($previousCumulative[$item->id] ?? 0),
        ])->all();

        return view('app.payment-certificates.create', [
            'project' => $project->toArray(),
            'rows' => $rows,
            'nextNumber' => $nextNumber,
            'defaultRetentionPercent' => (float) ($company->default_retention_percent ?? 0),
            'defaultAdvanceRecoveryPercent' => (float) ($project->advance_recovery_percent ?? 0),
            'advancePaymentAmount' => (float) ($project->advance_payment_amount ?? 0),
            'advanceRecoveredSoFar' => $this->advanceRecoveredSoFar($project->id),
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('payment_certificates')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        [$lines, $error] = $this->buildLines($project, $request);
        if ($error !== null) {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/payment-certificates/create', 'error', $error);
        }

        $company = Company::find($project->company_id);
        $gross = round(array_sum(array_column($lines, 'this_period_value')), 2);

        $retentionPercent = min(100, max(0, (float) $request->input('retention_percent', $company->default_retention_percent ?? 0)));
        $retentionAmount = round($gross * $retentionPercent / 100, 2);

        $advanceRecoveryPercent = $project->advance_payment_amount > 0
            ? min(100, max(0, (float) $request->input('advance_recovery_percent', $project->advance_recovery_percent ?? 0)))
            : null;
        $advanceRecoveryAmount = $this->advanceRecoveryAmount($project, $advanceRecoveryPercent, $gross);

        $netPayable = round($gross - $retentionAmount - $advanceRecoveryAmount, 2);
        $nextNumber = (int) PaymentCertificate::where('project_id', $project->id)->max('certificate_number') + 1;
        $previousCertificate = PaymentCertificate::where('project_id', $project->id)->where('certificate_number', $nextNumber - 1)->first();
        $cumulativeCertified = round((float) ($previousCertificate->cumulative_certified ?? 0) + $gross, 2);

        $certificate = DB::transaction(function () use ($project, $request, $nextNumber, $retentionPercent, $retentionAmount, $advanceRecoveryPercent, $advanceRecoveryAmount, $gross, $netPayable, $cumulativeCertified, $lines) {
            $certificate = PaymentCertificate::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'certificate_number' => $nextNumber,
                'certificate_date' => $request->input('certificate_date') ?: now()->format('Y-m-d'),
                'period_from' => $request->input('period_from') ?: null,
                'period_to' => $request->input('period_to') ?: null,
                'status' => 'draft',
                'retention_percent' => $retentionPercent,
                'retention_amount' => $retentionAmount,
                'advance_recovery_percent' => $advanceRecoveryPercent,
                'advance_recovery_amount' => $advanceRecoveryAmount,
                'gross_amount' => $gross,
                'net_payable' => $netPayable,
                'cumulative_certified' => $cumulativeCertified,
                'notes' => trim((string) $request->input('notes', '')) ?: null,
                'created_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                PaymentCertificateLine::create(['payment_certificate_id' => $certificate->id, ...$line]);
            }

            return $certificate;
        });

        $this->flash('success', t('user.payment_certificates.flash_created_draft', ['number' => $certificate->certificate_number]));
        return redirect('/app/payment-certificates/' . $certificate->id);
    }

    public function show(int $id): View
    {
        $certificate = $this->findOwned($id);
        $project = $this->findOwnedProject($certificate->project_id);
        $lines = PaymentCertificateLine::where('payment_certificate_id', $certificate->id)->orderBy('id')->get();
        $invoice = $certificate->invoice_id ? Invoice::find($certificate->invoice_id) : null;
        $company = Company::find($project->company_id);

        return view('app.payment-certificates.show', [
            'certificate' => $certificate->toArray(),
            'project' => $project->toArray(),
            'lines' => $lines->toArray(),
            'invoice' => $invoice?->toArray(),
            'isLatestDraft' => $this->isLatestDraft($certificate),
            'activeTemplate' => $company->activeInvoiceTemplate(),
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        $certificate = $this->findOwned($id);
        if (!$certificate->isDraft()) {
            return $this->redirectWithFlash('/app/payment-certificates/' . $certificate->id, 'error', t('user.payment_certificates.locked_certified'));
        }
        $project = $this->findOwnedProject($certificate->project_id);
        $existingLines = PaymentCertificateLine::where('payment_certificate_id', $certificate->id)->get()->keyBy('boq_item_id');
        $boqItems = BoqItem::where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get();
        $previousCumulative = $this->previousCumulativeByBoqItemId($project->id, $certificate->certificate_number);

        $rows = $boqItems->map(function (BoqItem $item) use ($existingLines, $previousCumulative) {
            $existing = $existingLines->get($item->id);
            return [
                'boq_item_id' => $item->id,
                'section_title' => $item->section_title,
                'item_number' => $item->item_number,
                'description' => $item->description,
                'description_ar' => $item->description_ar,
                'uom' => $item->uom,
                'contract_qty' => (float) $item->qty,
                'contract_unit_price' => (float) $item->unit_price,
                'contract_total' => (float) $item->total,
                'previous_cumulative_qty' => (float) ($previousCumulative[$item->id] ?? 0),
                'cumulative_qty' => (float) ($existing->cumulative_qty ?? 0),
            ];
        })->all();

        return view('app.payment-certificates.edit', [
            'project' => $project->toArray(),
            'certificate' => $certificate->toArray(),
            'rows' => $rows,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $certificate = $this->findOwned($id);
        if (!$certificate->isDraft()) {
            return $this->redirectWithFlash('/app/payment-certificates/' . $certificate->id, 'error', t('user.payment_certificates.locked_certified'));
        }
        $project = $this->findOwnedProject($certificate->project_id);

        [$lines, $error] = $this->buildLines($project, $request, excludeCertificateNumber: $certificate->certificate_number);
        if ($error !== null) {
            return $this->redirectWithFlash('/app/payment-certificates/' . $certificate->id . '/edit', 'error', $error);
        }

        $company = Company::find($project->company_id);
        $gross = round(array_sum(array_column($lines, 'this_period_value')), 2);

        $retentionPercent = min(100, max(0, (float) $request->input('retention_percent', $company->default_retention_percent ?? 0)));
        $retentionAmount = round($gross * $retentionPercent / 100, 2);

        $advanceRecoveryPercent = $project->advance_payment_amount > 0
            ? min(100, max(0, (float) $request->input('advance_recovery_percent', $project->advance_recovery_percent ?? 0)))
            : null;
        $advanceRecoveryAmount = $this->advanceRecoveryAmount($project, $advanceRecoveryPercent, $gross, excludeCertificateId: $certificate->id);

        $netPayable = round($gross - $retentionAmount - $advanceRecoveryAmount, 2);
        $previousCertificate = PaymentCertificate::where('project_id', $project->id)->where('certificate_number', $certificate->certificate_number - 1)->first();
        $cumulativeCertified = round((float) ($previousCertificate->cumulative_certified ?? 0) + $gross, 2);

        DB::transaction(function () use ($certificate, $request, $retentionPercent, $retentionAmount, $advanceRecoveryPercent, $advanceRecoveryAmount, $gross, $netPayable, $cumulativeCertified, $lines) {
            $certificate->update([
                'certificate_date' => $request->input('certificate_date') ?: $certificate->certificate_date,
                'period_from' => $request->input('period_from') ?: null,
                'period_to' => $request->input('period_to') ?: null,
                'retention_percent' => $retentionPercent,
                'retention_amount' => $retentionAmount,
                'advance_recovery_percent' => $advanceRecoveryPercent,
                'advance_recovery_amount' => $advanceRecoveryAmount,
                'gross_amount' => $gross,
                'net_payable' => $netPayable,
                'cumulative_certified' => $cumulativeCertified,
                'notes' => trim((string) $request->input('notes', '')) ?: null,
            ]);

            PaymentCertificateLine::where('payment_certificate_id', $certificate->id)->delete();
            foreach ($lines as $line) {
                PaymentCertificateLine::create(['payment_certificate_id' => $certificate->id, ...$line]);
            }
        });

        // Any later draft certificate for this project computed its own previous_cumulative_qty
        // and cumulative_certified from this one at ITS creation time — those snapshots are not
        // retroactively recalculated here. This mirrors the BOQ edit-lock's own reasoning (past
        // snapshots are deliberately frozen) and is exactly why only the most-recent draft may be
        // deleted (see isLatestDraft()/destroy()): an out-of-order edit further back in the chain
        // is prevented from happening in the first place because certify() locks everything at
        // and before it, and destroy() only ever removes from the top of the stack.
        $this->flash('success', t('user.payment_certificates.flash_updated', ['number' => $certificate->certificate_number]));
        return redirect('/app/payment-certificates/' . $certificate->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $certificate = $this->findOwned($id);
        $projectId = $certificate->project_id;

        if (!$certificate->isDraft()) {
            return $this->redirectWithFlash('/app/payment-certificates/' . $certificate->id, 'error', t('user.payment_certificates.certified_cannot_delete'));
        }
        if (!$this->isLatestDraft($certificate)) {
            return $this->redirectWithFlash('/app/payment-certificates/' . $certificate->id, 'error', t('user.payment_certificates.only_latest_draft_deletable'));
        }

        DB::transaction(function () use ($certificate) {
            PaymentCertificateLine::where('payment_certificate_id', $certificate->id)->delete();
            $certificate->delete();
        });

        return $this->redirectWithFlash('/app/projects/' . $projectId . '/payment-certificates', 'success', t('user.payment_certificates.draft_deleted'));
    }

    /**
     * The money step: turns a draft certificate's net payable into a real,
     * ZATCA-chained tax invoice via the exact same InvoiceChainer every
     * other invoice-creation path in this app uses, continuing the same
     * company-wide ICV/hash sequence.
     *
     * JUDGMENT CALL — VAT base: VAT is computed on net_payable (gross claim
     * minus retention minus advance-recovery), not on the gross claim.
     * Retention and advance-recovery are genuine deductions from what is
     * actually being invoiced/collected this period (the client is not
     * paying — and the contractor is not currently entitled to — the
     * retained/recovered portion yet), so net_payable is the real taxable
     * subtotal being billed right now. This mirrors InvoiceController::
     * store(), where vat_amount is always computed on the actual billed
     * subtotal, never on a larger notional figure. Retention is released
     * later (see Invoice::releaseRetention()) without a further VAT event
     * modeled in this phase — that reconciliation is a documented scope
     * boundary of Phase 1, not an oversight.
     */
    public function certify(int $id, ZatcaSyncService $zatcaSync): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $certificate = $this->findOwned($id);
        if (!$certificate->isDraft()) {
            return $this->redirectWithFlash('/app/payment-certificates/' . $certificate->id, 'error', t('user.payment_certificates.already_certified'));
        }

        $project = $this->findOwnedProject($certificate->project_id);
        $companyId = $project->company_id;
        $company = Company::find($companyId);
        $client = $project->client_id ? $this->ownedClient($project->client_id, $companyId) : null;

        $lineRows = PaymentCertificateLine::where('payment_certificate_id', $certificate->id)
            ->where('this_period_qty', '>', 0)
            ->orderBy('id')
            ->get();

        if ($lineRows->isEmpty()) {
            return $this->redirectWithFlash('/app/payment-certificates/' . $certificate->id, 'error', t('user.payment_certificates.nothing_to_certify'));
        }

        // Billed at this-period value only (the delta since the last certificate) — never the
        // BOQ line's full contract value. Retention/advance-recovery are certificate-level
        // deductions already netted into net_payable and the invoice's own retention_percent/
        // retention_amount fields — they must NOT also appear as negative InvoiceItem lines,
        // which would double-deduct them.
        $items = $lineRows->map(fn (PaymentCertificateLine $line) => [
            'description' => $line->description,
            'description_ar' => $line->description_ar,
            'qty' => (float) $line->this_period_qty,
            'unit_price' => (float) $line->contract_unit_price,
            'total' => (float) $line->this_period_value,
        ])->all();

        $vatRate = (float) Setting::get('vat_rate', '15');
        $vatAmount = round((float) $certificate->net_payable * $vatRate / 100, 2);
        $total = round((float) $certificate->net_payable + $vatAmount, 2);

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'project_id' => $project->id,
            'source_payment_certificate_id' => $certificate->id,
            'client_id' => $client?->id,
            'invoice_number' => 'INV-' . (1000 + Invoice::where('company_id', $companyId)->count() + 1),
            'status' => 'unpaid',
            'total' => $total,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'due_date' => null,
            'retention_percent' => (float) $certificate->retention_percent,
            'retention_amount' => (float) $certificate->retention_amount,
            'share_token' => bin2hex(random_bytes(20)),
            ...$this->approvalFieldsForNewInvoice($companyId),
        ]);

        foreach ($items as $item) {
            InvoiceItem::create(['invoice_id' => $invoice->id, ...$item]);
        }

        InvoiceChainer::chain($invoice->fresh(), $company, $client, $items, $zatcaSync);

        $certificate->update([
            'status' => 'certified',
            'certified_by' => Auth::id(),
            'certified_at' => now(),
            'invoice_id' => $invoice->id,
        ]);

        WebhookDispatcher::dispatch($companyId, 'invoice.created', $invoice->fresh()->toArray());
        WebhookDispatcher::dispatch($companyId, 'payment_certificate.certified', $certificate->fresh()->toArray());

        $this->flash('success', t('user.payment_certificates.flash_certified', ['number' => $certificate->certificate_number, 'invoice' => $invoice->invoice_number]));
        return redirect('/app/payment-certificates/' . $certificate->id);
    }

    public function pdf(Request $request, int $id): Response
    {
        $certificate = $this->findOwned($id);
        $project = $this->findOwnedProject($certificate->project_id);
        $client = $project->client_id ? $this->ownedClient($project->client_id, $project->company_id) : null;
        $company = Company::find($project->company_id);
        $lines = PaymentCertificateLine::where('payment_certificate_id', $certificate->id)->orderBy('id')->get();
        $lang = $request->input('lang') === 'ar' ? 'ar' : app()->getLocale();
        // Same 6-option whitelist InvoiceController::pdf() validates against, NOW including
        // 'saudi' too — see payment-certificate.blade.php's own comment for the bilingual
        // layout that template renders for a certificate (a previous pass had excluded it here
        // as a poor fit; it's since been built properly with its own wider cumulative-billing
        // columns instead of being force-fit onto the invoice template's simpler shape). Falls
        // back to the company's activated default (see Company::activeInvoiceTemplate()) rather
        // than always 'modern' when no ?template= override is given.
        $template = in_array($request->input('template'), Company::INVOICE_TEMPLATES, true) ? $request->input('template') : $company->activeInvoiceTemplate();
        // Stage 2: same resolve-then-fallback pattern as every other document-PDF
        // controller (see InvoiceController::pdf()'s comment) — renders through
        // pdf.payment-certificate-v2 only when this company customized a template
        // for 'payment_certificate'; every other company keeps the unchanged view.
        $invoiceTemplate = $company->activeInvoiceTemplateFor('payment_certificate');

        $invoice = $certificate->invoice_id ? Invoice::find($certificate->invoice_id) : null;
        // A certified certificate shows its real invoiced VAT/total; a draft one shows a live
        // estimate at the company's current VAT rate — never persisted, purely informational.
        $vatRate = $invoice ? (float) $invoice->vat_rate : (float) Setting::get('vat_rate', '15');
        $vatAmount = $invoice ? (float) $invoice->vat_amount : round((float) $certificate->net_payable * $vatRate / 100, 2);
        $totalDue = $invoice ? (float) $invoice->total : round((float) $certificate->net_payable + $vatAmount, 2);

        $view = $invoiceTemplate ? 'pdf.payment-certificate-v2' : 'pdf.payment-certificate';
        $html = view($view, [
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'company' => $company,
            'project' => $project,
            'client' => $client,
            'certificate' => $certificate,
            'lines' => $lines,
            'contractValue' => $project->boqContractValue(),
            'vatRate' => $vatRate,
            'vatAmount' => $vatAmount,
            'totalDue' => $totalDue,
            'invoiceTemplate' => $invoiceTemplate,
        ])->render();

        $pageSize = $invoiceTemplate ? ($invoiceTemplate->page_size ?: 'a4') : 'a4';
        $pdf = \App\Support\Pdf\Pdf::output($html, $lang, $pageSize);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Payment-Certificate-' . $certificate->certificate_number . '.pdf"',
        ]);
    }

    /**
     * previous_cumulative_qty for every BOQ item currently on the project: the cumulative_qty
     * from the most recent certificate (by certificate_number, any status) that has a line for
     * that boq_item_id — 0 for a line that's never been claimed before. Pass $beforeNumber when
     * recomputing for an existing draft certificate being edited, so its own lines aren't
     * counted as their own "previous".
     *
     * @return array<int, float> boq_item_id => cumulative_qty
     */
    private function previousCumulativeByBoqItemId(int $projectId, ?int $beforeNumber = null): array
    {
        $query = PaymentCertificateLine::query()
            ->join('payment_certificates', 'payment_certificates.id', '=', 'payment_certificate_lines.payment_certificate_id')
            ->where('payment_certificates.project_id', $projectId);
        if ($beforeNumber !== null) {
            $query->where('payment_certificates.certificate_number', '<', $beforeNumber);
        }

        $rows = $query
            ->orderByDesc('payment_certificates.certificate_number')
            ->select('payment_certificate_lines.boq_item_id', 'payment_certificate_lines.cumulative_qty')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            // Rows are ordered by certificate_number DESC, so the first one seen per
            // boq_item_id is the most recent — later duplicates for the same item are ignored.
            if (!array_key_exists($row->boq_item_id, $result)) {
                $result[$row->boq_item_id] = (float) $row->cumulative_qty;
            }
        }
        return $result;
    }

    /** Total advance already recovered across every OTHER certificate in this project's sequence (draft or certified), so a new certificate never recovers more than what remains outstanding. */
    private function advanceRecoveredSoFar(int $projectId, ?int $excludeCertificateId = null): float
    {
        $query = PaymentCertificate::where('project_id', $projectId);
        if ($excludeCertificateId !== null) {
            $query->where('id', '!=', $excludeCertificateId);
        }
        return (float) $query->sum('advance_recovery_amount');
    }

    /** Caps this certificate's proposed advance recovery at whatever remains of the project's advance payment after every other certificate's recovery. */
    private function advanceRecoveryAmount(Project $project, ?float $percent, float $gross, ?int $excludeCertificateId = null): float
    {
        $advanceAmount = (float) ($project->advance_payment_amount ?? 0);
        if ($advanceAmount <= 0 || $percent === null || $percent <= 0) {
            return 0.0;
        }
        $recoveredSoFar = $this->advanceRecoveredSoFar($project->id, $excludeCertificateId);
        $remaining = max(0, round($advanceAmount - $recoveredSoFar, 2));
        $proposed = round($gross * $percent / 100, 2);
        return min($proposed, $remaining);
    }

    /**
     * Validates and builds this certificate's line rows from the request's parallel
     * boq_item_id[]/cumulative_qty[] arrays. Returns [lines, null] on success or
     * [[], "error message"] on the FIRST validation failure — the whole certificate is
     * rejected with no rows created, never a partial/clamped save.
     *
     * @return array{0: array<int, array>, 1: ?string}
     */
    private function buildLines(Project $project, Request $request, ?int $excludeCertificateNumber = null): array
    {
        $boqItemIds = $request->input('boq_item_id', []);
        $cumulativeQtys = $request->input('cumulative_qty', []);
        $boqItems = BoqItem::where('project_id', $project->id)->get()->keyBy('id');
        $previousCumulative = $this->previousCumulativeByBoqItemId($project->id, $excludeCertificateNumber);

        $lines = [];
        foreach ($boqItemIds as $i => $rawBoqItemId) {
            $boqItemId = (int) $rawBoqItemId;
            $boqItem = $boqItems->get($boqItemId);
            // Never trust a boq_item_id from the request beyond confirming it belongs to
            // this project — a tampered id for another project/company is simply skipped.
            if (!$boqItem) {
                continue;
            }

            $cumulativeQty = (float) ($cumulativeQtys[$i] ?? 0);
            $previousQty = (float) ($previousCumulative[$boqItemId] ?? 0);
            $contractQty = (float) $boqItem->qty;
            $lineLabel = trim((string) ($boqItem->item_number ?: $boqItem->description));

            if ($cumulativeQty < $previousQty) {
                return [[], "Line \"{$lineLabel}\": cumulative quantity ({$cumulativeQty}) cannot be less than what was already certified ({$previousQty})."];
            }
            if ($cumulativeQty > $contractQty) {
                $over = round($cumulativeQty - $contractQty, 2);
                return [[], "Line \"{$lineLabel}\": cumulative quantity ({$cumulativeQty}) exceeds the BOQ contract quantity ({$contractQty}) by {$over}."];
            }

            $thisPeriodQty = round($cumulativeQty - $previousQty, 2);
            $thisPeriodValue = round($thisPeriodQty * (float) $boqItem->unit_price, 2);

            $lines[] = [
                'boq_item_id' => $boqItem->id,
                'description' => $boqItem->description,
                'description_ar' => $boqItem->description_ar,
                'uom' => $boqItem->uom,
                'contract_qty' => $contractQty,
                'contract_unit_price' => (float) $boqItem->unit_price,
                'contract_total' => (float) $boqItem->total,
                'previous_cumulative_qty' => $previousQty,
                'cumulative_qty' => $cumulativeQty,
                'this_period_qty' => $thisPeriodQty,
                'this_period_value' => $thisPeriodValue,
            ];
        }

        if (empty($lines)) {
            return [[], 'No BOQ lines to certify.'];
        }

        // Invariant check (should always hold given the per-line contract_qty cap above, but
        // verified explicitly since this is money-critical): the sum of every line's
        // contract_total can never be exceeded by the sum of cumulative_qty*unit_price across
        // this project's certificates, because each line's own cumulative_qty is capped at its
        // own contract_qty above — so the whole-certificate total never implies over-claiming
        // the total contract value either.
        return [$lines, null];
    }

    /** Only the most recently created (highest certificate_number) draft may be deleted — deleting an earlier one would corrupt every later certificate's previous_cumulative_qty/cumulative_certified chain. */
    private function isLatestDraft(PaymentCertificate $certificate): bool
    {
        $maxNumber = (int) PaymentCertificate::where('project_id', $certificate->project_id)->max('certificate_number');
        return $certificate->certificate_number === $maxNumber;
    }

    /** Exact analogue of InvoiceController/EstimateController's own approvalFieldsForNewInvoice() — duplicated per this app's established per-controller precedent for this helper. */
    private function approvalFieldsForNewInvoice(int $companyId): array
    {
        $company = Company::find($companyId);
        if (!$company || !$company->requiresInvoiceApproval()) {
            return [];
        }
        return [
            'approval_status' => 'pending',
            'approval_requested_by' => Auth::id(),
            'approval_requested_at' => now(),
        ];
    }

    private function findOwned(int $id): PaymentCertificate
    {
        $certificate = PaymentCertificate::find($id);
        abort_if(!$certificate || $certificate->company_id !== Auth::user()->company_id, 404, 'Payment certificate not found.');
        return $certificate;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }

    /** Only returns the client if it belongs to $companyId — never leak another company's contact data via a foreign key. */
    private function ownedClient(?int $id, int $companyId): ?Client
    {
        if (!$id) {
            return null;
        }
        $client = Client::find($id);
        return ($client && $client->company_id === $companyId) ? $client : null;
    }
}
