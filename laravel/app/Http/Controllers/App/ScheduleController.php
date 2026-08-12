<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ScheduleTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(): View
    {
        $companyId = Auth::user()->company_id;
        $tasks = DB::table('schedule_tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->where('t.company_id', $companyId)
            ->orderBy('t.start_date')
            ->select('t.*', 'p.name as project_name', 'p.name_ar as project_name_ar')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.schedule.index', [
            'tasks' => $tasks,
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $title = trim((string) $request->input('title'));
        $projectId = (int) $request->input('project_id');

        if ($title === '' || !$projectId) {
            return $this->redirectWithFlash('/app/schedule', 'error', 'Task title and project are required.');
        }

        $project = Project::find($projectId);
        abort_if(!$project || $project->company_id !== $companyId, 404, 'Project not found.');

        ScheduleTask::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'title' => $title,
            'title_ar' => trim((string) $request->input('title_ar', '')),
            'start_date' => $request->input('start_date') ?: null,
            'end_date' => $request->input('end_date') ?: null,
            'status' => 'pending',
        ]);

        $this->flash('success', 'Task added to schedule.');
        return redirect('/app/schedule');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $task = $this->findOwned($id);
        $status = (string) $request->input('status', 'pending');
        if (in_array($status, ['pending', 'in_progress', 'done'], true)) {
            $task->update(['status' => $status]);
        }
        return redirect('/app/schedule');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $task = $this->findOwned($id);
        $task->delete();
        $this->flash('success', 'Task removed.');
        return redirect('/app/schedule');
    }

    private function findOwned(int $id): ScheduleTask
    {
        $task = ScheduleTask::find($id);
        abort_if(!$task || $task->company_id !== Auth::user()->company_id, 404, 'Task not found.');
        return $task;
    }
}
