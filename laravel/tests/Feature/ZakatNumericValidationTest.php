<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Models\ZakatCalculation;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to ZakatController::store() — equity_amount,
 * long_term_liabilities, net_fixed_assets, other_deductions, and period_end_date, none
 * of which had any type check before (every amount was only ever (float)-cast, so a
 * non-numeric value silently became 0 instead of being rejected, and period_end_date
 * only went through the very loose strtotime() check, which accepts things like "next
 * thursday" as a "valid" period end date). Mirrors ProjectNumericValidationTest's shape.
 */
class ZakatNumericValidationTest extends TestCase
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
            'feature_flags' => json_encode(['zakat' => true]),
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

    public function test_store_rejects_non_numeric_equity_amount(): void
    {
        $a = $this->makeCompanyWithOwner('ZkNaN');

        $response = $this->actingAs($a['owner'])->post('/app/zakat', [
            'period_end_date' => '2026-12-31',
            'equity_amount' => 'not-a-number',
        ]);

        $response->assertRedirect('/app/zakat/create');
        $this->assertSame(0, ZakatCalculation::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_a_malformed_period_end_date(): void
    {
        $a = $this->makeCompanyWithOwner('ZkDateBad');

        $response = $this->actingAs($a['owner'])->post('/app/zakat', [
            'period_end_date' => 'next thursday',
            'equity_amount' => '500000',
        ]);

        $response->assertRedirect('/app/zakat/create');
        $this->assertSame(0, ZakatCalculation::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_a_blank_period_end_date(): void
    {
        $a = $this->makeCompanyWithOwner('ZkDateBlank');

        $response = $this->actingAs($a['owner'])->post('/app/zakat', [
            'period_end_date' => '',
            'equity_amount' => '500000',
        ]);

        $response->assertRedirect('/app/zakat/create');
        $this->assertSame(0, ZakatCalculation::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_a_negative_equity_amount(): void
    {
        $a = $this->makeCompanyWithOwner('ZkNegative');

        $response = $this->actingAs($a['owner'])->post('/app/zakat', [
            'period_end_date' => '2026-12-31',
            'equity_amount' => '-50000',
            'long_term_liabilities' => '0',
            'net_fixed_assets' => '0',
            'other_deductions' => '0',
        ]);

        $response->assertRedirect();
        $calculation = ZakatCalculation::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($calculation);
        $this->assertSame('-50000.00', $calculation->equity_amount);
        // zakat_base is floored at zero even though the inputs net negative.
        $this->assertSame('0.00', $calculation->zakat_base);
    }

    public function test_store_truncates_overlong_notes(): void
    {
        $a = $this->makeCompanyWithOwner('ZkNotesLong');

        $response = $this->actingAs($a['owner'])->post('/app/zakat', [
            'period_end_date' => '2026-12-31',
            'equity_amount' => '500000',
            'notes' => str_repeat('N', 2500),
        ]);

        $response->assertRedirect();
        $calculation = ZakatCalculation::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($calculation);
        $this->assertSame(2000, mb_strlen($calculation->notes));
    }

    public function test_store_accepts_a_valid_payload_and_computes_the_hijri_rate(): void
    {
        $a = $this->makeCompanyWithOwner('ZkHappy');

        $response = $this->actingAs($a['owner'])->post('/app/zakat', [
            'period_end_date' => '2026-12-31',
            'rate_type' => 'hijri',
            'equity_amount' => '1000000',
            'long_term_liabilities' => '0',
            'net_fixed_assets' => '200000',
            'other_deductions' => '0',
        ]);

        $response->assertRedirect();
        $calculation = ZakatCalculation::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($calculation);
        $this->assertSame('800000.00', $calculation->zakat_base);
        $this->assertSame('20000.00', $calculation->zakat_due); // 800000 * 2.5%
    }
}
