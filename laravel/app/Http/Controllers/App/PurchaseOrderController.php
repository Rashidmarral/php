<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Setting;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    /** Status transitions allowed from updateStatus() — no un-cancelling, and 'received' is otherwise only set automatically once a vendor bill lands against the PO. */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['issued', 'cancelled'],
        'issued' => ['received', 'cancelled'],
        'received' => [],
        'cancelled' => [],
    ];

    public function create(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('purchase_orders')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $companyId = Auth::user()->company_id;

        return view('app.purchase-orders.form', [
            'project' => $project->toArray(),
            'suppliers' => Supplier::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'nextNumber' => 'PO-' . (1000 + PurchaseOrder::where('company_id', $companyId)->count() + 1),
            'vatRate' => (float) Setting::get('vat_rate', '15'),
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('purchase_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $companyId = Auth::user()->company_id;

        $descriptions = $request->input('item_description', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);

        $subtotal = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1);
            $price = (float) ($prices[$i] ?? 0);
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;
            $items[] = ['description' => $desc, 'qty' => $qty, 'unit_price' => $price, 'total' => $lineTotal];
        }

        if (empty($items)) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'A purchase order needs at least one line item.');
        }

        $applyVat = (bool) $request->input('apply_vat', true);
        $vatRate = $applyVat ? (float) Setting::get('vat_rate', '15') : 0;
        $vatAmount = $subtotal * $vatRate / 100;
        $total = $subtotal + $vatAmount;

        $status = $request->input('status') === 'issued' ? 'issued' : 'draft';

        $purchaseOrder = PurchaseOrder::create([
            'company_id' => $companyId,
            'project_id' => $project->id,
            'supplier_id' => $this->ownedSupplier($request->input('supplier_id') ?: null, $companyId)?->id,
            'po_number' => 'PO-' . (1000 + PurchaseOrder::where('company_id', $companyId)->count() + 1),
            'status' => $status,
            'issue_date' => $request->input('issue_date') ?: now()->format('Y-m-d'),
            'expected_delivery_date' => $request->input('expected_delivery_date') ?: null,
            'notes' => trim((string) $request->input('notes', '')),
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
        ]);

        foreach ($items as $item) {
            PurchaseOrderItem::create(['purchase_order_id' => $purchaseOrder->id, ...$item]);
        }

        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', 'Purchase order ' . ($status === 'issued' ? 'issued' : 'saved as draft') . '.');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('purchase_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $purchaseOrder = $this->findOwned($id);
        $status = (string) $request->input('status');

        if (!in_array($status, self::ALLOWED_TRANSITIONS[$purchaseOrder->status] ?? [], true)) {
            return $this->redirectWithFlash('/app/projects/' . $purchaseOrder->project_id, 'error', 'That status change isn\'t allowed from ' . $purchaseOrder->status . '.');
        }

        $purchaseOrder->update(['status' => $status]);

        return $this->redirectWithFlash('/app/projects/' . $purchaseOrder->project_id, 'success', 'Purchase order ' . $status . '.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('purchase_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $purchaseOrder = $this->findOwned($id);
        if (!$purchaseOrder->isEditable()) {
            return $this->redirectWithFlash('/app/projects/' . $purchaseOrder->project_id, 'error', 'Only a draft purchase order can be deleted — this one has already been issued to the supplier.');
        }
        $projectId = $purchaseOrder->project_id;
        PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->delete();
        $purchaseOrder->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', 'Purchase order deleted.');
    }

    public function pdf(Request $request, int $id): Response
    {
        $purchaseOrder = $this->findOwned($id);
        $items = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->orderBy('id')->get();
        $supplier = $purchaseOrder->supplier_id ? $this->ownedSupplier($purchaseOrder->supplier_id, $purchaseOrder->company_id) : null;
        $company = Company::find($purchaseOrder->company_id);
        $lang = $request->input('lang') === 'ar' ? 'ar' : app()->getLocale();

        return $this->streamPdf([
            'template' => 'modern',
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'أمر شراء' : 'Purchase Order',
            'docNumber' => $purchaseOrder->po_number,
            'docDate' => $purchaseOrder->created_at,
            'validUntil' => $purchaseOrder->expected_delivery_date,
            'status' => ucfirst($purchaseOrder->status),
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, ($company->vat_number ?? null) ? 'VAT: ' . $company->vat_number : null, ($company->cr_number ?? null) ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'billTo' => $supplier ? ['name' => ($lang === 'ar' && !empty($supplier->name_ar)) ? $supplier->name_ar : $supplier->name, 'meta' => array_filter([$supplier->email ?? null, $supplier->phone ?? null, $supplier->address ?? null])] : null,
            'items' => $items->map(fn ($i) => ['description' => $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'total' => $i->total])->all(),
            'subtotal' => (float) $purchaseOrder->subtotal,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => (float) Setting::get('vat_rate', '15'),
            'vatAmount' => (float) $purchaseOrder->vat_amount,
            'total' => (float) $purchaseOrder->total,
            'qrCode' => null,
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة ' . Setting::siteName() : 'Generated by ' . Setting::siteName(),
        ], $purchaseOrder->po_number . '.pdf');
    }

    private function findOwned(int $id): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::find($id);
        abort_if(!$purchaseOrder || $purchaseOrder->company_id !== Auth::user()->company_id, 404, 'Purchase order not found.');
        return $purchaseOrder;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
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
}
