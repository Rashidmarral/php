<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ExtensionOfTimeRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Extension of Time (EOT) requests, the counterpart to a project's Liquidated Damages
 * exposure (see Project::ldExposure()/effectiveCompletionDate()) — only an APPROVED
 * request ever shifts the effective completion date, so pending/rejected ones are inert
 * until reviewed. Submitting a request is ordinary 'write' work (any estimator/accountant
 * can flag a delay); deciding it is the same weight of contractual sign-off as approving
 * an estimate, so it reuses that exact Gate ('approve_documents') rather than a new one.
 */
class ExtensionOfTimeController extends Controller
{
    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('ld_eot_tracking')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $requestedDays = (int) $request->input('requested_days', 0);
        $reason = trim((string) $request->input('reason', ''));
        if ($requestedDays < 1) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'Requested days must be a positive number.');
        }
        if ($reason === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'A reason is required for an Extension of Time request.');
        }

        ExtensionOfTimeRequest::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'requested_days' => $requestedDays,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => Auth::id(),
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', 'Extension of Time request submitted.');
    }

    public function approve(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('ld_eot_tracking')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $eot = $this->findOwned($id);
        if ($eot->status !== 'pending') {
            return $this->redirectWithFlash('/app/projects/' . $eot->project_id, 'error', 'This request is not awaiting approval.');
        }
        $eot->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return $this->redirectWithFlash('/app/projects/' . $eot->project_id, 'success', 'Extension of Time approved.');
    }

    public function reject(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('ld_eot_tracking')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $eot = $this->findOwned($id);
        if ($eot->status !== 'pending') {
            return $this->redirectWithFlash('/app/projects/' . $eot->project_id, 'error', 'This request is not awaiting approval.');
        }
        $eot->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return $this->redirectWithFlash('/app/projects/' . $eot->project_id, 'success', 'Extension of Time rejected.');
    }

    /** Withdraw a request that hasn't been decided yet — the submitter or any other write-able teammate/admin can do this, same as they could submit one. */
    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('ld_eot_tracking')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $eot = $this->findOwned($id);
        if ($eot->status !== 'pending') {
            return $this->redirectWithFlash('/app/projects/' . $eot->project_id, 'error', 'Only a pending request can be withdrawn.');
        }
        $projectId = $eot->project_id;
        $eot->delete();

        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', 'Extension of Time request withdrawn.');
    }

    private function findOwned(int $id): ExtensionOfTimeRequest
    {
        $eot = ExtensionOfTimeRequest::find($id);
        abort_if(!$eot || $eot->company_id !== Auth::user()->company_id, 404, 'Extension of Time request not found.');
        return $eot;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
