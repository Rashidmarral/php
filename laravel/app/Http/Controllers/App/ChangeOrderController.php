<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChangeOrder;
use App\Models\ChangeOrderItem;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChangeOrderController extends Controller
{
    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('change_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $title = trim((string) $request->input('title'));
        $amount = (float) $request->input('amount', 0);
        if ($title === '' || $amount == 0.0) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.change_orders.title_and_amount_required'));
        }

        $timeImpactDays = $request->input('time_impact_days');

        $changeOrder = ChangeOrder::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'title' => $title,
            'title_ar' => trim((string) $request->input('title_ar', '')),
            'description' => $request->input('description', ''),
            'description_ar' => $request->input('description_ar', ''),
            'amount' => $amount,
            'time_impact_days' => ($timeImpactDays !== null && $timeImpactDays !== '') ? (int) $timeImpactDays : null,
            'status' => 'pending',
        ]);
        AuditLog::recordForCompany(Auth::user(), 'change_order_create', 'change_order', $changeOrder->id, "Change order \"{$changeOrder->title}\" added — " . number_format((float) $changeOrder->amount, 2) . ' SAR');

        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', t('user.change_orders.added'));
    }

    /**
     * Detail page: line items (opt-in — see ChangeOrder::syncAmountFromItems()),
     * the lightweight time-impact note, and the client e-signature share link
     * (generated lazily here the same way EstimateController::show()/
     * PaymentCertificateController::show() generate their own share_token on
     * first view).
     */
    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('change_orders')) {
            return $redirect;
        }
        $changeOrder = $this->findOwned($id);
        $project = Project::find($changeOrder->project_id);
        $items = ChangeOrderItem::where('change_order_id', $changeOrder->id)->orderBy('id')->get()->toArray();

        if (empty($changeOrder->share_token)) {
            $changeOrder->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/co/' . $changeOrder->share_token;

        return view('app.change-orders.show', [
            'changeOrder' => $changeOrder->toArray(),
            'project' => $project?->toArray(),
            'items' => $items,
            'shareUrl' => $shareUrl,
        ]);
    }

    public function addItem(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('change_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $changeOrder = $this->findOwned($id);

        $description = trim((string) $request->input('description'));
        if ($description === '') {
            return $this->redirectWithFlash('/app/change-orders/' . $changeOrder->id, 'error', t('user.change_orders.item_description_required'));
        }
        $qty = (float) ($request->input('qty') ?: 1);
        $unitPrice = (float) ($request->input('unit_price') ?: 0);

        ChangeOrderItem::create([
            'change_order_id' => $changeOrder->id,
            'description' => $description,
            'qty' => $qty,
            'unit' => trim((string) $request->input('unit', '')) ?: null,
            'unit_price' => $unitPrice,
            'total' => $qty * $unitPrice,
        ]);
        $changeOrder->syncAmountFromItems();

        $this->flash('success', t('user.change_orders.item_added'));
        return redirect('/app/change-orders/' . $changeOrder->id);
    }

    public function removeItem(int $id, int $itemId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('change_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $changeOrder = $this->findOwned($id);
        $item = ChangeOrderItem::find($itemId);
        abort_if(!$item || $item->change_order_id !== $changeOrder->id, 404, 'Change order item not found.');
        $item->delete();
        $changeOrder->syncAmountFromItems();

        $this->flash('success', t('user.change_orders.item_removed'));
        return redirect('/app/change-orders/' . $changeOrder->id);
    }

    public function updateTimeImpact(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('change_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $changeOrder = $this->findOwned($id);
        $days = $request->input('time_impact_days');

        $changeOrder->update(['time_impact_days' => ($days !== null && $days !== '') ? (int) $days : null]);
        $this->flash('success', t('user.change_orders.time_impact_updated'));
        return redirect('/app/change-orders/' . $changeOrder->id);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('change_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $changeOrder = $this->findOwned($id);
        $status = (string) $request->input('status');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return redirect('/app/projects/' . $changeOrder->project_id);
        }

        $changeOrder->update([
            'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);
        AuditLog::recordForCompany(Auth::user(), 'change_order_status_change', 'change_order', $changeOrder->id, "Change order \"{$changeOrder->title}\" → {$status} — " . number_format((float) $changeOrder->amount, 2) . ' SAR');

        return $this->redirectWithFlash('/app/projects/' . $changeOrder->project_id, 'success', t('user.change_orders.status_changed', ['status' => $status]));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('change_orders')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $changeOrder = $this->findOwned($id);
        $projectId = $changeOrder->project_id;
        $changeOrder->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.change_orders.removed'));
    }

    private function findOwned(int $id): ChangeOrder
    {
        $changeOrder = ChangeOrder::find($id);
        abort_if(!$changeOrder || $changeOrder->company_id !== Auth::user()->company_id, 404, 'Change order not found.');
        return $changeOrder;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
