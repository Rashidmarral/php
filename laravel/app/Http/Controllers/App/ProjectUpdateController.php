<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectUpdate;
use App\Models\ScheduleTask;
use App\Models\Setting;
use App\Models\SiteLog;
use App\Support\AiProjectUpdateDrafter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Project Updates" — a client-visible progress feed a PM posts periodically (see
 * ProjectUpdate's own docblock), the real competitor capability this mirrors being
 * Buildertrend's own client-facing progress updates. A per-project sub-resource
 * controller, same shape as SiteLogController/ProjectPhotoController: {projectId}-scoped
 * routes for listing/creating, and plain {id}-scoped routes (mirroring
 * ProjectPhotoController's own /app/project-photos/{id}/delete convention) for actions on
 * one existing update.
 *
 * draftWithAi() is the one AI-assisted action: it gathers the project's own recent
 * activity and asks AiProjectUpdateDrafter for a first-pass draft, which the PM always
 * reviews/edits in the form before ever saving it — see that class's own docblock for the
 * "AI proposes, a human explicitly confirms" rule this follows.
 */
class ProjectUpdateController extends Controller
{
    public function index(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('client_portal')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $updates = ProjectUpdate::where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->get();

        return view('app.project-updates.index', [
            'project' => $project->toArray(),
            'updates' => $updates->toArray(),
            'aiConfigured' => AiProjectUpdateDrafter::isConfigured(),
        ]);
    }

    public function create(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('client_portal')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        return view('app.project-updates.form', [
            'project' => $project->toArray(),
            'aiConfigured' => AiProjectUpdateDrafter::isConfigured(),
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('client_portal')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/updates/create', 'error', t('user.project_updates.body_required'));
        }

        $publish = $request->input('submit_action') === 'publish';

        ProjectUpdate::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'title' => trim((string) $request->input('title', '')) ?: null,
            'body' => $body,
            'source' => 'manual',
            'created_by' => Auth::id(),
            'published_at' => $publish ? now() : null,
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id . '/updates', 'success', t($publish ? 'user.project_updates.published' : 'user.project_updates.saved_as_draft'));
    }

    /**
     * Gathers this project's own recent activity (last 14 days of site logs, last 5 photo
     * captions, schedule tasks grouped by status) and asks AiProjectUpdateDrafter for a
     * first-pass draft. Returns {ok:true, body:string} on success — the caller's form fills
     * its body textarea with it, nothing is saved here. Returns a clean "nothing to
     * summarize yet" error (never calling the AI with an empty context, which would only
     * invite a hallucinated generic update) when the project has no activity at all.
     */
    public function draftWithAi(int $projectId): JsonResponse
    {
        if ($this->requireFeature('client_portal')) {
            return response()->json(['ok' => false, 'error' => t('common.feature_not_in_plan', ['feature' => t('feature.client_portal')])], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => t('common.no_permission', ['role' => auth()->user()->roleLabel()])], 403);
        }
        $project = $this->findOwnedProject($projectId);

        if (!AiProjectUpdateDrafter::isConfigured()) {
            return response()->json(['ok' => false, 'error' => t('user.project_updates.ai_not_configured')], 422);
        }

        $recentSiteLogs = SiteLog::where('project_id', $project->id)
            ->where('log_date', '>=', now()->subDays(14)->format('Y-m-d'))
            ->orderByDesc('log_date')
            ->limit(10)
            ->get();

        $recentPhotoCaptions = ProjectPhoto::where('project_id', $project->id)
            ->whereNotNull('caption')
            ->where('caption', '!=', '')
            ->orderByDesc('taken_on')
            ->orderByDesc('created_at')
            ->limit(5)
            ->pluck('caption');

        $tasks = ScheduleTask::where('project_id', $project->id)->orderBy('start_date')->get();
        $tasksDone = $tasks->where('status', 'done')->pluck('title')->take(10);
        $tasksInProgress = $tasks->where('status', 'in_progress')->pluck('title')->take(10);
        $tasksUpcoming = $tasks->where('status', 'pending')->pluck('title')->take(10);

        $hasAnyActivity = $recentSiteLogs->isNotEmpty()
            || $recentPhotoCaptions->isNotEmpty()
            || $tasksDone->isNotEmpty()
            || $tasksInProgress->isNotEmpty()
            || $tasksUpcoming->isNotEmpty();

        if (!$hasAnyActivity) {
            return response()->json(['ok' => false, 'error' => t('user.project_updates.nothing_to_summarize')], 422);
        }

        $context = [
            'project_name' => $project->name,
            'recent_site_logs' => $recentSiteLogs->map(fn (SiteLog $log) => [
                'date' => (string) $log->log_date,
                'weather' => $log->weather,
                'workers' => $log->workers_on_site,
                'notes' => $log->notes,
            ])->all(),
            'recent_photo_captions' => $recentPhotoCaptions->values()->all(),
            'tasks_done' => $tasksDone->values()->all(),
            'tasks_in_progress' => $tasksInProgress->values()->all(),
            'tasks_upcoming' => $tasksUpcoming->values()->all(),
        ];

        $draft = AiProjectUpdateDrafter::draft($context);
        if ($draft === null) {
            $lastError = Setting::get('ai_last_error') ?: 'The AI provider did not return a usable response.';
            return response()->json(['ok' => false, 'error' => t('user.project_updates.ai_failed', ['reason' => $lastError])], 422);
        }

        return response()->json(['ok' => true, 'body' => $draft]);
    }

    public function publish(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('client_portal')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $update = $this->findOwned($id);
        $update->update(['published_at' => now()]);

        return $this->redirectWithFlash('/app/projects/' . $update->project_id . '/updates', 'success', t('user.project_updates.published'));
    }

    public function unpublish(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('client_portal')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $update = $this->findOwned($id);
        $update->update(['published_at' => null]);

        return $this->redirectWithFlash('/app/projects/' . $update->project_id . '/updates', 'success', t('user.project_updates.unpublished'));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('client_portal')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $update = $this->findOwned($id);
        $projectId = $update->project_id;
        $update->delete();

        return $this->redirectWithFlash('/app/projects/' . $projectId . '/updates', 'success', t('user.project_updates.deleted'));
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }

    private function findOwned(int $id): ProjectUpdate
    {
        $update = ProjectUpdate::find($id);
        abort_if(!$update || $update->company_id !== Auth::user()->company_id, 404, 'Project update not found.');
        return $update;
    }
}
