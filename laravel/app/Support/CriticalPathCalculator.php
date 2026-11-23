<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Standard forward/backward-pass Critical Path Method (CPM), scoped to a single
 * project's own tasks and dependency edges (the caller — ScheduleController —
 * fetches both and hands them here; this class does no DB/HTTP work of its own,
 * which keeps it trivial to unit test with hand-built fixtures).
 *
 * Scope: Finish-to-Start dependencies only, with an optional lag in days (task B
 * starts no earlier than N days after task A finishes). This deliberately skips
 * the other three CPM relationship types (Start-to-Start, Finish-to-Finish,
 * Start-to-Finish) — Finish-to-Start covers the overwhelming majority of real
 * contractor scheduling ("framing before electrical rough-in") and keeps both the
 * data model and this math simple. This is a scoping decision, not an oversight.
 *
 * A task only participates in the CPM math when it has BOTH a start_date and an
 * end_date set. A task without both can still exist and carry dependency edges,
 * but it's excluded here — deliberate simplification, not a bug, since there's no
 * duration to compute a float for.
 *
 * Units: ES/EF/LS/LF are plain integer day-offsets, not calendar dates. Every task
 * with no predecessors gets ES = 0 ("the project's own earliest start") — i.e. all
 * root tasks are treated as able to start together at the project's earliest
 * possible point, regardless of whatever start_date each one happens to carry in
 * the DB (that stored date still feeds the task's own duration, just not its ES).
 * Everything downstream is derived relative to that day 0.
 */
class CriticalPathCalculator
{
    /**
     * @param  array<int, array{id: int, start_date: mixed, end_date: mixed}>  $tasks
     * @param  array<int, array{task_id: int, depends_on_task_id: int, lag_days: int}>  $dependencies
     * @return array<int, array{es: int, ef: int, ls: int, lf: int, float: int, is_critical: bool}>
     *                                                                                              keyed by task id; tasks without both dates are simply absent from the result.
     */
    public static function calculate(array $tasks, array $dependencies): array
    {
        $participants = [];
        foreach ($tasks as $task) {
            if (empty($task['start_date']) || empty($task['end_date'])) {
                continue;
            }
            $participants[$task['id']] = [
                'start' => Carbon::parse($task['start_date']),
                'end' => Carbon::parse($task['end_date']),
            ];
        }

        if (empty($participants)) {
            return [];
        }

        // Duration mirrors the existing Gantt bar-width calculation exactly
        // (resources/views/app/schedule/index.blade.php): inclusive day count,
        // floored at 1 day.
        $duration = [];
        foreach ($participants as $id => $p) {
            $duration[$id] = (int) max(1, $p['start']->diffInDays($p['end']) + 1);
        }

        // Build predecessor/successor adjacency, restricted to edges where BOTH
        // ends are participants (an edge touching a non-dated task is simply
        // skipped for the math — it still exists in the DB).
        $predecessorsOf = array_fill_keys(array_keys($participants), []);
        $successorsOf = array_fill_keys(array_keys($participants), []);
        foreach ($dependencies as $dep) {
            $taskId = $dep['task_id'];
            $dependsOnId = $dep['depends_on_task_id'];
            if (! isset($participants[$taskId]) || ! isset($participants[$dependsOnId])) {
                continue;
            }
            $lag = (int) ($dep['lag_days'] ?? 0);
            $predecessorsOf[$taskId][] = ['id' => $dependsOnId, 'lag' => $lag];
            $successorsOf[$dependsOnId][] = ['id' => $taskId, 'lag' => $lag];
        }

        $order = self::topologicalOrder($participants, $predecessorsOf, $successorsOf);

        // Forward pass: Early Start / Early Finish.
        $es = [];
        $ef = [];
        foreach ($order as $id) {
            if (empty($predecessorsOf[$id])) {
                $es[$id] = 0;
            } else {
                $max = 0;
                foreach ($predecessorsOf[$id] as $pred) {
                    $max = max($max, $ef[$pred['id']] + $pred['lag']);
                }
                $es[$id] = $max;
            }
            $ef[$id] = $es[$id] + $duration[$id];
        }

        $projectFinish = max($ef);

        // Backward pass: Late Finish / Late Start, walked in reverse topological
        // order so every successor's LS is already known before a task needs it.
        $lf = [];
        $ls = [];
        foreach (array_reverse($order) as $id) {
            if (empty($successorsOf[$id])) {
                $lf[$id] = $projectFinish;
            } else {
                $min = PHP_INT_MAX;
                foreach ($successorsOf[$id] as $succ) {
                    $min = min($min, $ls[$succ['id']] - $succ['lag']);
                }
                $lf[$id] = $min;
            }
            $ls[$id] = $lf[$id] - $duration[$id];
        }

        $result = [];
        foreach (array_keys($participants) as $id) {
            $float = $ls[$id] - $es[$id];
            $result[$id] = [
                'es' => $es[$id],
                'ef' => $ef[$id],
                'ls' => $ls[$id],
                'lf' => $lf[$id],
                'float' => $float,
                'is_critical' => $float === 0,
            ];
        }

        return $result;
    }

    /**
     * Kahn's algorithm, scoped to $participants. The caller (ScheduleController)
     * already rejects any dependency that would introduce a cycle before it's
     * ever written, so this subgraph is always a DAG in practice; if a stray
     * cycle somehow slipped through anyway, nodes stuck in it are simply
     * appended in their original order at the end rather than looping forever,
     * so the math degrades gracefully instead of hanging.
     *
     * @return array<int, int> task ids in topological order
     */
    private static function topologicalOrder(array $participants, array $predecessorsOf, array $successorsOf): array
    {
        $inDegree = [];
        foreach ($participants as $id => $_) {
            $inDegree[$id] = count($predecessorsOf[$id]);
        }

        $queue = array_keys(array_filter($inDegree, fn ($d) => $d === 0));
        $order = [];
        while (! empty($queue)) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($successorsOf[$id] as $succ) {
                $inDegree[$succ['id']]--;
                if ($inDegree[$succ['id']] === 0) {
                    $queue[] = $succ['id'];
                }
            }
        }

        if (count($order) !== count($participants)) {
            $remaining = array_diff(array_keys($participants), $order);
            foreach ($remaining as $id) {
                $order[] = $id;
            }
        }

        return $order;
    }
}
