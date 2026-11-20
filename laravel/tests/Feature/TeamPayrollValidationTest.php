<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the validation added to TeamController::store()/updatePayroll() — the Saudi
 * national ID/Iqama format (previously accepted completely free-form, despite the
 * payroll form's own placeholder implying a 10-digit number) and the salary/hourly-rate
 * numeric fields (previously a hand-rolled is_numeric()+>=0 check with no upper bound
 * matching each column's real decimal precision, now the same validatedNumber() shape
 * used everywhere else in this app). Also covers invite() rejecting an over-length
 * email rather than ever truncating it into a different address. Mirrors
 * ProjectNumericValidationTest's own shape.
 */
class TeamPayrollValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithOwner(string $tag): array
    {
        $company = Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag).'@example.com',
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

    private function makeMember(Company $company, string $tag): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Worker",
            'email' => strtolower($tag).'-worker@example.com',
            'password' => Hash::make('password'),
            'role' => 'estimator',
            'status' => 'active',
        ]);
    }

    public function test_update_payroll_rejects_a_malformed_national_id(): void
    {
        $a = $this->makeCompanyWithOwner('PrNidBad');
        $member = $this->makeMember($a['company'], 'PrNidBad');

        $response = $this->actingAs($a['owner'])->post('/app/team/'.$member->id.'/payroll', [
            'national_id' => '12345',
        ]);

        $response->assertRedirect('/app/team/'.$member->id.'/payroll');
        $this->assertNull($member->fresh()->national_id);
    }

    public function test_update_payroll_rejects_a_national_id_not_starting_with_1_or_2(): void
    {
        $a = $this->makeCompanyWithOwner('PrNidPrefix');
        $member = $this->makeMember($a['company'], 'PrNidPrefix');

        $response = $this->actingAs($a['owner'])->post('/app/team/'.$member->id.'/payroll', [
            'national_id' => '9123456789',
        ]);

        $response->assertRedirect('/app/team/'.$member->id.'/payroll');
        $this->assertNull($member->fresh()->national_id);
    }

    public function test_update_payroll_accepts_a_valid_national_id(): void
    {
        $a = $this->makeCompanyWithOwner('PrNidOk');
        $member = $this->makeMember($a['company'], 'PrNidOk');

        $response = $this->actingAs($a['owner'])->post('/app/team/'.$member->id.'/payroll', [
            'national_id' => '1234567890',
        ]);

        $response->assertRedirect('/app/team/'.$member->id.'/payroll');
        $this->assertSame('1234567890', $member->fresh()->national_id);
    }

    public function test_update_payroll_rejects_a_non_numeric_basic_salary(): void
    {
        $a = $this->makeCompanyWithOwner('PrSalNaN');
        $member = $this->makeMember($a['company'], 'PrSalNaN');

        $response = $this->actingAs($a['owner'])->post('/app/team/'.$member->id.'/payroll', [
            'basic_salary' => 'abc',
        ]);

        $response->assertRedirect('/app/team/'.$member->id.'/payroll');
        $this->assertNull($member->fresh()->basic_salary);
    }

    public function test_update_payroll_rejects_hourly_rate_above_its_column_ceiling(): void
    {
        $a = $this->makeCompanyWithOwner('PrHourlyHigh');
        $member = $this->makeMember($a['company'], 'PrHourlyHigh');

        $response = $this->actingAs($a['owner'])->post('/app/team/'.$member->id.'/payroll', [
            'hourly_rate' => '9999999',
        ]);

        $response->assertRedirect('/app/team/'.$member->id.'/payroll');
        $this->assertNull($member->fresh()->hourly_rate);
    }

    public function test_update_payroll_accepts_a_valid_payload_and_truncates_overlong_bank_name(): void
    {
        $a = $this->makeCompanyWithOwner('PrHappy');
        $member = $this->makeMember($a['company'], 'PrHappy');

        $response = $this->actingAs($a['owner'])->post('/app/team/'.$member->id.'/payroll', [
            'national_id' => '2123456789',
            'basic_salary' => '8000',
            'hourly_rate' => '45.50',
            'bank_name' => str_repeat('B', 200),
        ]);

        $response->assertRedirect('/app/team/'.$member->id.'/payroll');
        $member->refresh();
        $this->assertSame('2123456789', $member->national_id);
        $this->assertSame('8000.00', $member->basic_salary);
        $this->assertSame('45.50', $member->hourly_rate);
        $this->assertSame(150, mb_strlen($member->bank_name));
    }

    public function test_invite_rejects_an_over_length_email_instead_of_truncating_it(): void
    {
        $a = $this->makeCompanyWithOwner('TeamEmailLong');

        $longLocalPart = str_repeat('a', 150);
        $response = $this->actingAs($a['owner'])->post('/app/team', [
            'name' => 'New Hire',
            'email' => $longLocalPart.'@example.com',
        ]);

        $response->assertRedirect('/app/team');
        $this->assertSame(1, User::where('company_id', $a['company']->id)->count(), 'only the owner — the over-length email must never be stored, truncated or otherwise');
    }
}
