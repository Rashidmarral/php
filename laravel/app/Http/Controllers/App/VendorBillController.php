<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CostCode;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\VendorBill;
use App\Models\VendorBillSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VendorBillController extends Controller
{
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $companyId = Auth::user()->company_id;

        $description = trim((string) $request->input('description'));
        $amount = (float) $request->input('amount', 0);
        if ($description === '' || $amount <= 0) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.vendor_bills.description_amount_required'));
        }

        $filePath = null;
        $file = $request->file('receipt');
        if ($file && $file->isValid()) {
            $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.vendor_bills.receipt_type_not_allowed', ['allowed' => implode(', ', self::ALLOWED_EXT)]));
            }
            $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
            $file->move(public_path("uploads/vendor-bills/{$companyId}"), $storedName);
            $filePath = "/uploads/vendor-bills/{$companyId}/{$storedName}";
        }

        $purchaseOrder = $this->ownedPurchaseOrder($request->input('purchase_order_id') ?: null, $project->id);

        VendorBill::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'supplier_id' => $request->input('supplier_id') ?: null,
            'purchase_order_id' => $purchaseOrder?->id,
            'category' => in_array($request->input('category'), array_keys(VendorBill::CATEGORIES), true) ? $request->input('category') : 'material',
            'description' => $description,
            'amount' => $amount,
            'bill_date' => $request->input('bill_date') ?: now()->format('Y-m-d'),
            'reference' => trim((string) $request->input('reference', '')),
            'status' => in_array($request->input('status'), ['unpaid', 'paid'], true) ? $request->input('status') : 'unpaid',
            'file_path' => $filePath,
            'cost_code_id' => CostCode::ownedById($request->input('cost_code_id') ?: null, $companyId)?->id,
        ]);

        // A bill arriving against a PO is the natural signal the goods/services showed up — flip it to
        // received (it stays visible with its new status, never hidden or deleted just because it billed).
        if ($purchaseOrder && $purchaseOrder->status !== 'received') {
            $purchaseOrder->update(['status' => 'received']);
        }

        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', t('user.vendor_bills.recorded'));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $bill = $this->findOwned($id);
        $projectId = $bill->project_id;
        if ($bill->file_path) {
            $file = public_path($bill->file_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $bill->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.vendor_bills.removed'));
    }

    /**
     * The supplier portal's review queue (see VendorBillSubmission's own docblock) — every
     * pending submission across this company, newest first, with Approve/Reject actions.
     */
    public function pendingSubmissions(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('supplier_portal')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $submissions = VendorBillSubmission::where('company_id', $companyId)->where('status', 'pending')->orderByDesc('created_at')->get();

        return view('app.vendor-bills.submissions', [
            'submissions' => $submissions->map(fn (VendorBillSubmission $s) => [
                ...$s->toArray(),
                'supplier_name' => $s->supplier?->name ?? '—',
                'project_name' => $s->project?->name ?? '—',
            ])->all(),
        ]);
    }

    /**
     * Turns a pending submission into a real VendorBill — fields copied 1:1 from the
     * submission, status defaulting to 'unpaid' exactly like a normal manually-entered bill
     * (VendorBillController::store() above never sets anything else on creation either).
     * This is the ONLY path that may ever turn a supplier's self-submitted row into a real
     * cost — see VendorBillSubmission's docblock for why that gate matters.
     */
    public function approveSubmission(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('supplier_portal')) {
            return $redirect;
        }
        $submission = $this->findOwnedSubmission($id);
        if (!$submission->isPending()) {
            return $this->redirectWithFlash('/app/vendor-bill-submissions', 'error', t('user.vendor_bills.submission_already_reviewed'));
        }

        $bill = VendorBill::create([
            'company_id' => $submission->company_id,
            'project_id' => $submission->project_id,
            'supplier_id' => $submission->supplier_id,
            'purchase_order_id' => $submission->purchase_order_id,
            'category' => $submission->category,
            'description' => $submission->description,
            'amount' => $submission->amount,
            'bill_date' => $submission->bill_date,
            'reference' => $submission->reference,
            'file_path' => $submission->file_path,
            'status' => 'unpaid',
        ]);

        $submission->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'vendor_bill_id' => $bill->id,
        ]);

        return $this->redirectWithFlash('/app/vendor-bill-submissions', 'success', t('user.vendor_bills.submission_approved'));
    }

    public function rejectSubmission(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('supplier_portal')) {
            return $redirect;
        }
        $submission = $this->findOwnedSubmission($id);
        if (!$submission->isPending()) {
            return $this->redirectWithFlash('/app/vendor-bill-submissions', 'error', t('user.vendor_bills.submission_already_reviewed'));
        }

        $submission->update([
            'status' => 'rejected',
            'rejection_reason' => trim((string) $request->input('rejection_reason', '')) ?: null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $this->redirectWithFlash('/app/vendor-bill-submissions', 'success', t('user.vendor_bills.submission_rejected'));
    }

    private function findOwnedSubmission(int $id): VendorBillSubmission
    {
        $submission = VendorBillSubmission::find($id);
        abort_if(!$submission || $submission->company_id !== Auth::user()->company_id, 404, 'Vendor bill submission not found.');
        return $submission;
    }

    private function findOwned(int $id): VendorBill
    {
        $bill = VendorBill::find($id);
        abort_if(!$bill || $bill->company_id !== Auth::user()->company_id, 404, 'Vendor bill not found.');
        return $bill;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }

    /** Only returns the PO if it belongs to this project — never trust a raw purchase_order_id from the request. */
    private function ownedPurchaseOrder(?int $id, int $projectId): ?PurchaseOrder
    {
        if (!$id) {
            return null;
        }
        $purchaseOrder = PurchaseOrder::find($id);
        return ($purchaseOrder && $purchaseOrder->project_id === $projectId) ? $purchaseOrder : null;
    }
}
