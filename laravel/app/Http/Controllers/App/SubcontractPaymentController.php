<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\VendorBill;
use App\Support\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The back-to-back subcontractor progress-payment cycle: a subcontract claims cumulative
 * value against its single lump-sum contract_value, one payment at a time — the direct
 * analogue of PaymentCertificateController's cycle, but purchase-side and single-value (see
 * Subcontract's own docblock). A payment is either 'draft' (still editable/deletable under
 * the rules below) or 'certified' (immutable — certify() has recorded a real VendorBill from
 * it, never a ZATCA e-invoice: this is money the contractor pays OUT to a subcontractor, not
 * a sales invoice to its own client).
 *
 * Money-critical: cumulative_value is validated server-side only, never trusted from
 * client-side arithmetic — it must never fall below the previous payment's cumulative_value
 * (never un-claim already-certified progress) and never exceed the subcontract's
 * contract_value (the actual overpayment-prevention mechanism this module exists for).
 */
class SubcontractPaymentController extends Controller
{
    public function create(int $subcontractId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('subcontractors')) {
            return $redirect;
        }
        $subcontract = $this->findOwnedSubcontract($subcontractId);
        $project = $this->findOwnedProject($subcontract->project_id);

        $previousCumulative = $this->previousCumulative($subcontract->id);
        $nextNumber = (int) SubcontractPayment::where('subcontract_id', $subcontract->id)->max('payment_number') + 1;

