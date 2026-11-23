<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to ProjectController::store()/update() for
 * the project create/edit form — budget, advance payment amount, advance recovery %,
 * LD rate/cap %, and every date field, none of which had any type or range check
 * before (each was only ever (float)-cast, or clamped with min()/max() instead of
 * being rejected). Mirrors EstimateNumericValidationTest's own shape and reasoning.
 *
 * Wrapped in DatabaseTransactions so none of this touches the real dev sqlite
 * database beyond the test.
 */
class ProjectNumericValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithOwner(string $tag): array
    {
        $company = Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag) . '@example.com',
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag) . '-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    public function test_store_rejects_non_numeric_budget(): void
    {
        $a = $this->makeCompanyWithOwner('BudgetNaN');

        $response = $this->actingAs($a['owner'])->post('/app/projects', [
            'name' => 'Villa Project',
            'budget' => 'abc',
        ]);

        $response->assertRedirect('/app/projects/create');
        $this->assertSame(0, Project::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_budget(): void
    {
        $a = $this->makeCompanyWithOwner('BudgetNeg');

        $response = $this->actingAs($a['owner'])->post('/app/projects', [
            'name' => 'Villa Project',
            'budget' => '-500',
        ]);

        $response->assertRedirect('/app/projects/create');
        $this->assertSame(0, Project::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_budget_as_zero(): void
    {
        $a = $this->makeCompanyWithOwner('BudgetBlank');

        $response = $this->actingAs($a['owner'])->post('/app/projects', [
            'name' => 'Villa Project',
            'budget' => '',
        ]);

        $response->assertRedirect();
        $project = Project::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($project);
        $this->assertSame('0.00', $project->budget);
    }

    public function test_store_rejects_advance_recovery_percent_above_100(): void
    {
        $a = $this->makeCompanyWithOwner('RecoveryHigh');

        $response = $this->actingAs($a['owner'])->post('/app/projects', [
            'name' => 'Villa Project',
            'advance_recovery_percent' => '150',
        ]);

        $response->assertRedirect('/app/projects/create');
        $this->assertSame(0, Project::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_non_numeric_ld_cap_percent(): void
    {
        $a = $this->makeCompanyWithOwner('LdCapNaN');

        $response = $this->actingAs($a['owner'])->post('/app/projects', [
            'name' => 'Villa Project',
            'ld_cap_percent' => 'not-a-number',
        ]);

        $response->assertRedirect('/app/projects/create');
        $this->assertSame(0, Project::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_start_date(): void
    {
        $a = $this->makeCompanyWithOwner('DateBad');

        $response = $this->actingAs($a['owner'])->post('/app/projects', [
            'name' => 'Villa Project',
            'start_date' => '2026-13-40',
        ]);

        $response->assertRedirect('/app/projects/create');
        $this->assertSame(0, Project::where('company_id', $a['company']->id)->count());
    }

    public function test_store_truncates_overlong_name(): void
    {
        $a = $this->makeCompanyWithOwner('NameLong');

        $response = $this->actingAs($a['owner'])->post('/app/projects', [
            'name' => str_repeat('A', 300),
        ]);

        $response->assertRedirect();
        $project = Project::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($project);
        $this->assertSame(150, mb_strlen($project->name));
    }

    public function test_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('Happy');

        $response = $this->actingAs($a['owner'])->post('/app/projects', [
            'name' => 'Villa Project',
            'budget' => '500000',
            'advance_payment_amount' => '50000',
            'advance_recovery_percent' => '20',
            'ld_rate_per_day' => '1000',
            'ld_cap_percent' => '10',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
        ]);

        $response->assertRedirect();
        $project = Project::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($project);
        $this->assertSame('500000.00', $project->budget);
        $this->assertSame('20.00', $project->advance_recovery_percent);
    }

    public function test_update_rejects_out_of_range_ld_cap_percent_and_leaves_stored_value(): void
    {
        $a = $this->makeCompanyWithOwner('UpdateLdCap');
        $project = Project::create([
            'company_id' => $a['company']->id,
            'name' => 'Existing Project',
            'status' => 'planning',
            'budget' => 1000,
            'ld_cap_percent' => 5,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $project->id, [
            'name' => 'Existing Project',
            'budget' => '1000',
            'ld_cap_percent' => '-10',
        ]);

        $response->assertRedirect('/app/projects/' . $project->id . '/edit');
        $project->refresh();
        $this->assertSame('5.00', $project->ld_cap_percent);
    }
}
