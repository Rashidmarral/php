<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ScheduleTask;
use App\Models\ScheduleTaskDependency;
use App\Support\CriticalPathCalculator;
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

        $dependencies = DB::table('schedule_task_dependencies as d')
            ->join('schedule_tasks as t', 't.id', '=', 'd.task_id')
            ->where('t.company_id', $companyId)
            ->select('d.*')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->applyCriticalPath($tasks, $dependencies);

        return view('app.schedule.index', [
            'tasks' => $tasks,
            'dependencies' => $dependencies,
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'gantt' => $this->buildGantt($tasks),
        ]);
    }

    /**
     * Runs CriticalPathCalculator once per project (each project's tasks and
     * dependency edges form their own independent graph) and merges `es`,
     * `ef`, `ls`, `lf`, `float` and `is_critical` into each row of $tasks in
     * place. Tasks without both dates simply get `is_critical = false` and a
     * null float — CriticalPathCalculator already excludes them from the math.
     */
    private function applyCriticalPath(array &$tasks, array $dependencies): void
    {
        $byProject = [];
        foreach ($tasks as $t) {
            $byProject[$t['project_id']][] = $t;
        }

        $cpmByTaskId = [];
        foreach ($byProject as $projectId => $projectTasks) {
            $taskIds = array_flip(array_column($projectTasks, 'id'));
            $projectDependencies = array_values(array_filter(
                $dependencies,
                fn ($d) => isset($taskIds[$d['task_id']]) && isset($taskIds[$d['depends_on_task_id']])
            ));
            $cpmByTaskId += CriticalPathCalculator::calculate($projectTasks, $projectDependencies);
        }

        foreach ($tasks as &$t) {
            $cpm = $cpmByTaskId[$t['id']] ?? null;
            $t['es'] = $cpm['es'] ?? null;
            $t['ef'] = $cpm['ef'] ?? null;
            $t['ls'] = $cpm['ls'] ?? null;
            $t['lf'] = $cpm['lf'] ?? null;
            $t['float'] = $cpm['float'] ?? null;
            $t['is_critical'] = $cpm['is_critical'] ?? false;
        }
        unset($t);
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
            return $this->redirectWithFlash('/app/schedule', 'error', t('user.schedule.title_project_required'));
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

        $this->flash('success', t('user.schedule.added'));
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
        $this->flash('success', t('user.schedule.removed'));
        return redirect('/app/schedule');
    }

    private function findOwned(int $id): ScheduleTask
    {
        $task = ScheduleTask::find($id);
        abort_if(!$task || $task->company_id !== Auth::user()->company_id, 404, 'Task not found.');
        return $task;
    }

    /**
     * Records that `task_id` can't start until `depends_on_task_id` finishes
     * (Finish-to-Start, with an optional lag in days) — the only dependency
     * relationship this tool supports; see CriticalPathCalculator's docblock
     * for why Start-to-Start/Finish-to-Finish/Start-to-Finish are out of scope.
     */
    public function storeDependency(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $taskId = (int) $request->input('task_id');
        $dependsOnTaskId = (int) $request->input('depends_on_task_id');
        $lagDays = max(0, (int) $request->input('lag_days', 0));

        $task = $this->findOwned($taskId);
        $dependsOnTask = $this->findOwned($dependsOnTaskId);

        if ($taskId === $dependsOnTaskId) {
            return $this->redirectWithFlash('/app/schedule', 'error', t('user.schedule.dependency_self'));
        }
        if ($task->project_id !== $dependsOnTask->project_id) {
            return $this->redirectWithFlash('/app/schedule', 'error', t('user.schedule.dependency_cross_project'));
        }
        if ($this->wouldCreateCycle($companyId, $taskId, $dependsOnTaskId)) {
            return $this->redirectWithFlash('/app/schedule', 'error', t('user.schedule.dependency_cycle'));
        }

        ScheduleTaskDependency::create([
            'task_id' => $taskId,
            'depends_on_task_id' => $dependsOnTaskId,
            'lag_days' => $lagDays,
        ]);

        $this->flash('success', t('user.schedule.dependency_added'));
        return redirect('/app/schedule');
    }

    public function destroyDependency(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $dependency = ScheduleTaskDependency::find($id);
        abort_if(!$dependency, 404, 'Dependency not found.');
        // A dependency has no company_id of its own — ownership is proven via its task.
        $this->findOwned($dependency->task_id);

        $dependency->delete();
        $this->flash('success', t('user.schedule.dependency_removed'));
        return redirect('/app/schedule');
    }

    /**
     * Would adding the edge "$taskId depends on $dependsOnTaskId" create a cycle?
     * True iff $taskId is already reachable from $dependsOnTaskId by walking the
     * existing "depends on" edges (i.e. $dependsOnTaskId already (directly or
     * transitively) depends on $taskId) — adding the new edge on top of that would
     * close a loop. A real DFS over the company's existing dependency graph, not a
     * shortcut, since a cyclic graph makes the CPM forward/backward pass meaningless.
     */
    private function wouldCreateCycle(int $companyId, int $taskId, int $dependsOnTaskId): bool
    {
        $edges = DB::table('schedule_task_dependencies as d')
            ->join('schedule_tasks as t', 't.id', '=', 'd.task_id')
            ->where('t.company_id', $companyId)
            ->select('d.task_id', 'd.depends_on_task_id')
            ->get();

        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->task_id][] = $edge->depends_on_task_id;
        }

        $visited = [];
        $stack = [$dependsOnTaskId];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if ($current === $taskId) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($adjacency[$current] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }
}
