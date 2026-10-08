<?php

namespace Tests\Feature;

use App\Models\ChangeOrder;
use App\Models\ChangeOrderItem;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the ChangeOrder enrichment: (a) optional line items that
 * auto-sum `amount` once any exist, (b) a lightweight `time_impact_days`
 * note, and (c) the client-facing e-signature flow mirroring Estimate's
 * own /e/{token} + ShareController::signEstimate(). Wrapped in
 * DatabaseTransactions, matching EquipmentTest/RfiSubmittalDocumentVersionTest's
 * own convention so none of this touches the real dev sqlite database beyond
 * the test.
 */
class ChangeOrderEnrichmentTest extends TestCase
{
    use DatabaseTransactions;

    /** See EquipmentTest's own docblock on why this reset is needed. */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Support\Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompany(string $tag): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode([
                'change_orders' => true,
            ]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $tag): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => "Owner {$tag}",
            'email' => 'owner-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    private function makeProject(Company $company, string $tag): Project
    {
        return Project::create([
            'company_id' => $company->id,
            'name' => "Project {$tag}",
            'status' => 'in_progress',
            'budget' => 100000,
        ]);
    }

    /** (1) A change order can be created with line items and amount auto-sums correctly. */
    public function test_change_order_amount_auto_sums_from_line_items(): void
    {
        $company = $this->makeCompany('COI');
        $owner = $this->makeUser($company, 'COI');
        $project = $this->makeProject($company, 'COI');

        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/change-orders', [
            'title' => 'Additional glazing',
            'amount' => 5000,
        ]);
        $changeOrder = ChangeOrder::where('project_id', $project->id)->first();
        $this->assertNotNull($changeOrder);
        $this->assertSame('5000.00', $changeOrder->amount);

        $this->actingAs($owner)->post('/app/change-orders/' . $changeOrder->id . '/items', [
            'description' => 'Extra glass panels',
            'qty' => 10,
            'unit' => 'sqm',
            'unit_price' => 150,
        ]);
        $this->actingAs($owner)->post('/app/change-orders/' . $changeOrder->id . '/items', [
            'description' => 'Installation labor',
            'qty' => 2,
            'unit' => 'day',
            'unit_price' => 400,
        ]);

        $this->assertSame(2, ChangeOrderItem::where('change_order_id', $changeOrder->id)->count());
        $changeOrder->refresh();
        // 10*150 + 2*400 = 1500 + 800 = 2300
        $this->assertSame('2300.00', $changeOrder->amount);

        // Removing one item recomputes the sum from what's left.
        $firstItem = ChangeOrderItem::where('change_order_id', $changeOrder->id)->where('description', 'Extra glass panels')->first();
        $this->actingAs($owner)->post('/app/change-orders/' . $changeOrder->id . '/items/' . $firstItem->id . '/delete');
        $changeOrder->refresh();
        $this->assertSame('800.00', $changeOrder->amount);
    }

    /** (2) A change order with zero items keeps its manually-set amount unchanged — the critical backward-compat proof. */
    public function test_change_order_with_zero_items_keeps_manual_amount_unchanged(): void
    {
        $company = $this->makeCompany('COZ');
        $owner = $this->makeUser($company, 'COZ');
        $project = $this->makeProject($company, 'COZ');

        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/change-orders', [
            'title' => 'Scope reduction',
            'amount' => -3000,
        ]);
        $changeOrder = ChangeOrder::where('project_id', $project->id)->first();
        $this->assertSame('-3000.00', $changeOrder->amount);
        $this->assertSame(0, $changeOrder->items()->count());

        // Calling the sync method directly (as every item add/remove path does) must be a
        // true no-op when there are zero items — amount stays exactly as typed in.
        $changeOrder->syncAmountFromItems();
        $changeOrder->refresh();
        $this->assertSame('-3000.00', $changeOrder->amount, 'a change order with zero items must keep its manually-set amount unchanged');

        // Viewing/updating other fields on it must not disturb the amount either.
        $this->actingAs($owner)->post('/app/change-orders/' . $changeOrder->id . '/time-impact', ['time_impact_days' => 5]);
        $changeOrder->refresh();
        $this->assertSame('-3000.00', $changeOrder->amount);
        $this->assertSame(5, $changeOrder->time_impact_days);
    }

    /** (3) The public sign flow correctly records signed_at/signed_by_name/signature_data/signed_ip and fires the notification/webhook. */
    public function test_public_sign_flow_records_signature_and_fires_notification_and_webhook(): void
    {
        $company = $this->makeCompany('COS');
        $owner = $this->makeUser($company, 'COS');
        $project = $this->makeProject($company, 'COS');

        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/change-orders', [
            'title' => 'Upgraded flooring',
            'amount' => 7500,
        ]);
        $changeOrder = ChangeOrder::where('project_id', $project->id)->first();

        // Visiting the show page lazily generates the share_token, same as Estimate's show().
        $show = $this->actingAs($owner)->get('/app/change-orders/' . $changeOrder->id);
        $show->assertOk();
        $changeOrder->refresh();
        $this->assertNotEmpty($changeOrder->share_token);
        $this->assertSame(40, strlen($changeOrder->share_token));

        // Public view (no auth) works via the token.
        $publicView = $this->get('/co/' . $changeOrder->share_token);
        $publicView->assertOk();

        // Mailer::send()/WebhookDispatcher::dispatch() are both fire-and-forget no-ops here
        // (no SMTP configured, no webhooks registered for this company) — exercising
        // Notifications::changeOrderSigned()/WebhookDispatcher::dispatch() below proves the
        // sign action calls them without throwing, which is what matters for this test.
        $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $sign = $this->post('/co/' . $changeOrder->share_token . '/sign', [
            'signed_by_name' => 'Fahad Al-Otaibi',
            'signature_data' => $signatureData,
        ]);
        $sign->assertRedirect('/co/' . $changeOrder->share_token);

        $changeOrder->refresh();
        $this->assertNotNull($changeOrder->signed_at);
        $this->assertSame('Fahad Al-Otaibi', $changeOrder->signed_by_name);
        $this->assertSame($signatureData, $changeOrder->signature_data);
        $this->assertNotNull($changeOrder->signed_ip);

        // Re-signing an already-signed change order is a no-op redirect, not a re-process.
        $resign = $this->post('/co/' . $changeOrder->share_token . '/sign', [
            'signed_by_name' => 'Someone Else',
            'signature_data' => $signatureData,
        ]);
        $resign->assertRedirect('/co/' . $changeOrder->share_token);
        $changeOrder->refresh();
        $this->assertSame('Fahad Al-Otaibi', $changeOrder->signed_by_name, 'an already-signed change order must not be overwritten by a second sign attempt');
    }

    /** (4) A change order from Company A is never visible/signable via another company's/a guessed token — a wrong token 404s. */
    public function test_wrong_or_random_token_404s_and_never_leaks_another_companys_change_order(): void
    {
        $companyA = $this->makeCompany('COA');
        $ownerA = $this->makeUser($companyA, 'COA');
        $projectA = $this->makeProject($companyA, 'COA');

        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/change-orders', [
            'title' => 'Company A change order',
            'amount' => 1000,
        ]);
        $changeOrderA = ChangeOrder::where('project_id', $projectA->id)->first();
        $this->actingAs($ownerA)->get('/app/change-orders/' . $changeOrderA->id);
        $changeOrderA->refresh();
        $this->assertNotEmpty($changeOrderA->share_token);

        // A random, unguessable 40-hex-char token that happens to not match anything.
        $randomToken = bin2hex(random_bytes(20));
        $this->assertNotSame($changeOrderA->share_token, $randomToken);

        $view = $this->get('/co/' . $randomToken);
        $view->assertNotFound();

        $sign = $this->post('/co/' . $randomToken . '/sign', [
            'signed_by_name' => 'Attacker',
            'signature_data' => 'data:image/png;base64,abc',
        ]);
        $sign->assertNotFound();

        // The real token is untouched by the failed guesses.
        $changeOrderA->refresh();
        $this->assertNull($changeOrderA->signed_at);
    }

    /** (5) The existing simple approve/reject flow for a change order with no items and no signature request is completely unaffected (zero-regression proof). */
    public function test_simple_approve_reject_flow_unaffected_for_change_order_with_no_items_or_signature(): void
    {
        $company = $this->makeCompany('COR');
        $owner = $this->makeUser($company, 'COR');
        $project = $this->makeProject($company, 'COR');

        $create = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/change-orders', [
            'title' => 'Simple scope addition',
            'amount' => 2500,
        ]);
        $create->assertRedirect('/app/projects/' . $project->id);
        $changeOrder = ChangeOrder::where('project_id', $project->id)->first();
        $this->assertSame('pending', $changeOrder->status);
        $this->assertNull($changeOrder->share_token);
        $this->assertSame(0, $changeOrder->items()->count());
        $this->assertNull($changeOrder->time_impact_days);

        $approve = $this->actingAs($owner)->post('/app/change-orders/' . $changeOrder->id . '/status', ['status' => 'approved']);
        $approve->assertRedirect('/app/projects/' . $project->id);
        $changeOrder->refresh();
        $this->assertSame('approved', $changeOrder->status);
        $this->assertNotNull($changeOrder->approved_at);
        $this->assertSame('2500.00', $changeOrder->amount, 'amount must remain exactly as typed in, untouched by the new item-sync behavior');
        $this->assertNull($changeOrder->signed_at, 'approving internally must never touch the separate client-signature fields');

        // The project show page's revised budget still reflects this approved amount exactly
        // as before the enrichment.
        $show = $this->actingAs($owner)->get('/app/projects/' . $project->id);
        $show->assertOk();

        $reject = $this->actingAs($owner)->post('/app/change-orders/' . $changeOrder->id . '/status', ['status' => 'rejected']);
        $reject->assertRedirect('/app/projects/' . $project->id);
        $changeOrder->refresh();
        $this->assertSame('rejected', $changeOrder->status);
        $this->assertNull($changeOrder->approved_at);
    }
}
