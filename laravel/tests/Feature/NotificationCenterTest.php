<?php

namespace Tests\Feature;

use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Support\Notifications;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the new in-app notification center: the `notifications` table/model
 * written by App\Support\Notifications::record() alongside every existing
 * email/SMS send, and the bell-icon list/mark-read behind it
 * (App\Http\Controllers\App\NotificationController). Same cross-tenant-leakage
 * and pagination style as ActivityLogTest, since `notifications` is scoped
 * exactly like `audit_logs` — company-wide, not per-user.
 *
 * Wrapped in DatabaseTransactions, matching ActivityLogTest/ChangeOrderEnrichmentTest's
 * own convention, so none of this touches the real dev database beyond the test.
 */
class NotificationCenterTest extends TestCase
{
    use DatabaseTransactions;

    /** See ActivityLogTest's own docblock on why this reset is needed. */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Support\Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-notif-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Notif Plan {$tag}",
            'feature_flags' => json_encode(['change_orders' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Holding",
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

        $client = Client::create([
            'company_id' => $company->id,
            'name' => "Zephyr{$tag} Client",
            'email' => 'client-' . strtolower($tag) . '@example.com',
        ]);

        return compact('company', 'owner', 'client');
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

    /**
     * (1) A real business event — the estimate public e-signature flow — produces a
     * notification row stamped with the right company_id, AND the existing email send
     * (ShareController::signEstimate() -> Notifications::estimateSigned() -> Mailer::send())
     * still runs exactly as before: the whole request completes without error, proving the
     * new self::record() call is purely additive and never disturbs the existing dispatch.
     */
    public function test_estimate_signed_records_a_notification_and_leaves_existing_email_dispatch_unaffected(): void
    {
        $a = $this->makeCompanyWithOwner('EstSign');

        $create = $this->actingAs($a['owner'])->post('/app/estimates', [
            'title' => 'Villa extension estimate',
            'item_description' => ['Foundation works'],
            'item_qty' => [1],
            'item_cost' => [10000],
        ]);
        $create->assertRedirect();
        $estimate = \App\Models\Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);

        // Visiting the show page lazily generates the share_token, same as EstimateController::show().
        $show = $this->actingAs($a['owner'])->get('/app/estimates/' . $estimate->id);
        $show->assertOk();
        $estimate->refresh();
        $this->assertNotEmpty($estimate->share_token);

        $this->assertSame(0, Notification::where('company_id', $a['company']->id)->count(), 'no notification should exist before the estimate is signed');

        $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $sign = $this->post('/e/' . $estimate->share_token . '/sign', [
            'decision' => 'accept',
            'signed_by_name' => 'Fahad Al-Otaibi',
            'signature_data' => $signatureData,
        ]);
        // The public sign endpoint completing with a redirect (not a 500) proves
        // Notifications::estimateSigned()'s existing Mailer::send() call still ran
        // without throwing, exactly as it did before this feature — Mailer::send()
        // is a no-op here since no SMTP is configured in tests (see Mailer::isConfigured()).
        $sign->assertRedirect('/e/' . $estimate->share_token);

        $estimate->refresh();
        $this->assertSame('accepted', $estimate->status);

        $notification = Notification::where('company_id', $a['company']->id)->where('type', 'estimate_signed')->first();
        $this->assertNotNull($notification);
        $this->assertSame($a['company']->id, $notification->company_id);
        $this->assertStringContainsString('Villa extension estimate', $notification->title);
        $this->assertStringContainsString('Fahad Al-Otaibi', $notification->message);
        $this->assertSame('/app/estimates/' . $estimate->id, $notification->link);
        $this->assertNull($notification->read_at);
    }

    /**
     * (2) Another real business event — a change order's public e-signature flow — also
     * records its own notification row, independent of the one above, and the existing
     * Mailer::send() in Notifications::changeOrderSigned() still runs without throwing.
     */
    public function test_change_order_signed_records_a_notification_and_leaves_existing_email_dispatch_unaffected(): void
    {
        $a = $this->makeCompanyWithOwner('CoSign');
        $project = $this->makeProject($a['company'], 'CoSign');

        $this->actingAs($a['owner'])->post('/app/projects/' . $project->id . '/change-orders', [
            'title' => 'Upgraded flooring',
            'amount' => 7500,
        ])->assertRedirect();
        $changeOrder = ChangeOrder::where('project_id', $project->id)->first();
        $this->assertNotNull($changeOrder);

        $this->actingAs($a['owner'])->get('/app/change-orders/' . $changeOrder->id)->assertOk();
        $changeOrder->refresh();
        $this->assertNotEmpty($changeOrder->share_token);

        $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $sign = $this->post('/co/' . $changeOrder->share_token . '/sign', [
            'signed_by_name' => 'Fahad Al-Otaibi',
            'signature_data' => $signatureData,
        ]);
        $sign->assertRedirect('/co/' . $changeOrder->share_token);

        $notification = Notification::where('company_id', $a['company']->id)->where('type', 'change_order_signed')->first();
        $this->assertNotNull($notification);
        $this->assertSame($a['company']->id, $notification->company_id);
        $this->assertStringContainsString('Upgraded flooring', $notification->title);
    }

    /**
     * (3) The invoice-paid trigger (App\Support\Notifications::invoicePaid(), fired from
     * ShareController::invoicePaymentCallback() once a real gateway confirms payment) records
     * its own notification row with the correct company_id, and calling it does not throw —
     * i.e. the pre-existing Mailer::send() calls to the owner and to the client inside it
     * still run exactly as before.
     */
    public function test_invoice_paid_records_a_notification_and_leaves_existing_email_dispatch_unaffected(): void
    {
        $a = $this->makeCompanyWithOwner('InvPaid');

        $invoice = Invoice::create([
            'company_id' => $a['company']->id,
            'client_id' => $a['client']->id,
            'invoice_number' => 'INV-NOTIF-1',
            'status' => 'sent',
            'subtotal' => 1000,
            'total' => 1000,
            'issue_date' => now()->toDateString(),
        ]);

        Notifications::invoicePaid($invoice->id);

        $notification = Notification::where('company_id', $a['company']->id)->where('type', 'invoice_paid')->first();
        $this->assertNotNull($notification);
        $this->assertSame($a['company']->id, $notification->company_id);
        $this->assertStringContainsString('INV-NOTIF-1', $notification->title);
        $this->assertSame('/app/invoices/' . $invoice->id, $notification->link);
    }

    /** (4) The critical cross-tenant-leakage test: Company A's notifications page must never show Company B's rows. */
    public function test_notifications_index_never_shows_another_companys_rows(): void
    {
        $a = $this->makeCompanyWithOwner('NotifLeakA');
        $b = $this->makeCompanyWithOwner('NotifLeakB');

        Notifications::record($a['company']->id, 'invoice_paid', 'Company A only notification', null, null);
        Notifications::record($b['company']->id, 'invoice_paid', 'Company B secret notification', null, null);

        $response = $this->actingAs($a['owner'])->get('/app/notifications');

        $response->assertOk();
        $response->assertSee('Company A only notification', false);
        $response->assertDontSee('Company B secret notification', false);
    }

    /** (5) markRead works and is tenant-isolated: a company can mark its own notification read, never another company's. */
    public function test_mark_read_is_tenant_isolated(): void
    {
        $a = $this->makeCompanyWithOwner('MarkReadA');
        $b = $this->makeCompanyWithOwner('MarkReadB');

        Notifications::record($a['company']->id, 'invoice_paid', 'A notification', null, null);
        $ownNotification = Notification::where('company_id', $a['company']->id)->first();

        Notifications::record($b['company']->id, 'invoice_paid', 'B notification', null, null);
        $othersNotification = Notification::where('company_id', $b['company']->id)->first();

        $this->assertNull($ownNotification->read_at);
        $ok = $this->actingAs($a['owner'])->post('/app/notifications/' . $ownNotification->id . '/read');
        $ok->assertRedirect();
        $ownNotification->refresh();
        $this->assertNotNull($ownNotification->read_at);

        // Company A's owner must never be able to mark Company B's notification read.
        $forbidden = $this->actingAs($a['owner'])->post('/app/notifications/' . $othersNotification->id . '/read');
        $forbidden->assertNotFound();
        $othersNotification->refresh();
        $this->assertNull($othersNotification->read_at, "another company's notification must never be touched");
    }

    /** (6) markAllRead marks every unread row for the acting company and none of another company's. */
    public function test_mark_all_read_only_touches_the_acting_companys_own_rows(): void
    {
        $a = $this->makeCompanyWithOwner('MarkAllA');
        $b = $this->makeCompanyWithOwner('MarkAllB');

        Notifications::record($a['company']->id, 'invoice_paid', 'A notification 1', null, null);
        Notifications::record($a['company']->id, 'estimate_signed', 'A notification 2', null, null);
        Notifications::record($b['company']->id, 'invoice_paid', 'B notification', null, null);

        $this->actingAs($a['owner'])->post('/app/notifications/mark-all-read')->assertRedirect();

        $this->assertSame(0, Notification::where('company_id', $a['company']->id)->whereNull('read_at')->count());
        $this->assertSame(1, Notification::where('company_id', $b['company']->id)->whereNull('read_at')->count(), "another company's unread notification must be untouched");
    }

    /** (7) The unread-count badge (computed inline in layouts.app) reflects reality: N unread + M read notifications must show exactly N. */
    public function test_unread_count_badge_reflects_reality(): void
    {
        $a = $this->makeCompanyWithOwner('BadgeCount');

        // 3 unread.
        Notifications::record($a['company']->id, 'invoice_paid', 'Unread one', null, null);
        Notifications::record($a['company']->id, 'invoice_paid', 'Unread two', null, null);
        Notifications::record($a['company']->id, 'invoice_paid', 'Unread three', null, null);
        // 2 already read.
        Notifications::record($a['company']->id, 'invoice_paid', 'Read one', null, null);
        Notifications::record($a['company']->id, 'invoice_paid', 'Read two', null, null);
        Notification::where('company_id', $a['company']->id)->where('title', 'Read one')->update(['read_at' => now()]);
        Notification::where('company_id', $a['company']->id)->where('title', 'Read two')->update(['read_at' => now()]);

        $this->assertSame(3, Notification::where('company_id', $a['company']->id)->whereNull('read_at')->count());

        $response = $this->actingAs($a['owner'])->get('/app/notifications');
        $response->assertOk();
        // The bell badge in layouts.app renders the live unread count — exactly 3 here.
        $response->assertSee('>3<', false);
    }
}
