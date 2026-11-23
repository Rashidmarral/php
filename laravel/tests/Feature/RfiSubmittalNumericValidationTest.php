<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Submittal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the date validation (and name/text truncation) added to RfiController::
 * store() and SubmittalController::store() — due_date on both, plus subject/title/
 * description/spec_section/notes length caps matching their DB columns, none of
 * which had any check before. Mirrors EstimateNumericValidationTest's own shape.
 */
class RfiSubmittalNumericValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Support\Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompanyWithOwnerAndProject(string $tag, array $features = []): array
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['rfi' => true, 'submittals' => true, ...$features]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag) . '-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'name' => "{$tag} Project",
            'status' => 'in_progress',
            'budget' => 100000,
        ]);

        return compact('company', 'owner', 'project');
    }

    public function test_rfi_store_rejects_malformed_due_date(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('RfiDateBad');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/rfis', [
            'subject' => 'Clarify beam depth',
            'question' => 'What is the required beam depth at gridline C3?',
            'due_date' => '2026-02-30',
        ]);

        $response->assertRedirect('/app/projects/' . $a['project']->id . '/rfis/new');
        $this->assertSame(0, Rfi::where('project_id', $a['project']->id)->count());
    }

    public function test_rfi_store_truncates_overlong_subject(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('RfiSubjectLong');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/rfis', [
            'subject' => str_repeat('A', 300),
            'question' => 'A real question here.',
        ]);

        $response->assertRedirect();
        $rfi = Rfi::where('project_id', $a['project']->id)->first();
        $this->assertNotNull($rfi);
        $this->assertSame(200, mb_strlen($rfi->subject));
    }

    public function test_rfi_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('RfiHappy');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/rfis', [
            'subject' => 'Clarify beam depth',
            'question' => 'What is the required beam depth at gridline C3?',
            'due_date' => '2027-01-15',
        ]);

        $response->assertRedirect();
        $rfi = Rfi::where('project_id', $a['project']->id)->first();
        $this->assertNotNull($rfi);
        $this->assertSame('2027-01-15', $rfi->due_date->format('Y-m-d'));
    }

    public function test_submittal_store_rejects_malformed_due_date(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('SubmittalDateBad');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/submittals', [
            'title' => 'Concrete mix design',
            'due_date' => '2026-13-01',
            'file' => UploadedFile::fake()->create('mix-design.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect('/app/projects/' . $a['project']->id . '/submittals/new');
        $this->assertSame(0, Submittal::where('project_id', $a['project']->id)->count());
    }

    public function test_submittal_store_truncates_overlong_spec_section(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('SubmittalSpecLong');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/submittals', [
            'title' => 'Concrete mix design',
            'spec_section' => str_repeat('9', 50),
            'file' => UploadedFile::fake()->create('mix-design.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $submittal = Submittal::where('project_id', $a['project']->id)->first();
        $this->assertNotNull($submittal);
        $this->assertSame(30, mb_strlen($submittal->spec_section));
    }

    public function test_submittal_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('SubmittalHappy');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/submittals', [
            'title' => 'Concrete mix design',
            'spec_section' => '03 30 00',
            'due_date' => '2027-01-15',
            'file' => UploadedFile::fake()->create('mix-design.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $submittal = Submittal::where('project_id', $a['project']->id)->first();
        $this->assertNotNull($submittal);
        $this->assertSame('2027-01-15', $submittal->due_date->format('Y-m-d'));
    }
}
