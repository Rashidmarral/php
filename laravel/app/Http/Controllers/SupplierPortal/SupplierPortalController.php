<?php

namespace App\Http\Controllers\SupplierPortal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\VendorBill;
use App\Models\VendorBillSubmission;
use App\Support\Notifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The authenticated, ongoing supplier/vendor self-service portal (as opposed to the
 * one-off, token-link RFQ bid flow in Site\ShareController::rfqBid()/submitRfqBid()).
 * Every action here is scoped strictly to Auth::guard('supplier')->id() — never trusts a
 * route-param id without an ownership check — mirroring Portal\PortalController's own
 * abort_if() pattern exactly.
 */
class SupplierPortalController extends Controller
{
    private const ALLOWED_RECEIPT_EXT = ['pdf', 'jpg', 'jpeg', 'png'];

    public function dashboard(): View
    {
        $supplier = Auth::guard('supplier')->user();
        $company = Company::find($supplier->company_id);

        return view('supplier-portal.dashboard', [
            'pageTitle' => 'My Purchase Orders & Subcontracts',
            'supplier' => $supplier,
            'company' => $company,
            // A draft PO is still just an internal plan — never shown to the vendor, same
            // reasoning as PurchaseOrderController::sendWhatsApp()'s own draft check.
            'purchaseOrders' => PurchaseOrder::where('supplier_id', $supplier->id)->where('status', '!=', 'draft')->orderByDesc('created_at')->get(),
            'subcontracts' => Subcontract::where('supplier_id', $supplier->id)->where('status', 'active')->orderByDesc('created_at')->get(),
            // Every status (pending/approved/rejected) — the supplier's own full submission history.
            'submissions' => VendorBillSubmission::where('supplier_id', $supplier->id)->orderByDesc('created_at')->get(),
        ]);
    }

    public function purchaseOrder(int $id): View
    {
        $supplier = Auth::guard('supplier')->user();
        $purchaseOrder = $this->ownedPurchaseOrder($id, $supplier->id);

        return view('supplier-portal.purchase-order', [
            'pageTitle' => $purchaseOrder->po_number,
            'supplier' => $supplier,
            'purchaseOrder' => $purchaseOrder,
            'items' => $purchaseOrder->items()->orderBy('id')->get(),
        ]);
    }

    /** Idempotent: acknowledging an already-acknowledged PO is a no-op, not an error — see PurchaseOrder::acknowledge(). */
    public function acknowledge(int $id): RedirectResponse
    {
        $supplier = Auth::guard('supplier')->user();
        $purchaseOrder = $this->ownedPurchaseOrder($id, $supplier->id);
        $purchaseOrder->acknowledge();

        return $this->redirectWithFlash('/supplier-portal/purchase-orders/' . $purchaseOrder->id, 'success', t('supplier_portal.po_acknowledged'));
    }

    public function subcontract(int $id): View
    {
        $supplier = Auth::guard('supplier')->user();
        $subcontract = $this->ownedSubcontract($id, $supplier->id);

        return view('supplier-portal.subcontract', [
            'pageTitle' => $subcontract->title,
            'supplier' => $supplier,
            'subcontract' => $subcontract,
            'payments' => SubcontractPayment::where('subcontract_id', $subcontract->id)->orderByDesc('payment_number')->get(),
            'cumulativePaid' => $subcontract->cumulativePaid(),
            'retentionHeld' => $subcontract->retentionHeld(),
        ]);
    }

    public function createBillSubmission(): View
    {
        $supplier = Auth::guard('supplier')->user();

        return view('supplier-portal.bill-submission-form', [
            'pageTitle' => 'Submit a Bill',
            'supplier' => $supplier,
            // Only the supplier's OWN issued/received POs and active subcontracts are
            // offered — this is the security boundary: the project/company context is
            // always derived from a relationship the supplier actually has, never typed in
            // free, so a submission can never be fabricated against an unrelated project.
            'purchaseOrders' => PurchaseOrder::where('supplier_id', $supplier->id)->whereIn('status', ['issued', 'received'])->orderByDesc('created_at')->get(),
            'subcontracts' => Subcontract::where('supplier_id', $supplier->id)->where('status', 'active')->orderByDesc('created_at')->get(),
            'categories' => VendorBill::CATEGORIES,
        ]);
    }

