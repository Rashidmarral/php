<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChangeOrder;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'A title and a non-zero amount are required (use a negative amount for a scope reduction).');
        }

        ChangeOrder::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'title' => $title,
            'title_ar' => trim((string) $request->input('title_ar', '')),
            'description' => $request->input('description', ''),
            'description_ar' => $request->input('description_ar', ''),
            'amount' => $amount,
            'status' => 'pending',
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', 'Change order added.');
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

        return $this->redirectWithFlash('/app/projects/' . $changeOrder->project_id, 'success', 'Change order ' . $status . '.');
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
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', 'Change order removed.');
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
