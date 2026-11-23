<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TimesheetEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Project-level labor hours (Task #58) — ties actual hours worked to a project's cost,
 * feeding ReportController::costVariance()'s 'labor' actual-cost bucket as an ADDITIONAL
 * term alongside the existing VendorBill-based labor sum (see that method's own docblock).
 * This is unrelated to TeamController's WPS payroll export, which tracks MONTHLY salary,
 * not project hours.
 */
class TimesheetController extends Controller
{
    public function index(Request $request, int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('timesheets')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $from = $request->query('from');
        $to = $request->query('to');

        $query = TimesheetEntry::where('project_id', $project->id);
        if ($from) {
            $query->where('work_date', '>=', $from);
        }
        if ($to) {
            $query->where('work_date', '<=', $to);
        }
        $entries = $query->orderByDesc('work_date')->orderByDesc('id')->get();

        $workers = User::whereIn('id', $entries->pluck('user_id')->unique())->get()->keyBy('id');
        $rows = $entries->map(fn (TimesheetEntry $e) => [
            ...$e->toArray(),
            'worker_name' => $workers->get($e->user_id)->name ?? '—',
        ])->all();

        return view('app.projects.timesheets', [
            'project' => $project->toArray(),
            'entries' => $rows,
            'totalHours' => (float) $entries->sum('hours'),
            'totalCost' => (float) $entries->whereNotNull('cost')->sum('cost'),
            'from' => $from,
            'to' => $to,
            'teamMembers' => User::where('company_id', $project->company_id)->orderBy('name')->get(['id', 'name', 'hourly_rate'])->toArray(),
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('timesheets')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $redirectPath = '/app/projects/' . $project->id;

        $worker = $this->ownedUser($request->input('user_id'), $project->company_id);
        if (!$worker) {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.timesheets.worker_required'));
        }

        $hours = (string) $request->input('hours', '');
        if ($hours === '' || !is_numeric($hours) || (float) $hours <= 0 || (float) $hours > 24) {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.timesheets.hours_invalid'));
        }

        $workDate = trim((string) $request->input('work_date', ''));
        if ($workDate === '') {
            $workDate = now()->format('Y-m-d');
        }
        if ($workDate > now()->format('Y-m-d')) {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.timesheets.future_date_invalid'));
        }

        // Snapshot the member's hourly_rate NOW — never re-read live later, same precedent
        // SubcontractPayment::retention_percent established. A null rate still records hours
        // (hours worked is real and trackable) but gets a null cost rather than a wrong 0.00.
        $rateSnapshot = $worker->hourly_rate;
        $cost = $rateSnapshot !== null ? round((float) $hours * (float) $rateSnapshot, 2) : null;

        TimesheetEntry::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'user_id' => $worker->id,
            'work_date' => $workDate,
            'hours' => $hours,
            'hourly_rate_snapshot' => $rateSnapshot,
            'cost' => $cost,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'logged_by' => Auth::id(),
        ]);

        return $this->redirectWithFlash($redirectPath, 'success', t('user.timesheets.added'));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('timesheets')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $entry = $this->findOwned($id);
        $projectId = $entry->project_id;
        $entry->delete();

        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.timesheets.removed'));
    }

    /** Only logs hours against a user within the acting company — never let a cross-tenant id slip into user_id. */
    private function ownedUser(mixed $id, int $companyId): ?User
    {
        if (!$id) {
            return null;
        }
        $user = User::find((int) $id);
        return ($user && $user->company_id === $companyId) ? $user : null;
    }

    private function findOwned(int $id): TimesheetEntry
    {
        $entry = TimesheetEntry::find($id);
        abort_if(!$entry || $entry->company_id !== Auth::user()->company_id, 404, 'Timesheet entry not found.');
        return $entry;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
