<?php

namespace Tests\Feature;

use App\Models\BoqItem;
use App\Models\Company;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the client-facing e-signature flow added to PaymentCertificate (IPC),
 * mirroring EstimateSigned's own token-based sign flow (see ShareController's
 * estimate()/signEstimate() and this feature's paymentCertificate()/
 * signPaymentCertificate()). Wrapped in DatabaseTransactions — same convention
 * as RfqTest/EquipmentTest.
 */
class PaymentCertificateSignatureTest extends TestCase
{
    use DatabaseTransactions;

    /** See RfiSubmittalDocumentVersionTest's own docblock on why this reset is needed. */
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
                'payment_certificates' => true,
            ]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $role, string $tag): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => ucfirst($role) . " {$tag}",
            'email' => strtolower($role) . '-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
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

    private function makeBoqItem(Project $project, string $tag): BoqItem
    {
        return BoqItem::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'item_number' => '1.0',
            'description' => "BOQ line {$tag}",
            'uom' => 'LS',
            'qty' => 100,
            'unit_price' => 50,
            'total' => 5000,
            'sort_order' => 1,
        ]);
    }

    /** Creates a draft certificate exactly through the existing store() endpoint, claiming half the BOQ line. */
    private function makeDraftCertificate(User $owner, Project $project, BoqItem $boqItem): PaymentCertificate
    {
        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/payment-certificates', [
            'certificate_date' => now()->format('Y-m-d'),
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => [50],
        ]);
        return PaymentCertificate::where('project_id', $project->id)->orderByDesc('id')->first();
    }

    public function test_sending_for_signature_generates_unique_share_token_and_public_route_renders(): void
    {
        $company = $this->makeCompany('PCA');
        $owner = $this->makeUser($company, 'owner', 'PCA');
        $project = $this->makeProject($company, 'PCA');
        $boqItem = $this->makeBoqItem($project, 'PCA');
        $certificate = $this->makeDraftCertificate($owner, $project, $boqItem);
        $this->assertNotNull($certificate);
        $this->assertNull($certificate->share_token);

        // Opening the company-side show page is what generates the share_token (same
        // lazy-generation pattern as EstimateController::show()) — the "send for
        // signature" action this feature adds.
        $show = $this->actingAs($owner)->get('/app/payment-certificates/' . $certificate->id);
        $show->assertOk();

        $certificate->refresh();
        $this->assertNotEmpty($certificate->share_token);

        // A second certificate gets its own, different token.
        $boqItem2 = $this->makeBoqItem($project, 'PCA2');
        $certificate2 = $this->makeDraftCertificate($owner, $project, $boqItem2);
        $this->actingAs($owner)->get('/app/payment-certificates/' . $certificate2->id);
        $certificate2->refresh();
        $this->assertNotEmpty($certificate2->share_token);
        $this->assertNotSame($certificate->share_token, $certificate2->share_token);

        // The public, token-only route renders with no auth at all.
        $public = $this->get('/ipc/' . $certificate->share_token);
        $public->assertOk();
        $public->assertSee('Certificate #' . $certificate->certificate_number);
    }

    public function test_valid_signature_records_fields_and_fires_notification_and_webhook(): void
    {
        $company = $this->makeCompany('PCB');
        $owner = $this->makeUser($company, 'owner', 'PCB');
        $project = $this->makeProject($company, 'PCB');
        $boqItem = $this->makeBoqItem($project, 'PCB');
        $certificate = $this->makeDraftCertificate($owner, $project, $boqItem);
        $this->actingAs($owner)->get('/app/payment-certificates/' . $certificate->id);
        $certificate->refresh();

        // A webhook subscribed to the new event — proves WebhookDispatcher::dispatch()
        // actually reaches it (last_triggered_at gets set) without depending on a real
        // network round trip succeeding: an unreachable local port fails the curl call
        // near-instantly, which WebhookDispatcher already swallows.
        $webhook = Webhook::create([
            'company_id' => $company->id,
            'url' => 'http://127.0.0.1:1/hook',
            'secret' => 'whsec_test',
            'events' => json_encode(['payment_certificate.signed']),
            'is_active' => true,
        ]);

        $sign = $this->post('/ipc/' . $certificate->share_token . '/sign', [
            'signed_by_name' => 'Fahad Al-Otaibi',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]);
        $sign->assertRedirect('/ipc/' . $certificate->share_token);

        $certificate->refresh();
        $this->assertNotNull($certificate->signed_at);
        $this->assertSame('Fahad Al-Otaibi', $certificate->signed_by_name);
        $this->assertStringStartsWith('data:image/', (string) $certificate->signature_data);
        $this->assertNotEmpty($certificate->signed_ip);

        $webhook->refresh();
        $this->assertNotNull($webhook->last_triggered_at);

        // The signed certificate's public page now shows the signed state instead of the form.
        $public = $this->get('/ipc/' . $certificate->share_token);
        $public->assertOk();
        $public->assertSee('Fahad Al-Otaibi');
    }

    public function test_invalid_token_404s_on_both_public_routes(): void
    {
        $get = $this->get('/ipc/not-a-real-token');
        $get->assertNotFound();

        $post = $this->post('/ipc/not-a-real-token/sign', [
            'signed_by_name' => 'Someone',
            'signature_data' => 'data:image/png;base64,abc',
        ]);
        $post->assertNotFound();
    }

    public function test_company_a_certificate_not_reachable_via_company_b_session(): void
    {
        $companyA = $this->makeCompany('PCC1');
        $ownerA = $this->makeUser($companyA, 'owner', 'PCC1');
        $projectA = $this->makeProject($companyA, 'PCC1');
        $boqItemA = $this->makeBoqItem($projectA, 'PCC1');
        $certificateA = $this->makeDraftCertificate($ownerA, $projectA, $boqItemA);

        $companyB = $this->makeCompany('PCC2');
        $ownerB = $this->makeUser($companyB, 'owner', 'PCC2');

        // Standard tenant isolation: Company B's session can never load Company A's
        // certificate via the company-side show route — same 404-on-foreign-id pattern
        // as every other findOwned() helper in this app.
        $response = $this->actingAs($ownerB)->get('/app/payment-certificates/' . $certificateA->id);
        $response->assertNotFound();
    }

    public function test_internal_certify_flow_is_unaffected_by_the_signature_feature(): void
    {
        $company = $this->makeCompany('PCD');
        $owner = $this->makeUser($company, 'owner', 'PCD');
        $project = $this->makeProject($company, 'PCD');
        $boqItem = $this->makeBoqItem($project, 'PCD');
        $certificate = $this->makeDraftCertificate($owner, $project, $boqItem);
        $this->assertSame('draft', $certificate->status);

        // Never generating/opening a share link at all — certify() still works exactly
        // as before this feature existed, proving zero regression on the pre-existing
        // internal approval cycle.
        $certify = $this->actingAs($owner)->post('/app/payment-certificates/' . $certificate->id . '/certify');
        $certify->assertRedirect('/app/payment-certificates/' . $certificate->id);

        $certificate->refresh();
        $this->assertSame('certified', $certificate->status);
        $this->assertNotNull($certificate->certified_at);
        $this->assertNotNull($certificate->invoice_id);
        // The signature fields this feature added are untouched by certify().
        $this->assertNull($certificate->share_token);
        $this->assertNull($certificate->signed_at);
    }
}
