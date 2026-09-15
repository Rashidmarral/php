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
            'gantt' => $this->buildGantt($tasks),
        ]);
    }

    /** Buckets tasks by project and computes a shared day-by-day timeline for the Gantt view. */
    private function buildGantt(array $tasks): array
    {
        $dated = array_filter($tasks, fn ($t) => !empty($t['start_date']) && !empty($t['end_date']));
        if (empty($dated)) {
            $rangeStart = now()->startOfWeek();
            $rangeEnd = now()->addWeeks(3)->endOfWeek();
        } else {
            $rangeStart = min(array_map(fn ($t) => \Carbon\Carbon::parse($t['start_date']), $dated))->copy()->subDays(2);
            $rangeEnd = max(array_map(fn ($t) => \Carbon\Carbon::parse($t['end_date']), $dated))->copy()->addDays(2);
        }
        $rangeEnd = $rangeStart->diffInDays($rangeEnd) > 120 ? $rangeStart->copy()->addDays(120) : $rangeEnd;

        $days = [];
        for ($d = $rangeStart->copy(); $d->lte($rangeEnd); $d->addDay()) {
            $days[] = $d->copy();
        }

        $byProject = [];
        foreach ($tasks as $t) {
            $byProject[$t['project_id']]['project_name'] = $t['project_name'];
            $byProject[$t['project_id']]['project_name_ar'] = $t['project_name_ar'];
            $byProject[$t['project_id']]['tasks'][] = $t;
        }

        return ['rangeStart' => $rangeStart, 'days' => $days, 'byProject' => $byProject];
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
