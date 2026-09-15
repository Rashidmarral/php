<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BoqItem;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * A project's Bill of Quantities — the fixed contract line list (qty x
 * contract rate) that Payment Certificates (see PaymentCertificateController)
 * claim cumulative progress against. Once a project has any payment
 * certificate (draft or certified), editing an existing line's qty/rate is
 * hard-blocked — a certificate has already snapshotted its own numbers
 * against the BOQ as it stood, so silently changing contract value after
 * billing against it started would corrupt the certified history. New
 * lines (added scope) can still be added freely.
 */
class BoqController extends Controller
{
    public function index(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('payment_certificates')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $items = BoqItem::where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get();

        return view('app.projects.boq', [
            'project' => $project->toArray(),
            'items' => $items->toArray(),
            'contractValue' => (float) $items->sum('total'),
            'locked' => $project->hasAnyPaymentCertificate(),
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

        $description = trim((string) $request->input('description'));
        if ($description === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/boq', 'error', 'A BOQ line needs a description.');
        }

        $qty = (float) $request->input('qty', 0);
        $unitPrice = (float) $request->input('unit_price', 0);
        $maxSortOrder = (int) BoqItem::where('project_id', $project->id)->max('sort_order');

        BoqItem::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'section_title' => trim((string) $request->input('section_title', '')) ?: null,
            'section_title_ar' => trim((string) $request->input('section_title_ar', '')) ?: null,
            'item_number' => trim((string) $request->input('item_number', '')) ?: null,
            'description' => $description,
            'description_ar' => trim((string) $request->input('description_ar', '')) ?: null,
            'uom' => trim((string) $request->input('uom', '')),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'total' => round($qty * $unitPrice, 2),
            'sort_order' => $maxSortOrder + 1,
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id . '/boq', 'success', 'BOQ line added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('payment_certificates')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $item = $this->findOwned($id);
        $project = $this->findOwnedProject($item->project_id);

        if ($project->hasAnyPaymentCertificate()) {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/boq', 'error', 'This BOQ line can no longer be edited: at least one payment certificate already exists for this project.');
        }

        $description = trim((string) $request->input('description'));
        if ($description === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/boq', 'error', 'A BOQ line needs a description.');
        }

        $qty = (float) $request->input('qty', 0);
        $unitPrice = (float) $request->input('unit_price', 0);

        $item->update([
            'section_title' => trim((string) $request->input('section_title', '')) ?: null,
            'section_title_ar' => trim((string) $request->input('section_title_ar', '')) ?: null,
            'item_number' => trim((string) $request->input('item_number', '')) ?: null,
            'description' => $description,
            'description_ar' => trim((string) $request->input('description_ar', '')) ?: null,
            'uom' => trim((string) $request->input('uom', '')),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'total' => round($qty * $unitPrice, 2),
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id . '/boq', 'success', 'BOQ line updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('payment_certificates')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $item = $this->findOwned($id);
        $project = $this->findOwnedProject($item->project_id);

        if ($project->hasAnyPaymentCertificate()) {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/boq', 'error', 'This BOQ line cannot be removed once a payment certificate exists for this project.');
        }

        $item->delete();
        return $this->redirectWithFlash('/app/projects/' . $project->id . '/boq', 'success', 'BOQ line removed.');
    }

    private function findOwned(int $id): BoqItem
    {
        $item = BoqItem::find($id);
        abort_if(!$item || $item->company_id !== Auth::user()->company_id, 404, 'BOQ line not found.');
        return $item;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
