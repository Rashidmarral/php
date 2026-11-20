<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ScheduleTask;
use App\Models\ScheduleTaskDependency;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers ScheduleController's new dependency store/destroy actions: same-project
 * and same-company ownership checks, self-dependency and cycle rejection, and
 * that the critical-path flags/float make it onto the rendered page. The pure
 * CPM math itself is covered by Unit\CriticalPathCalculatorTest.
 */
class ScheduleDependencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-'.strtolower($tag).'-'.bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['scheduling' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag).'@example.com',
            'plan_id' => $plan->id,
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag).'-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    private function makeTask(Company $company, Project $project, string $title, ?string $start = null, ?string $end = null): ScheduleTask
    {
        return ScheduleTask::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'title' => $title,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'pending',
        ]);
    }

    public function test_store_dependency_creates_the_row(): void
    {
        $a = $this->makeCompanyWithOwner('DepStore');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa']);
        $framing = $this->makeTask($a['company'], $project, 'Framing', '2026-03-01', '2026-03-05');
        $electrical = $this->makeTask($a['company'], $project, 'Electrical rough-in', '2026-03-06', '2026-03-10');

        $response = $this->actingAs($a['owner'])->post('/app/schedule/dependencies', [
            'task_id' => $electrical->id,
            'depends_on_task_id' => $framing->id,
            'lag_days' => 2,
        ]);

        $response->assertRedirect('/app/schedule');
        $dep = ScheduleTaskDependency::where('task_id', $electrical->id)->first();
        $this->assertNotNull($dep);
        $this->assertSame($framing->id, $dep->depends_on_task_id);
        $this->assertSame(2, $dep->lag_days);
    }

    public function test_a_task_cannot_depend_on_itself(): void
    {
        $a = $this->makeCompanyWithOwner('DepSelf');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa']);
        $task = $this->makeTask($a['company'], $project, 'Framing', '2026-03-01', '2026-03-05');

        $response = $this->actingAs($a['owner'])->post('/app/schedule/dependencies', [
            'task_id' => $task->id,
            'depends_on_task_id' => $task->id,
        ]);

        $response->assertRedirect('/app/schedule');
        $this->assertSame(0, ScheduleTaskDependency::where('task_id', $task->id)->count());
    }

    public function test_a_dependency_across_two_different_projects_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('DepCrossProject');
        $projectOne = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa One']);
        $projectTwo = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Two']);
        $taskInOne = $this->makeTask($a['company'], $projectOne, 'Framing', '2026-03-01', '2026-03-05');
        $taskInTwo = $this->makeTask($a['company'], $projectTwo, 'Electrical', '2026-03-06', '2026-03-10');

        $response = $this->actingAs($a['owner'])->post('/app/schedule/dependencies', [
            'task_id' => $taskInTwo->id,
            'depends_on_task_id' => $taskInOne->id,
        ]);

        $response->assertRedirect('/app/schedule');
        $this->assertSame(0, ScheduleTaskDependency::count());
    }

    public function test_a_cross_tenant_dependency_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('DepCrossTenantA');
        $b = $this->makeCompanyWithOwner('DepCrossTenantB');
        $projectA = Project::create(['company_id' => $a['company']->id, 'name' => 'A Villa']);
        $projectB = Project::create(['company_id' => $b['company']->id, 'name' => 'B Villa']);
        $myTask = $this->makeTask($a['company'], $projectA, 'My task', '2026-03-01', '2026-03-05');
        $othersTask = $this->makeTask($b['company'], $projectB, "Other company's task", '2026-03-01', '2026-03-05');

        $response = $this->actingAs($a['owner'])->post('/app/schedule/dependencies', [
            'task_id' => $myTask->id,
            'depends_on_task_id' => $othersTask->id,
        ]);

        $response->assertStatus(404);
        $this->assertSame(0, ScheduleTaskDependency::count());
    }

    public function test_a_cyclic_dependency_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('DepCycle');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa']);
        $x = $this->makeTask($a['company'], $project, 'X', '2026-03-01', '2026-03-02');
        $y = $this->makeTask($a['company'], $project, 'Y', '2026-03-03', '2026-03-04');
        $z = $this->makeTask($a['company'], $project, 'Z', '2026-03-05', '2026-03-06');

        // Y depends on X, Z depends on Y — a straight chain X -> Y -> Z.
        ScheduleTaskDependency::create(['task_id' => $y->id, 'depends_on_task_id' => $x->id, 'lag_days' => 0]);
        ScheduleTaskDependency::create(['task_id' => $z->id, 'depends_on_task_id' => $y->id, 'lag_days' => 0]);

        // Now try to make X depend on Z, which would close the loop X->Y->Z->X.
        $response = $this->actingAs($a['owner'])->post('/app/schedule/dependencies', [
            'task_id' => $x->id,
            'depends_on_task_id' => $z->id,
        ]);

        $response->assertRedirect('/app/schedule');
        $this->assertSame(0, ScheduleTaskDependency::where('task_id', $x->id)->count());
        $this->assertSame(2, ScheduleTaskDependency::count());
    }

    public function test_destroy_dependency_removes_it(): void
    {
        $a = $this->makeCompanyWithOwner('DepDestroy');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa']);
        $x = $this->makeTask($a['company'], $project, 'X', '2026-03-01', '2026-03-02');
        $y = $this->makeTask($a['company'], $project, 'Y', '2026-03-03', '2026-03-04');
        $dep = ScheduleTaskDependency::create(['task_id' => $y->id, 'depends_on_task_id' => $x->id, 'lag_days' => 0]);

        $response = $this->actingAs($a['owner'])->post('/app/schedule/dependencies/'.$dep->id.'/delete');

        $response->assertRedirect('/app/schedule');
        $this->assertNull(ScheduleTaskDependency::find($dep->id));
    }

    public function test_destroy_dependency_is_rejected_for_another_companys_task(): void
    {
        $a = $this->makeCompanyWithOwner('DepDestroyCrossA');
        $b = $this->makeCompanyWithOwner('DepDestroyCrossB');
        $projectB = Project::create(['company_id' => $b['company']->id, 'name' => 'B Villa']);
        $x = $this->makeTask($b['company'], $projectB, 'X', '2026-03-01', '2026-03-02');
        $y = $this->makeTask($b['company'], $projectB, 'Y', '2026-03-03', '2026-03-04');
        $dep = ScheduleTaskDependency::create(['task_id' => $y->id, 'depends_on_task_id' => $x->id, 'lag_days' => 0]);

        $response = $this->actingAs($a['owner'])->post('/app/schedule/dependencies/'.$dep->id.'/delete');

        $response->assertStatus(404);
        $this->assertNotNull(ScheduleTaskDependency::find($dep->id));
    }

    /**
     * End-to-end: build the same two-parallel-paths shape as
     * CriticalPathCalculatorTest through the real controller/DB, and confirm
     * the critical-path flag and float both reach the rendered Gantt + List
     * view output.
     */
    public function test_index_page_shows_critical_path_and_float(): void
    {
        $a = $this->makeCompanyWithOwner('DepIndexCpm');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa']);

        $taskA = $this->makeTask($a['company'], $project, 'Framing', '2026-04-01', '2026-04-05'); // 5d, critical
        $taskB = $this->makeTask($a['company'], $project, 'Electrical rough-in', '2026-04-06', '2026-04-08'); // 3d, critical
        $taskC = $this->makeTask($a['company'], $project, 'Site prep', '2026-04-01', '2026-04-02'); // 2d, slack
        $taskD = $this->makeTask($a['company'], $project, 'Plumbing rough-in', '2026-04-03', '2026-04-05'); // 3d, slack
        $taskEnd = $this->makeTask($a['company'], $project, 'Final inspection', '2026-04-09', '2026-04-09'); // 1d, critical

        ScheduleTaskDependency::create(['task_id' => $taskB->id, 'depends_on_task_id' => $taskA->id, 'lag_days' => 0]);
        ScheduleTaskDependency::create(['task_id' => $taskD->id, 'depends_on_task_id' => $taskC->id, 'lag_days' => 0]);
        ScheduleTaskDependency::create(['task_id' => $taskEnd->id, 'depends_on_task_id' => $taskB->id, 'lag_days' => 0]);
        ScheduleTaskDependency::create(['task_id' => $taskEnd->id, 'depends_on_task_id' => $taskD->id, 'lag_days' => 0]);

        $response = $this->actingAs($a['owner'])->get('/app/schedule');

        $response->assertOk();
        // Critical-path bars get the "critical" CSS modifier; the slack tasks don't.
        $response->assertSee('gantt-bar status-pending critical', false);
        // The SVG dependency-line overlay is present.
        $response->assertSee('gantt-dep-line', false);
        // Site prep (task C) has float 3 days, surfaced as a badge in the List view.
        $response->assertSee('float-badge">3</span>', false);
    }
}
