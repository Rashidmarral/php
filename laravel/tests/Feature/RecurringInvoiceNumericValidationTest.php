<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to RecurringInvoiceController::store()/
 * update() — line-item qty/unit_price, retention_percent, due_days, and
 * next_run_date, none of which had any type or range check before (qty/unit_price
 * were only ever coerced with a bare (float) cast, retention_percent/due_days were
 * silently clamped with min()/max() instead of being rejected, and next_run_date
 * was stored exactly as submitted with no calendar-date check at all). Mirrors
 * EstimateNumericValidationTest's own conventions, applied here to recurring
 * invoice templates.
 */
class RecurringInvoiceNumericValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithOwner(string $tag): array
    {
        $company = Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag).'@example.com',
            'feature_overrides' => ['recurring_invoices' => true],
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

    private function basePayload(): array
    {
        return [
            'title' => 'Monthly Site Security Retainer',
            'item_description' => ['Retainer fee'],
            'item_qty' => [1],
            'item_price' => [5000],
        ];
    }

    public function test_store_rejects_non_numeric_quantity(): void
    {
        $a = $this->makeCompanyWithOwner('RiQtyNaN');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'item_qty' => ['not-a-number'],
        ]);

        $response->assertRedirect('/app/recurring-invoices/create');
        $this->assertSame(0, RecurringInvoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_zero_quantity(): void
    {
        $a = $this->makeCompanyWithOwner('RiQtyZero');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'item_qty' => [0],
        ]);

        $response->assertRedirect('/app/recurring-invoices/create');
        $this->assertSame(0, RecurringInvoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_unit_price(): void
    {
        $a = $this->makeCompanyWithOwner('RiPriceNeg');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'item_price' => [-100],
        ]);

        $response->assertRedirect('/app/recurring-invoices/create');
        $this->assertSame(0, RecurringInvoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_unit_price_as_zero(): void
    {
        $a = $this->makeCompanyWithOwner('RiPriceBlank');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'item_price' => [''],
        ]);

        $response->assertRedirect();
        $template = RecurringInvoice::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($template);
        $item = RecurringInvoiceItem::where('recurring_invoice_id', $template->id)->first();
        $this->assertSame('0.00', $item->unit_price);
    }

    public function test_store_rejects_retention_percent_above_100(): void
    {
        $a = $this->makeCompanyWithOwner('RiRetentionHigh');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'retention_percent' => '150',
        ]);

        $response->assertRedirect('/app/recurring-invoices/create');
        $this->assertSame(0, RecurringInvoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_non_numeric_due_days(): void
    {
        $a = $this->makeCompanyWithOwner('RiDueDaysNaN');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'due_days' => 'abc',
        ]);

        $response->assertRedirect('/app/recurring-invoices/create');
        $this->assertSame(0, RecurringInvoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_due_days_above_max_bound(): void
    {
        $a = $this->makeCompanyWithOwner('RiDueDaysMax');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'due_days' => 99999,
        ]);

        $response->assertRedirect('/app/recurring-invoices/create');
        $this->assertSame(0, RecurringInvoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_next_run_date(): void
    {
        $a = $this->makeCompanyWithOwner('RiNextRunBad');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'next_run_date' => '2026-02-30',
        ]);

        $response->assertRedirect('/app/recurring-invoices/create');
        $this->assertSame(0, RecurringInvoice::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_a_valid_payload_and_persists_items(): void
    {
        $a = $this->makeCompanyWithOwner('RiHappy');

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices', [
            ...$this->basePayload(),
            'retention_percent' => '10',
            'due_days' => '30',
            'next_run_date' => '2026-12-01',
        ]);

        $response->assertRedirect();
        $template = RecurringInvoice::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($template);
        $this->assertSame('10.00', $template->retention_percent);
        $this->assertSame(30, $template->due_days);
        $this->assertSame('2026-12-01', $template->next_run_date->format('Y-m-d'));
    }

    public function test_update_rejects_out_of_range_retention_percent(): void
    {
        $a = $this->makeCompanyWithOwner('RiUpdateRetention');
        $template = RecurringInvoice::create([
            'company_id' => $a['company']->id,
            'title' => 'Existing template',
            'frequency' => 'monthly',
            'next_run_date' => now()->format('Y-m-d'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices/'.$template->id, [
            'title' => 'Existing template',
            'item_description' => ['Line item'],
            'item_qty' => [1],
            'item_price' => [1000],
            'retention_percent' => '-5',
        ]);

        $response->assertRedirect('/app/recurring-invoices/'.$template->id.'/edit');
        $template->refresh();
        // Unchanged — the rejected update must never overwrite the stored retention_percent.
        $this->assertSame('0.00', $template->retention_percent);
    }

    public function test_update_rejects_quantity_above_max_bound(): void
    {
        $a = $this->makeCompanyWithOwner('RiUpdateQtyMax');
        $template = RecurringInvoice::create([
            'company_id' => $a['company']->id,
            'title' => 'Existing template',
            'frequency' => 'monthly',
            'next_run_date' => now()->format('Y-m-d'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/recurring-invoices/'.$template->id, [
            'title' => 'Existing template',
            'item_description' => ['Line item'],
            'item_qty' => [100000000],
            'item_price' => [1000],
        ]);

        $response->assertRedirect('/app/recurring-invoices/'.$template->id.'/edit');
        $this->assertSame(0, RecurringInvoiceItem::where('recurring_invoice_id', $template->id)->count());
    }
}