    public function storeBillSubmission(Request $request): RedirectResponse
    {
        $supplier = Auth::guard('supplier')->user();

        $description = trim((string) $request->input('description'));
        $amount = (float) $request->input('amount', 0);
        if ($description === '' || $amount <= 0) {
            return $this->redirectWithFlash('/supplier-portal/bills/create', 'error', t('supplier_portal.description_amount_required'));
        }

        // Exactly one of purchase_order_id/subcontract_id must resolve to a PO/subcontract
        // this supplier actually holds — a tampered/unrelated id is silently dropped to null
        // rather than trusted, never accepted as-is.
        $purchaseOrder = $this->ownedIssuedOrReceivedPurchaseOrder($request->input('purchase_order_id') ?: null, $supplier->id);
        $subcontract = $this->ownedActiveSubcontract($request->input('subcontract_id') ?: null, $supplier->id);
        if (!$purchaseOrder && !$subcontract) {
            return $this->redirectWithFlash('/supplier-portal/bills/create', 'error', t('supplier_portal.po_or_subcontract_required'));
        }

        // Project/company context is always derived from the owned PO/subcontract above —
        // never from a raw project_id/company_id in the request.
        $projectId = $purchaseOrder->project_id ?? $subcontract->project_id;
        $companyId = $purchaseOrder->company_id ?? $subcontract->company_id;

        $filePath = null;
        $file = $request->file('receipt');
        if ($file && $file->isValid()) {
            $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_RECEIPT_EXT, true)) {
                return $this->redirectWithFlash('/supplier-portal/bills/create', 'error', t('supplier_portal.receipt_type_not_allowed', ['allowed' => implode(', ', self::ALLOWED_RECEIPT_EXT)]));
            }
            $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
            $file->move(public_path("uploads/vendor-bill-submissions/{$companyId}"), $storedName);
            $filePath = "/uploads/vendor-bill-submissions/{$companyId}/{$storedName}";
        }

        $submission = VendorBillSubmission::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder?->id,
            'subcontract_id' => $subcontract?->id,
            'category' => in_array($request->input('category'), array_keys(VendorBill::CATEGORIES), true) ? $request->input('category') : 'material',
            'description' => $description,
            'amount' => $amount,
            'bill_date' => $request->input('bill_date') ?: now()->format('Y-m-d'),
            'reference' => trim((string) $request->input('reference', '')) ?: null,
            'file_path' => $filePath,
            'status' => 'pending',
        ]);

        Notifications::vendorBillSubmitted($submission->id);

        return $this->redirectWithFlash('/supplier-portal', 'success', t('supplier_portal.bill_submitted'));
    }

    private function ownedPurchaseOrder(int $id, int $supplierId): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::find($id);
        abort_if(!$purchaseOrder || $purchaseOrder->supplier_id !== $supplierId || $purchaseOrder->status === 'draft', 404, 'Not found.');
        return $purchaseOrder;
    }

    private function ownedSubcontract(int $id, int $supplierId): Subcontract
    {
        $subcontract = Subcontract::find($id);
        abort_if(!$subcontract || $subcontract->supplier_id !== $supplierId, 404, 'Not found.');
        return $subcontract;
    }

    /** Only returns the PO if it belongs to this supplier AND is issued/received (never draft) — never trust a raw purchase_order_id from the request. */
    private function ownedIssuedOrReceivedPurchaseOrder(?int $id, int $supplierId): ?PurchaseOrder
    {
        if (!$id) {
            return null;
        }
        $purchaseOrder = PurchaseOrder::find($id);
        return ($purchaseOrder && $purchaseOrder->supplier_id === $supplierId && in_array($purchaseOrder->status, ['issued', 'received'], true)) ? $purchaseOrder : null;
    }

    /** Only returns the subcontract if it belongs to this supplier AND is active — never trust a raw subcontract_id from the request. */
    private function ownedActiveSubcontract(?int $id, int $supplierId): ?Subcontract
    {
        if (!$id) {
            return null;
        }
        $subcontract = Subcontract::find($id);
        return ($subcontract && $subcontract->supplier_id === $supplierId && $subcontract->status === 'active') ? $subcontract : null;
    }
}
