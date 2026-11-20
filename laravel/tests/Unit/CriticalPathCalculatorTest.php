<?php

namespace Tests\Unit;

use App\Support\CriticalPathCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Pure-math coverage for CriticalPathCalculator — no DB, no HTTP (see
 * MoyasarStcPayTest for the same style in this suite). Scope: Finish-to-Start
 * dependencies with an optional lag only, per CriticalPathCalculator's docblock.
 */
class CriticalPathCalculatorTest extends TestCase
{
    /**
     * Textbook two-parallel-paths example, hand-worked below so the test is
     * self-documenting:
     *
     *   Path 1 (longer):  A(5d) -> B(3d) -> End(1d)   total = 9 days
     *   Path 2 (shorter):  C(2d) -> D(3d) -> End(1d)   total = 6 days
     *
     * A and C have no predecessors, so both get ES = 0 (the project's own
     * earliest start). End depends on both B and D (lag 0 on every edge).
     *
     *   A:   ES=0,  EF=0+5=5
     *   C:   ES=0,  EF=0+2=2
     *   B:   ES=EF(A)=5,            EF=5+3=8
     *   D:   ES=EF(C)=2,            EF=2+3=5
     *   End: ES=max(EF(B),EF(D))=max(8,5)=8,  EF=8+1=9   <- project finish = 9
     *
     * Backward pass (project finish = 9):
     *   End: no successors -> LF=9,  LS=9-1=8,  float=8-8=0   (critical)
     *   B:   successor End (lag0) -> LF=LS(End)-0=8, LS=8-3=5, float=5-5=0  (critical)
     *   A:   successor B   (lag0) -> LF=LS(B)-0=5,   LS=5-5=0, float=0-0=0  (critical)
     *   D:   successor End (lag0) -> LF=LS(End)-0=8, LS=8-3=5, float=5-2=3  (slack)
     *   C:   successor D   (lag0) -> LF=LS(D)-0=5,   LS=5-2=3, float=3-0=3  (slack)
     *
     * Critical path A->B->End (5+3+1=9) has float 0 throughout; the shorter
     * path C->D->End (2+3+1=6) has float 3 throughout, exactly the
     * difference between the two paths' total durations (9-6=3).
     */
    public function test_two_parallel_paths_textbook_example(): void
    {
        $tasks = [
            ['id' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-01-05'], // A, 5d
            ['id' => 2, 'start_date' => '2026-01-06', 'end_date' => '2026-01-08'], // B, 3d
            ['id' => 3, 'start_date' => '2026-01-01', 'end_date' => '2026-01-02'], // C, 2d
            ['id' => 4, 'start_date' => '2026-01-03', 'end_date' => '2026-01-05'], // D, 3d
            ['id' => 5, 'start_date' => '2026-01-09', 'end_date' => '2026-01-09'], // End, 1d
        ];
        $dependencies = [
            ['task_id' => 2, 'depends_on_task_id' => 1, 'lag_days' => 0], // B depends on A
            ['task_id' => 4, 'depends_on_task_id' => 3, 'lag_days' => 0], // D depends on C
            ['task_id' => 5, 'depends_on_task_id' => 2, 'lag_days' => 0], // End depends on B
            ['task_id' => 5, 'depends_on_task_id' => 4, 'lag_days' => 0], // End depends on D
        ];

        $result = CriticalPathCalculator::calculate($tasks, $dependencies);

        $this->assertSame(['es' => 0, 'ef' => 5, 'ls' => 0, 'lf' => 5, 'float' => 0, 'is_critical' => true], $result[1]); // A
        $this->assertSame(['es' => 5, 'ef' => 8, 'ls' => 5, 'lf' => 8, 'float' => 0, 'is_critical' => true], $result[2]); // B
        $this->assertSame(['es' => 0, 'ef' => 2, 'ls' => 3, 'lf' => 5, 'float' => 3, 'is_critical' => false], $result[3]); // C
        $this->assertSame(['es' => 2, 'ef' => 5, 'ls' => 5, 'lf' => 8, 'float' => 3, 'is_critical' => false], $result[4]); // D
        $this->assertSame(['es' => 8, 'ef' => 9, 'ls' => 8, 'lf' => 9, 'float' => 0, 'is_critical' => true], $result[5]); // End

        // Longer path entirely critical, shorter path entirely non-critical with
        // uniform slack equal to the gap between the two paths' total durations.
        foreach ([1, 2, 5] as $id) {
            $this->assertTrue($result[$id]['is_critical'], "task {$id} should be critical");
        }
        foreach ([3, 4] as $id) {
            $this->assertSame(3, $result[$id]['float'], "task {$id} should have float 3");
            $this->assertFalse($result[$id]['is_critical']);
        }
    }

    /**
     * A(2d) -> B(4d) with a 3-day lag: B can't start until 3 days after A
     * finishes, on top of the Finish-to-Start relationship itself.
     *   A: ES=0, EF=0+2=2
     *   B: ES=EF(A)+lag=2+3=5, EF=5+4=9
     * Single chain, so both are critical (LF(B)=9, LS(B)=5; LF(A)=LS(B)-lag=2, LS(A)=0).
     */
    public function test_lag_days_delays_the_successors_early_start(): void
    {
        $tasks = [
            ['id' => 10, 'start_date' => '2026-02-01', 'end_date' => '2026-02-02'], // 2d
            ['id' => 11, 'start_date' => '2026-02-10', 'end_date' => '2026-02-13'], // 4d
        ];
        $dependencies = [
            ['task_id' => 11, 'depends_on_task_id' => 10, 'lag_days' => 3],
        ];

        $result = CriticalPathCalculator::calculate($tasks, $dependencies);

        $this->assertSame(0, $result[10]['es']);
        $this->assertSame(2, $result[10]['ef']);
        $this->assertSame(5, $result[11]['es']); // 2 (A's EF) + 3 lag
        $this->assertSame(9, $result[11]['ef']);
        $this->assertSame(0, $result[10]['float']);
        $this->assertSame(0, $result[11]['float']);
        $this->assertTrue($result[10]['is_critical']);
        $this->assertTrue($result[11]['is_critical']);
    }

    public function test_tasks_without_both_dates_are_excluded_from_the_result(): void
    {
        $tasks = [
            ['id' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-01-02'],
            ['id' => 2, 'start_date' => null, 'end_date' => null],
            ['id' => 3, 'start_date' => '2026-01-03', 'end_date' => null],
        ];

        $result = CriticalPathCalculator::calculate($tasks, []);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayNotHasKey(2, $result);
        $this->assertArrayNotHasKey(3, $result);
    }

    public function test_empty_task_list_returns_empty_result(): void
    {
        $this->assertSame([], CriticalPathCalculator::calculate([], []));
    }
}
