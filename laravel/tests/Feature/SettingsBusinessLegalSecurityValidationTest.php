<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/enum/length validation added to SettingsController's
 * updateBusiness()/updateLegal()/updatePassword() — default_markup_percent,
 * default_retention_percent, contractor_classification, and the new-password
 * length guard, none of which had any check before (the two percentages were
 * only ever (float)-cast, and contractor_classification was a free-form value
 * written straight from the request despite the form only ever offering a
 * fixed 1-5 grade). Mirrors ProjectNumericValidationTest's own shape.
 */
class SettingsBusinessLegalSecurityValidationTest extends TestCase
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

    public function test_update_business_rejects_non_numeric_default_markup_percent(): void
    {
        $a = $this->makeCompanyWithOwner('BizMarkupNaN');

        $response = $this->actingAs($a['owner'])->post('/app/settings/business', [
            'default_markup_percent' => 'abc',
        ]);

        $response->assertRedirect('/app/settings/business');
        $a['company']->refresh();
        $this->assertSame('0.00', $a['company']->default_markup_percent);
    }

    public function test_update_business_rejects_default_retention_percent_above_100(): void
    {
        $a = $this->makeCompanyWithOwner('BizRetHigh');

        $response = $this->actingAs($a['owner'])->post('/app/settings/business', [
            'default_retention_percent' => '150',
        ]);

        $response->assertRedirect('/app/settings/business');
        $a['company']->refresh();
        $this->assertSame('0.00', $a['company']->default_retention_percent);
    }

    public function test_update_business_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('BizHappy');

        $response = $this->actingAs($a['owner'])->post('/app/settings/business', [
            'default_markup_percent' => '15.5',
            'default_retention_percent' => '7.5',
        ]);

        $response->assertRedirect('/app/settings/business');
        $a['company']->refresh();
        $this->assertSame('15.50', $a['company']->default_markup_percent);
        $this->assertSame('7.50', $a['company']->default_retention_percent);
    }

    public function test_update_legal_rejects_an_invalid_classification_grade(): void
    {
        $a = $this->makeCompanyWithOwner('LegalGradeBad');

        $response = $this->actingAs($a['owner'])->post('/app/settings/legal', [
            'contractor_classification' => '99',
        ]);

        $response->assertRedirect('/app/settings/legal');
        $a['company']->refresh();
        $this->assertNull($a['company']->contractor_classification);
    }

    public function test_update_legal_accepts_a_valid_classification_grade_and_truncates_overlong_number(): void
    {
        $a = $this->makeCompanyWithOwner('LegalGradeOk');

        $response = $this->actingAs($a['owner'])->post('/app/settings/legal', [
            'contractor_classification' => '3',
            'contractor_classification_number' => str_repeat('9', 80),
        ]);

        $response->assertRedirect('/app/settings/legal');
        $a['company']->refresh();
        $this->assertSame('3', $a['company']->contractor_classification);
        $this->assertSame(50, strlen($a['company']->contractor_classification_number));
    }

    public function test_update_password_rejects_a_password_over_128_characters(): void
    {
        $a = $this->makeCompanyWithOwner('PwTooLong');
        $a['owner']->update(['password' => Hash::make('OldStr0ng!Pass')]);

        $longPassword = 'Aa1!'.str_repeat('x', 130);
        $response = $this->actingAs($a['owner'])->post('/app/settings/security', [
            'current_password' => 'OldStr0ng!Pass',
            'new_password' => $longPassword,
            'new_password_confirm' => $longPassword,
        ]);

        $response->assertRedirect('/app/settings/security');
        $this->assertTrue(Hash::check('OldStr0ng!Pass', $a['owner']->fresh()->password));
    }
}
