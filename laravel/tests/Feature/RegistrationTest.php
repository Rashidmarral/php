<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the registration form's new fields: an optional company Arabic
 * name (Company::name_ar was previously never captured at signup, leaving
 * every new company's bilingual invoices/estimates/ZATCA documents with a
 * blank Arabic company name), a required password confirmation, and a
 * required Terms of Service / Privacy Policy acceptance checkbox — none of
 * which this registration flow had a test for before.
 */
class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private function makePlan(): Plan
    {
        return Plan::create([
            'slug' => 'starter-'.bin2hex(random_bytes(4)),
            'name' => 'Starter',
            'price_monthly' => 199,
        ]);
    }

    private function validPayload(Plan $plan, array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Al Rashid Construction Co.',
            'company_name_ar' => 'شركة الراشد للمقاولات',
            'name' => 'Nasser Al Otaibi',
            'email' => 'nasser-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'Str0ng!Pass2024',
            'password_confirmation' => 'Str0ng!Pass2024',
            'plan' => $plan->slug,
            'terms' => '1',
        ], $overrides);
    }

    public function test_registration_saves_the_optional_arabic_company_name(): void
    {
        $plan = $this->makePlan();
        $payload = $this->validPayload($plan);

        $response = $this->post('/register', $payload);

        $response->assertRedirect('/app');
        $company = Company::where('email', strtolower($payload['email']))->firstOrFail();
        $this->assertSame('شركة الراشد للمقاولات', $company->name_ar);
        $this->assertSame('Al Rashid Construction Co.', $company->name);
        $this->assertTrue(Auth::check());
    }

    public function test_registration_works_without_the_optional_arabic_company_name(): void
    {
        $plan = $this->makePlan();
        $payload = $this->validPayload($plan, ['company_name_ar' => '']);

        $response = $this->post('/register', $payload);

        $response->assertRedirect('/app');
        $company = Company::where('email', strtolower($payload['email']))->firstOrFail();
        $this->assertNull($company->name_ar);
    }

    public function test_registration_is_rejected_when_password_confirmation_does_not_match(): void
    {
        $plan = $this->makePlan();
        $payload = $this->validPayload($plan, ['password_confirmation' => 'SomethingElse123!']);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => strtolower($payload['email'])]);
    }

    public function test_registration_is_rejected_when_terms_are_not_accepted(): void
    {
        $plan = $this->makePlan();
        $payload = $this->validPayload($plan, ['terms' => null]);
        unset($payload['terms']);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors('terms');
        $this->assertDatabaseMissing('users', ['email' => strtolower($payload['email'])]);
    }

    public function test_registration_still_requires_the_same_base_fields_as_before(): void
    {
        $plan = $this->makePlan();
        $payload = $this->validPayload($plan, ['company_name' => '', 'name' => '', 'email' => '']);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors(['company_name', 'name', 'email']);
    }
}
