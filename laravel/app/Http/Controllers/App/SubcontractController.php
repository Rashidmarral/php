<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Back-to-back subcontractor billing: a subcontract is a single lump-sum contract_value
 * hired under the project's main contract from a Supplier (this app already uses Supplier
 * generically as "who you pay" — reused here as the subcontractor party rather than
 * inventing a parallel model). Deliberately simpler than the main IPC module's
 * BoqController/PaymentCertificateController pair: no per-line BOQ, just one lump-sum
 * value claimed cumulatively — see SubcontractPaymentController for the payment-cycle half
 * of this pattern.
 *
 * Money-critical edit locks: once a subcontract has any CERTIFIED payment, its
 * contract_value/retention_percent can no longer change (a certified payment has already
 * snapshotted its own retention_percent and been billed against the contract_value as it
 * stood) — mirrors BoqController's own edit-lock reasoning. Once a subcontract has ANY
 * payment at all (draft or certified), it can no longer be deleted.
 */
class SubcontractController extends Controller
{
    public function index(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('subcontractors')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $subcontracts = Subcontract::where('project_id', $project->id)->orderByDesc('created_at')->get();
        $suppliers = Supplier::where('company_id', $project->company_id)->get()->keyBy('id');

        $rows = $subcontracts->map(fn (Subcontract $s) => [
            ...$s->toArray(),
            'supplier_name' => $suppliers->get($s->supplier_id)->name ?? '—',
            'cumulative_paid' => $s->cumulativePaid(),
            'retention_held' => $s->retentionHeld(),
        ])->all();

        return view('app.subcontracts.index', [
            'project' => $project->toArray(),
            'subcontracts' => $rows,
            'statuses' => Subcontract::STATUSES,
            'totalContractValue' => (float) $subcontracts->sum('contract_value'),
            'totalCumulativePaid' => round(array_sum(array_column($rows, 'cumulative_paid')), 2),
            'totalRetentionHeld' => round(array_sum(array_column($rows, 'retention_held')), 2),
        ]);
    }

    public function create(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('subcontractors')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $company = Company::find($project->company_id);

        return view('app.subcontracts.create', [
            'project' => $project->toArray(),
            'suppliers' => Supplier::where('company_id', $project->company_id)->orderBy('name')->get()->toArray(),
            'defaultRetentionPercent' => (float) ($company->default_retention_percent ?? 0),
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('subcontractors')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        [$data, $error] = $this->validated($request, $project->company_id);
        if ($error !== null) {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/subcontracts/create', 'error', $error);
        }

        $subcontract = Subcontract::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            ...$data,
        ]);

        $this->flash('success', t('user.subcontracts.flash_created'));
        return redirect('/app/subcontracts/' . $subcontract->id);
    }

    public function show(int $id): View
    {
        $subcontract = $this->findOwned($id);
        $project = $this->findOwnedProject($subcontract->project_id);
        $supplier = Supplier::find($subcontract->supplier_id);
        $payments = SubcontractPayment::where('subcontract_id', $subcontract->id)->orderByDesc('payment_number')->get();

        return view('app.subcontracts.show', [
            'subcontract' => $subcontract->toArray(),
            'project' => $project->toArray(),
            'supplier' => $supplier?->toArray(),
            'payments' => $payments->toArray(),
            'cumulativePaid' => $subcontract->cumulativePaid(),
            'retentionHeld' => $subcontract->retentionHeld(),
            'remaining' => max(0, round((float) $subcontract->contract_value - $subcontract->cumulativePaid(), 2)),
            'hasCertifiedPayment' => $subcontract->hasCertifiedPayment(),
            'hasAnyPayment' => $subcontract->hasAnyPayment(),
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        $subcontract = $this->findOwned($id);
        if ($subcontract->hasCertifiedPayment()) {
            return $this->redirectWithFlash('/app/subcontracts/' . $subcontract->id, 'error', t('user.subcontracts.locked_certified_payment'));
        }
        $project = $this->findOwnedProject($subcontract->project_id);

        return view('app.subcontracts.edit', [
            'subcontract' => $subcontract->toArray(),
            'project' => $project->toArray(),
            'suppliers' => Supplier::where('company_id', $subcontract->company_id)->orderBy('name')->get()->toArray(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $subcontract = $this->findOwned($id);
        if ($subcontract->hasCertifiedPayment()) {
            return $this->redirectWithFlash('/app/subcontracts/' . $subcontract->id, 'error', t('user.subcontracts.locked_certified_payment'));
        }

        [$data, $error] = $this->validated($request, $subcontract->company_id);
        if ($error !== null) {
            return $this->redirectWithFlash('/app/subcontracts/' . $subcontract->id . '/edit', 'error', $error);
        }

        $subcontract->update($data);

        $this->flash('success', t('user.subcontracts.flash_updated'));
        return redirect('/app/subcontracts/' . $subcontract->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $subcontract = $this->findOwned($id);
        if ($subcontract->hasAnyPayment()) {
            return $this->redirectWithFlash('/app/subcontracts/' . $subcontract->id, 'error', t('user.subcontracts.has_payment_cannot_delete'));
        }
        $projectId = $subcontract->project_id;
        $subcontract->delete();

        return $this->redirectWithFlash('/app/projects/' . $projectId . '/subcontracts', 'success', t('user.subcontracts.removed'));
    }

    /**
     * Validates and normalizes store()/update()'s shared input. Returns [data, null] on
     * success or [[], "error message"] on the first validation failure — never a partial save.
     *
     * @return array{0: array, 1: ?string}
     */
    private function validated(Request $request, int $companyId): array
    {
        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return [[], 'A subcontract needs a title.'];
        }

        $supplier = $this->ownedSupplier($request->input('supplier_id') ?: null, $companyId);
        if (!$supplier) {
            return [[], 'Select a valid subcontractor.'];
        }

        $contractValue = (float) $request->input('contract_value', 0);
        if ($contractValue <= 0) {
            return [[], 'The contract value must be greater than zero.'];
        }

        $retentionPercent = min(100, max(0, (float) $request->input('retention_percent', 0)));
        $status = in_array($request->input('status'), array_keys(Subcontract::STATUSES), true) ? $request->input('status') : 'active';

        return [[
            'supplier_id' => $supplier->id,
            'title' => $title,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'contract_value' => round($contractValue, 2),
            'retention_percent' => $retentionPercent,
            'status' => $status,
            'start_date' => $request->input('start_date') ?: null,
            'end_date' => $request->input('end_date') ?: null,
        ], null];
    }

    /** Only returns the supplier if it belongs to $companyId — never trust a raw supplier_id from the request. */
    private function ownedSupplier(?int $id, int $companyId): ?Supplier
    {
        if (!$id) {
            return null;
        }
        $supplier = Supplier::find($id);
        return ($supplier && $supplier->company_id === $companyId) ? $supplier : null;
    }

    private function findOwned(int $id): Subcontract
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