        return view('app.subcontract-payments.create', [
            'subcontract' => $subcontract->toArray(),
            'project' => $project->toArray(),
            'nextNumber' => $nextNumber,
            'previousCumulative' => $previousCumulative,
            'contractValue' => (float) $subcontract->contract_value,
            'defaultRetentionPercent' => (float) $subcontract->retention_percent,
        ]);
    }

    public function store(Request $request, int $subcontractId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('subcontractors')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $subcontract = $this->findOwnedSubcontract($subcontractId);

        [$data, $error] = $this->buildPayment($subcontract, $request);
        if ($error !== null) {
            return $this->redirectWithFlash('/app/subcontracts/' . $subcontract->id . '/payments/create', 'error', $error);
        }

        $payment = SubcontractPayment::create([
            'company_id' => $subcontract->company_id,
            'subcontract_id' => $subcontract->id,
            'created_by' => Auth::id(),
            ...$data,
        ]);

        $this->flash('success', t('user.subcontract_payments.flash_created_draft', ['number' => $payment->payment_number]));
        return redirect('/app/subcontract-payments/' . $payment->id);
    }

    public function show(int $id): View
    {
        $payment = $this->findOwned($id);
        $subcontract = $this->findOwnedSubcontract($payment->subcontract_id);
        $project = $this->findOwnedProject($subcontract->project_id);
        $vendorBill = $payment->vendor_bill_id ? VendorBill::find($payment->vendor_bill_id) : null;

        return view('app.subcontract-payments.show', [
            'payment' => $payment->toArray(),
            'subcontract' => $subcontract->toArray(),
            'project' => $project->toArray(),
            'vendorBill' => $vendorBill?->toArray(),
            'isLatestDraft' => $this->isLatestDraft($payment),
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        $payment = $this->findOwned($id);
        if (!$payment->isDraft()) {
            return $this->redirectWithFlash('/app/subcontract-payments/' . $payment->id, 'error', 'This payment has already been certified and can no longer be edited.');
        }
        $subcontract = $this->findOwnedSubcontract($payment->subcontract_id);
        $project = $this->findOwnedProject($subcontract->project_id);
        $previousCumulative = $this->previousCumulative($subcontract->id, $payment->payment_number);

        return view('app.subcontract-payments.edit', [
            'payment' => $payment->toArray(),
            'subcontract' => $subcontract->toArray(),
            'project' => $project->toArray(),
            'previousCumulative' => $previousCumulative,
            'contractValue' => (float) $subcontract->contract_value,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $payment = $this->findOwned($id);
        if (!$payment->isDraft()) {
            return $this->redirectWithFlash('/app/subcontract-payments/' . $payment->id, 'error', 'This payment has already been certified and can no longer be edited.');
        }
        $subcontract = $this->findOwnedSubcontract($payment->subcontract_id);

        [$data, $error] = $this->buildPayment($subcontract, $request, excludePaymentNumber: $payment->payment_number);
        if ($error !== null) {
            return $this->redirectWithFlash('/app/subcontract-payments/' . $payment->id . '/edit', 'error', $error);
        }

        $payment->update($data);

        $this->flash('success', t('user.subcontract_payments.flash_updated', ['number' => $payment->payment_number]));
        return redirect('/app/subcontract-payments/' . $payment->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $payment = $this->findOwned($id);
        $subcontractId = $payment->subcontract_id;

        if (!$payment->isDraft()) {
            return $this->redirectWithFlash('/app/subcontract-payments/' . $payment->id, 'error', 'A certified payment cannot be deleted.');
        }
        if (!$this->isLatestDraft($payment)) {
            return $this->redirectWithFlash('/app/subcontract-payments/' . $payment->id, 'error', 'Only the most recently created draft payment can be deleted — a later payment already exists for this subcontract and its numbers depend on this one staying intact.');
        }

        $payment->delete();
        return $this->redirectWithFlash('/app/subcontracts/' . $subcontractId, 'success', 'Draft payment deleted.');
    }

    /**
     * The money step: turns a draft payment's net payable into a real VendorBill (this
     * app's existing expense-recording entity) — never a ZATCA-chained invoice, since this
     * app never issues sales e-invoices for money it pays OUT to a subcontractor.
     */
    public function certify(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $payment = $this->findOwned($id);
        if (!$payment->isDraft()) {
            return $this->redirectWithFlash('/app/subcontract-payments/' . $payment->id, 'error', 'This payment has already been certified.');
        }
        $subcontract = $this->findOwnedSubcontract($payment->subcontract_id);
        $companyId = $subcontract->company_id;

        $vendorBill = DB::transaction(function () use ($payment, $subcontract, $companyId) {
            $vendorBill = VendorBill::create([
                'company_id' => $companyId,
                'project_id' => $subcontract->project_id,
                'supplier_id' => $subcontract->supplier_id,
                'category' => 'subcontractor',
                'description' => 'Subcontract progress payment #' . $payment->payment_number . ' — ' . $subcontract->title,
                'amount' => (float) $payment->net_payable,
                'bill_date' => $payment->payment_date,
                'reference' => 'SC-' . $subcontract->id . '-' . $payment->payment_number,
                'status' => 'unpaid',
            ]);

            $payment->update([
                'status' => 'certified',
                'certified_by' => Auth::id(),
                'certified_at' => now(),
                'vendor_bill_id' => $vendorBill->id,
            ]);

            return $vendorBill;
        });

        WebhookDispatcher::dispatch($companyId, 'subcontract_payment.certified', $payment->fresh()->toArray());

        $this->flash('success', t('user.subcontract_payments.flash_certified', ['number' => $payment->payment_number, 'reference' => $vendorBill->reference]));
        return redirect('/app/subcontract-payments/' . $payment->id);
    }

    /**
     * previous_cumulative_value for a subcontract: the cumulative_value from the most recent
     * payment (by payment_number, any status) — 0 if none yet. Pass $beforeNumber when
     * recomputing for an existing draft payment being edited, so it isn't counted as its own
     * "previous".
     */
    private function previousCumulative(int $subcontractId, ?int $beforeNumber = null): float
    {
        $query = SubcontractPayment::where('subcontract_id', $subcontractId);
        if ($beforeNumber !== null) {
            $query->where('payment_number', '<', $beforeNumber);
        }
        $latest = $query->orderByDesc('payment_number')->first();
        return (float) ($latest->cumulative_value ?? 0);
    }

    /**
     * Validates and builds a payment's fields from the request's raw cumulative_value.
     * Returns [data, null] on success or [[], "error message"] on failure — never a
     * partial/clamped save.
     *
     * @return array{0: array, 1: ?string}
     */
    private function buildPayment(Subcontract $subcontract, Request $request, ?int $excludePaymentNumber = null): array
    {
        $previousCumulative = $this->previousCumulative($subcontract->id, $excludePaymentNumber);
        $cumulativeValue = round((float) $request->input('cumulative_value', 0), 2);
        $contractValue = (float) $subcontract->contract_value;

        if ($cumulativeValue < $previousCumulative) {
            return [[], "Cumulative value ({$cumulativeValue}) cannot be less than what was already certified ({$previousCumulative})."];
        }
        if ($cumulativeValue > $contractValue) {
            $over = round($cumulativeValue - $contractValue, 2);
            return [[], "Cumulative value ({$cumulativeValue}) exceeds this subcontract's contract value ({$contractValue}) by {$over}."];
        }

        $thisPeriodValue = round($cumulativeValue - $previousCumulative, 2);
        $retentionPercent = min(100, max(0, (float) $request->input('retention_percent', $subcontract->retention_percent)));
        $retentionAmount = round($thisPeriodValue * $retentionPercent / 100, 2);
        $netPayable = round($thisPeriodValue - $retentionAmount, 2);

        $paymentNumber = $excludePaymentNumber
            ?? ((int) SubcontractPayment::where('subcontract_id', $subcontract->id)->max('payment_number') + 1);

        return [[
            'payment_number' => $paymentNumber,
            'payment_date' => $request->input('payment_date') ?: now()->format('Y-m-d'),
            'status' => 'draft',
            'cumulative_value' => $cumulativeValue,
            'previous_cumulative_value' => $previousCumulative,
            'this_period_value' => $thisPeriodValue,
            'retention_percent' => $retentionPercent,
            'retention_amount' => $retentionAmount,
            'net_payable' => $netPayable,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ], null];
    }

    /** Only the most recently created (highest payment_number) draft may be deleted — deleting an earlier one would corrupt every later payment's previous_cumulative_value chain. */
    private function isLatestDraft(SubcontractPayment $payment): bool
    {
        $maxNumber = (int) SubcontractPayment::where('subcontract_id', $payment->subcontract_id)->max('payment_number');
        return $payment->payment_number === $maxNumber;
    }

    private function findOwned(int $id): SubcontractPayment
    {
        $payment = SubcontractPayment::find($id);
        abort_if(!$payment || $payment->company_id !== Auth::user()->company_id, 404, 'Subcontract payment not found.');
        return $payment;
    }

    private function findOwnedSubcontract(int $id): Subcontract
    {
        $subcontract = Subcontract::find($id);
        abort_if(!$subcontract || $subcontract->company_id !== Auth::user()->company_id, 404, 'Subcontract not found.');
        return $subcontract;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
