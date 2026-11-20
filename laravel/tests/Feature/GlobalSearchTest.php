<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers Task #51 (global cross-entity search): most importantly, that a
 * company user's search NEVER returns another company's data, plus that a
 * same-company multi-entity search renders correctly in both locales and
 * that the 2-character minimum actually stops a 1-character query from
 * searching at all. Wrapped in DatabaseTransactions so none of this touches
 * the real dev sqlite database beyond the test.
 */
class GlobalSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithData(string $tag): array
    {
        $company = Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag) . '@example.com',
        ]);

        $user = User::create([
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

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => "Zephyr{$tag} Tower",
            'status' => 'planning',
            'budget' => 1000,
        ]);

        $estimate = Estimate::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'title' => "Zephyr{$tag} Estimate",
            'status' => 'draft',
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => "ZEPHYR{$tag}-001",
            'status' => 'unpaid',
            'total' => 500,
        ]);

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => "Zephyr{$tag} Supply Co",
        ]);

        return compact('company', 'user', 'client', 'project', 'estimate', 'invoice', 'supplier');
    }

    public function test_search_never_leaks_another_companys_data(): void
    {
        $a = $this->makeCompanyWithData('A');
        $b = $this->makeCompanyWithData('B');

        // Company A's user searches for a term that only exists in Company B's data.
        $response = $this->actingAs($a['user'])->get('/app/search?q=ZephyrB');

        $response->assertOk();
        // The search box legitimately echoes the typed query ("ZephyrB") back into its
        // own <input value>, so the real assertion is that none of Company B's actual
        // entity names/numbers (which only coincidentally share that substring) appear.
        $response->assertDontSee($b['client']->name);
        $response->assertDontSee($b['project']->name);
        $response->assertDontSee($b['estimate']->title);
        $response->assertDontSee($b['invoice']->invoice_number);
        $response->assertDontSee($b['supplier']->name);
        $response->assertSee('No results found', false);
    }

    public function test_search_never_leaks_another_companys_data_via_json_endpoint(): void
    {
        $a = $this->makeCompanyWithData('A2');
        $b = $this->makeCompanyWithData('B2');

        $response = $this->actingAs($a['user'])->getJson('/app/search?format=json&q=ZephyrB2');

        $response->assertOk();
        $json = $response->json();
        $this->assertSame(0, $json['total']);
        foreach ($json['groups'] as $group) {
            $this->assertEmpty($group['items']);
        }
    }

    public function test_same_company_search_returns_grouped_multi_entity_results_with_working_links(): void
    {
        $a = $this->makeCompanyWithData('C');

        $response = $this->actingAs($a['user'])->get('/app/search?q=ZephyrC');

        $response->assertOk();
        $response->assertSee($a['project']->name, false);
        $response->assertSee($a['client']->name, false);
        $response->assertSee($a['estimate']->title, false);
        $response->assertSee($a['invoice']->invoice_number, false);
        $response->assertSee($a['supplier']->name, false);

        $response->assertSee('/app/projects/' . $a['project']->id, false);
        $response->assertSee('/app/clients/' . $a['client']->id . '/edit', false);
        $response->assertSee('/app/estimates/' . $a['estimate']->id, false);
        $response->assertSee('/app/invoices/' . $a['invoice']->id, false);
        $response->assertSee('/app/suppliers/' . $a['supplier']->id . '/edit', false);
    }

    public function test_search_results_render_in_arabic_locale_too(): void
    {
        $a = $this->makeCompanyWithData('D');

        $response = $this->actingAs($a['user'])->get('/app/search?q=ZephyrD&lang=ar');

        $response->assertOk();
        $response->assertSee($a['project']->name, false);
        $response->assertSee('dir="rtl"', false);
    }

    public function test_one_character_query_does_not_run_a_search(): void
    {
        $a = $this->makeCompanyWithData('E');

        // "Z" alone matches every Zephyr* record above, but is below the 2-char
        // minimum, so it must come back empty rather than table-scanning everything.
        $response = $this->actingAs($a['user'])->get('/app/search?q=Z');

        $response->assertOk();
        $response->assertDontSee($a['project']->name, false);
        $response->assertSee('2', false);
    }

    public function test_one_character_query_is_blocked_on_the_json_endpoint_too(): void
    {
        $a = $this->makeCompanyWithData('F');

        $response = $this->actingAs($a['user'])->getJson('/app/search?format=json&q=Z');

        $response->assertOk();
        $json = $response->json();
        $this->assertSame(0, $json['total']);
    }

    public function test_admin_search_covers_companies_and_platform_support_tickets(): void
    {
        $company = Company::create([
            'name' => 'ZephyrG Holding',
            'email' => 'zephyrg@example.com',
        ]);

        $admin = User::create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin-g@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $ticket = SupportTicket::create([
            'channel' => 'platform',
            'company_id' => $company->id,
            'subject' => 'ZephyrG login issue',
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->get('/admin/search?q=ZephyrG');

        $response->assertOk();
        $response->assertSee($company->name, false);
        $response->assertSee($ticket->subject, false);
        $response->assertSee('/admin/companies/' . $company->id, false);
        $response->assertSee('/admin/support/' . $ticket->id, false);
    }
}
