<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to EstimateController::store()/update()
 * for the estimate create/edit forms — line-item quantity and unit cost, the
 * markup_percent mini-calculator field, and the valid_until date, none of which
 * had any type or range check before (qty/unit_cost were only ever cast with
 * (float), and markup_percent was silently clamped with min()/max() instead of
 * being rejected). Mirrors the CR/VAT/postal-code validation gaps closed by
 * App\Support\ZatcaAddressValidator earlier this session — same spirit, applied
 * to estimate numbers instead of address fields.
 *
 * Wrapped in DatabaseTransactions, matching GlobalSearchTest/ApprovalChainTest's
 * own convention, so none of this touches the real dev sqlite database.
 */
class EstimateNumericValidationTest extends TestCase
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

    private function basePayload(): array
    {
        return [
            'title' => 'Villa Renovation',
            'item_description' => ['Foundation works'],
            'item_qty' => [2],
            'item_cost' => [5000],
        ];
    }

    public function test_store_rejects_markup_percent_above_100(): void
    {
        $a = $this->makeCompanyWithOwner('MarkupHigh');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            ...$this->basePayload(),
            'markup_percent' => '150',
        ]);

        $response->assertRedirect('/app/estimates/create');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_non_numeric_markup_percent(): void
    {
        $a = $this->makeCompanyWithOwner('MarkupNaN');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            ...$this->basePayload(),
            'markup_percent' => 'abc',
        ]);

        $response->assertRedirect('/app/estimates/create');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_markup_percent_as_zero(): void
    {
        $a = $this->makeCompanyWithOwner('MarkupBlank');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            ...$this->basePayload(),
            'markup_percent' => '',
        ]);

        $response->assertRedirect();
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $this->assertSame('0.00', $estimate->markup_percent);
    }

    public function test_store_rejects_non_numeric_quantity(): void
    {
        $a = $this->makeCompanyWithOwner('QtyNaN');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            'title' => 'Villa Renovation',
            'item_description' => ['Foundation works'],
            'item_qty' => ['not-a-number'],
            'item_cost' => [5000],
        ]);

        $response->assertRedirect('/app/estimates/create');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_zero_quantity(): void
    {
        $a = $this->makeCompanyWithOwner('QtyZero');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            'title' => 'Villa Renovation',
            'item_description' => ['Foundation works'],
            'item_qty' => [0],
            'item_cost' => [5000],
        ]);

        $response->assertRedirect('/app/estimates/create');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_unit_cost(): void
    {
        $a = $this->makeCompanyWithOwner('CostNeg');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            'title' => 'Villa Renovation',
            'item_description' => ['Foundation works'],
            'item_qty' => [2],
            'item_cost' => [-100],
        ]);

        $response->assertRedirect('/app/estimates/create');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_unit_cost_as_zero(): void
    {
        $a = $this->makeCompanyWithOwner('CostBlank');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            'title' => 'Free sample line',
            'item_description' => ['Free sample'],
            'item_qty' => [1],
            'item_cost' => [''],
        ]);

        $response->assertRedirect();
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $item = EstimateItem::where('estimate_id', $estimate->id)->first();
        $this->assertSame('0.00', $item->unit_cost);
    }

    public function test_store_rejects_malformed_valid_until_date(): void
    {
        $a = $this->makeCompanyWithOwner('DateBad');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            ...$this->basePayload(),
            'valid_until' => '2026-13-99',
        ]);

        $response->assertRedirect('/app/estimates/create');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_a_valid_payload_and_computes_totals(): void
    {
        $a = $this->makeCompanyWithOwner('Happy');

        $response = $this->actingAs($a['owner'])->post('/app/estimates', [
            'title' => 'Villa Renovation',
            'item_description' => ['Foundation works'],
            'item_qty' => [2],
            'item_cost' => [5000],
            'markup_percent' => '10',
            'valid_until' => '2027-01-15',
        ]);

        $response->assertRedirect();
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $this->assertSame('10000.00', $estimate->subtotal);
        $this->assertSame('10.00', $estimate->markup_percent);
        $this->assertSame('1000.00', $estimate->markup_amount);
        $this->assertSame('2027-01-15', $estimate->valid_until);
    }

    public function test_update_rejects_out_of_range_markup_percent(): void
    {
        $a = $this->makeCompanyWithOwner('UpdateMarkup');
        $estimate = Estimate::create([
            'company_id' => $a['company']->id,
            'title' => 'Existing estimate',
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/estimates/' . $estimate->id . '/update', [
            'title' => 'Existing estimate',
            'item_description' => ['Line item'],
            'item_qty' => [1],
            'item_cost' => [1000],
            'markup_percent' => '-5',
        ]);

        $response->assertRedirect('/app/estimates/' . $estimate->id . '/edit');
        $estimate->refresh();
        // Unchanged — the rejected update must never overwrite the stored markup.
        $this->assertSame('0.00', $estimate->markup_percent);
    }

    public function test_update_rejects_quantity_above_max_bound(): void
    {
        $a = $this->makeCompanyWithOwner('UpdateQtyMax');
        $estimate = Estimate::create([
            'company_id' => $a['company']->id,
            'title' => 'Existing estimate',
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/estimates/' . $estimate->id . '/update', [
            'title' => 'Existing estimate',
            'item_description' => ['Line item'],
            'item_qty' => [99999999],
            'item_cost' => [1000],
        ]);

        $response->assertRedirect('/app/estimates/' . $estimate->id . '/edit');
        $this->assertSame(0, EstimateItem::where('estimate_id', $estimate->id)->count());
    }
}
