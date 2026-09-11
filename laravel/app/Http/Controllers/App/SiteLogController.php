<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SiteLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * A one-thumb, phone-first daily log a supervisor fills in from the site — a dated free-text
 * note plus weather/headcount, not a rigid single-record-per-day form. Multiple entries per
 * day are allowed on purpose (a supervisor may reasonably add a follow-up note later the same
 * day), same precedent as change orders allowing multiple per project.
 */
class SiteLogController extends Controller
{
    public function index(int $projectId): View
    {
        if ($redirect = $this->requireFeature('site_logs')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $logs = SiteLog::where('project_id', $project->id)
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->get();

        return view('app.projects.site-log', [
            'project' => $project->toArray(),
            'logs' => $logs->toArray(),
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('site_logs')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $notes = trim((string) $request->input('notes', ''));
        $weather = trim((string) $request->input('weather', ''));
        if ($notes === '' && $weather === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/site-log', 'error', 'Add a note or weather condition to log something.');
        }

        SiteLog::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'logged_by' => Auth::id(),
            'log_date' => $request->input('log_date') ?: now()->format('Y-m-d'),
            'weather' => $weather !== '' ? $weather : null,
            'workers_on_site' => $request->filled('workers_on_site') ? (int) $request->input('workers_on_site') : null,
            'notes' => $notes,
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id . '/site-log', 'success', 'Site log entry added.');
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
