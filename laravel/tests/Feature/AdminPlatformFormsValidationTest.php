<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EstimateTemplate;
use App\Models\EstimateTemplateItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Plan;
use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateQualityTier;
use App\Models\QuickEstimateRegion;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the server-side validation added in this batch to the platform-admin-only forms
 * (not company-side) that previously only cast numeric/structured input with (float)/(int)
 * and never rejected an out-of-range or malformed value: Plans, Pages, Page Sections,
 * Tenders, Estimate Template items, the Quick Estimate pricing-config screens, and the
 * company profile editor in CompanyController. Mirrors the shape of
 * QuickEstimateNumericValidationTest/ZakatNumericValidationTest for the company-side
 * equivalents. Wrapped in DatabaseTransactions, same convention as those tests.
 */
class AdminPlatformFormsValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'platform-forms-admin-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    // ---------------- Plans ----------------

    public function test_plan_store_rejects_a_malformed_slug(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/plans', [
            'name' => 'Starter',
            'slug' => 'Not A Slug!',
            'max_users' => 5,
            'max_projects' => 10,
        ]);

        $response->assertRedirect('/admin/plans/create');
        $this->assertSame(0, Plan::where('name', 'Starter')->count());
    }

    public function test_plan_store_rejects_a_duplicate_slug(): void
    {
        $admin = $this->superAdmin();
        Plan::create(['slug' => 'duplicate-slug-test', 'name' => 'Existing Plan']);

        $response = $this->actingAs($admin)->post('/admin/plans', [
            'name' => 'New Plan',
            'slug' => 'duplicate-slug-test',
            'max_users' => 5,
            'max_projects' => 10,
        ]);

        $response->assertRedirect('/admin/plans/create');
        $this->assertSame(1, Plan::where('slug', 'duplicate-slug-test')->count());
    }

    public function test_plan_store_rejects_max_users_above_bound(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/plans', [
            'name' => 'Huge Plan',
            'slug' => 'huge-plan-'.uniqid(),
            'max_users' => '99999999999999',
            'max_projects' => 10,
        ]);

        $response->assertRedirect('/admin/plans/create');
        $this->assertSame(0, Plan::where('name', 'Huge Plan')->count());
    }

    public function test_plan_store_rejects_non_numeric_price(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/plans', [
            'name' => 'Bad Price Plan',
            'slug' => 'bad-price-'.uniqid(),
            'price_monthly' => 'not-a-number',
            'max_users' => 5,
            'max_projects' => 10,
        ]);

        $response->assertRedirect('/admin/plans/create');
        $this->assertSame(0, Plan::where('name', 'Bad Price Plan')->count());
    }

    public function test_plan_store_accepts_a_valid_payload(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/plans', [
            'name' => 'Professional',
            'name_ar' => 'احترافي',
            'slug' => 'professional-'.uniqid(),
            'price_monthly' => '299.00',
            'max_users' => '20',
            'max_projects' => '50',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/plans');
        $this->assertSame(1, Plan::where('name', 'Professional')->count());
    }

    public function test_plan_update_scopes_to_its_own_id_and_rejects_slug_collision_with_another_plan(): void
    {
        $admin = $this->superAdmin();
        $planA = Plan::create(['slug' => 'plan-a-'.uniqid(), 'name' => 'Plan A']);
        $planB = Plan::create(['slug' => 'plan-b-'.uniqid(), 'name' => 'Plan B']);

        $response = $this->actingAs($admin)->post('/admin/plans/'.$planB->id, [
            'name' => 'Plan B Renamed',
            'slug' => $planA->slug,
            'max_users' => 5,
            'max_projects' => 10,
        ]);

        $response->assertRedirect('/admin/plans/'.$planB->id.'/edit');
        $this->assertSame('Plan B', $planB->fresh()->name);
    }

    // ---------------- Pages ----------------

    public function test_page_store_rejects_a_blank_arabic_title(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/pages', [
            'slug' => 'careers',
            'title_en' => 'Careers',
            'title_ar' => '',
        ]);

        $response->assertRedirect('/admin/pages/create');
        $this->assertSame(0, Page::where('slug', 'careers')->count());
    }

    public function test_page_store_rejects_nav_order_above_bound(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/pages', [
            'slug' => 'careers-'.uniqid(),
            'title_en' => 'Careers',
            'title_ar' => 'وظائف',
            'nav_order' => '999999999',
        ]);

        $response->assertRedirect('/admin/pages/create');
    }

    public function test_page_store_accepts_a_valid_payload(): void
    {
        $admin = $this->superAdmin();
        $slug = 'careers-'.uniqid();

        $response = $this->actingAs($admin)->post('/admin/pages', [
            'slug' => $slug,
            'title_en' => 'Careers',
            'title_ar' => 'وظائف',
            'nav_order' => '3',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Page::where('slug', $slug)->count());
    }

    // ---------------- Page sections ----------------

    public function test_section_store_rejects_a_page_slug_that_does_not_exist(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/sections', [
            'page_slug' => 'totally-made-up-page',
            'section_type' => 'text_block',
            'sort_order' => '0',
        ]);

        $response->assertRedirect('/admin/sections');
        $this->assertSame(0, PageSection::where('page_slug', 'totally-made-up-page')->count());
    }

    public function test_section_store_accepts_an_existing_custom_page_slug(): void
    {
        $admin = $this->superAdmin();
        $page = Page::create([
            'slug' => 'custom-page-'.uniqid(),
            'title_en' => 'Custom',
            'title_ar' => 'مخصصة',
        ]);

        $response = $this->actingAs($admin)->post('/admin/sections', [
            'page_slug' => $page->slug,
            'section_type' => 'text_block',
            'sort_order' => '0',
        ]);

        $response->assertRedirect('/admin/sections');
        $this->assertSame(1, PageSection::where('page_slug', $page->slug)->count());
    }

    public function test_section_store_accepts_one_of_the_fixed_marketing_pages(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/sections', [
            'page_slug' => 'home',
            'section_type' => 'text_block',
            'sort_order' => '0',
        ]);

        $response->assertRedirect('/admin/sections');
        $this->assertSame(1, PageSection::where('page_slug', 'home')->count());
    }

    public function test_section_store_caps_feature_grid_items_to_the_configured_maximum(): void
    {
        $admin = $this->superAdmin();
        $items = [];
        for ($i = 0; $i < 20; $i++) {
            $items[] = ['title_en' => "Item {$i}"];
        }

        $response = $this->actingAs($admin)->post('/admin/sections', [
            'page_slug' => 'home',
            'section_type' => 'feature_grid',
            'sort_order' => '0',
            'items' => $items,
        ]);

        $response->assertRedirect('/admin/sections');
        $section = PageSection::where('page_slug', 'home')->where('section_type', 'feature_grid')->first();
        $this->assertNotNull($section);
        $this->assertLessThanOrEqual(6, count($section->items));
    }

    // ---------------- Tenders ----------------

    public function test_tender_store_rejects_a_malformed_deadline(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/tenders', [
            'title_en' => 'Road Construction Tender',
            'entity_name_en' => 'Ministry of Transport',
            'submission_deadline' => 'not-a-date',
        ]);

        $response->assertRedirect('/admin/tenders/create');
        $this->assertSame(0, Tender::where('title_en', 'Road Construction Tender')->count());
    }

    public function test_tender_store_rejects_estimated_value_above_bound(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/tenders', [
            'title_en' => 'Giga Project Tender',
            'entity_name_en' => 'NEOM',
            'submission_deadline' => now()->addMonth()->format('Y-m-d'),
            'estimated_value_sar' => '9999999999999999',
        ]);

        $response->assertRedirect('/admin/tenders/create');
        $this->assertSame(0, Tender::where('title_en', 'Giga Project Tender')->count());
    }

    public function test_tender_store_accepts_a_valid_payload(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/tenders', [
            'title_en' => 'Valid Tender '.uniqid(),
            'entity_name_en' => 'Ministry of Municipal Affairs',
            'submission_deadline' => now()->addMonth()->format('Y-m-d'),
            'estimated_value_sar' => '5000000',
            'location_city' => 'Riyadh',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Tender::where('entity_name_en', 'Ministry of Municipal Affairs')->count());
    }

    // ---------------- Estimate template items ----------------

    public function test_template_item_store_rejects_unit_cost_above_bound(): void
    {
        $admin = $this->superAdmin();
        $template = EstimateTemplate::create(['name_en' => 'Villa', 'name_ar' => 'فيلا']);

        $response = $this->actingAs($admin)->post('/admin/estimate-templates/'.$template->id.'/items', [
            'description_en' => 'Concrete slab',
            'unit_cost' => '999999999999',
        ]);

        $response->assertRedirect('/admin/estimate-templates/'.$template->id.'/items');
        $this->assertSame(0, EstimateTemplateItem::where('template_id', $template->id)->count());
    }

    public function test_template_item_update_rejects_an_item_belonging_to_another_template(): void
    {
        $admin = $this->superAdmin();
        $templateA = EstimateTemplate::create(['name_en' => 'Villa', 'name_ar' => 'فيلا']);
        $templateB = EstimateTemplate::create(['name_en' => 'Apartment', 'name_ar' => 'شقة']);
        $item = EstimateTemplateItem::create([
            'template_id' => $templateA->id,
            'section_title_en' => 'Foundations',
            'section_title_ar' => 'الأساسات',
            'description_en' => 'Excavation',
            'description_ar' => 'حفر',
        ]);

        $response = $this->actingAs($admin)->post(
            '/admin/estimate-templates/'.$templateB->id.'/items/'.$item->id,
            ['description_en' => 'Hijacked description']
        );

        $response->assertStatus(404);
        $this->assertSame('Excavation', $item->fresh()->description_en);
    }

    public function test_template_item_store_accepts_a_valid_payload(): void
    {
        $admin = $this->superAdmin();
        $template = EstimateTemplate::create(['name_en' => 'Villa', 'name_ar' => 'فيلا']);

        $response = $this->actingAs($admin)->post('/admin/estimate-templates/'.$template->id.'/items', [
            'description_en' => 'Concrete slab',
            'description_ar' => 'بلاطة خرسانية',
            'default_qty' => '10',
            'unit_cost' => '150.50',
        ]);

        $response->assertRedirect('/admin/estimate-templates/'.$template->id.'/items');
        $this->assertSame(1, EstimateTemplateItem::where('template_id', $template->id)->count());
    }

    // ---------------- Quick Estimate pricing config ----------------

    public function test_region_store_rejects_multiplier_above_bound(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/quick-estimate/regions', [
            'name_en' => 'Riyadh Region',
            'name_ar' => 'منطقة الرياض',
            'multiplier' => '50000',
        ]);

        $response->assertRedirect('/admin/quick-estimate/regions');
        $this->assertSame(0, QuickEstimateRegion::where('name_en', 'Riyadh Region')->count());
    }

    public function test_region_store_accepts_a_valid_payload(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/quick-estimate/regions', [
            'name_en' => 'Jeddah Region '.uniqid(),
            'name_ar' => 'منطقة جدة',
            'price_per_sqm' => '1200',
            'multiplier' => '1.1',
        ]);

        $response->assertRedirect('/admin/quick-estimate/regions');
        $this->assertSame(1, QuickEstimateRegion::where('name_ar', 'منطقة جدة')->count());
    }

    public function test_foundation_store_rejects_non_numeric_price_per_sqm(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/quick-estimate/foundations', [
            'name_en' => 'Raft Foundation',
            'name_ar' => 'أساس حصيرة',
            'price_per_sqm' => 'not-a-number',
        ]);

        $response->assertRedirect('/admin/quick-estimate/foundations');
        $this->assertSame(0, QuickEstimateFoundation::where('name_en', 'Raft Foundation')->count());
    }

    public function test_addon_store_rejects_unit_price_above_bound(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/quick-estimate/addons', [
            'name_en' => 'Swimming Pool',
            'name_ar' => 'حمام سباحة',
            'unit_price' => '999999999999',
        ]);

        $response->assertRedirect('/admin/quick-estimate/addons');
        $this->assertSame(0, QuickEstimateAddon::where('name_en', 'Swimming Pool')->count());
    }

    public function test_quality_tier_store_rejects_a_blank_name(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/quick-estimate/quality-tiers', [
            'name_en' => '',
            'name_ar' => '',
            'multiplier' => '1',
        ]);

        $response->assertRedirect('/admin/quick-estimate/quality-tiers');
        $this->assertSame(0, QuickEstimateQualityTier::count());
    }

    // ---------------- Company profile (admin override) ----------------

    private function makeCompany(string $tag): Company
    {
        return Company::create([
            'name' => "Acme {$tag}",
            'email' => strtolower($tag).'-'.uniqid().'@example.com',
        ]);
    }

    public function test_company_profile_update_rejects_an_invalid_email(): void
    {
        $admin = $this->superAdmin();
        $company = $this->makeCompany('BadEmail');

        $response = $this->actingAs($admin)->post('/admin/companies/'.$company->id.'/profile', [
            'name' => $company->name,
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect('/admin/companies/'.$company->id);
        $this->assertNotSame('not-an-email', $company->fresh()->email);
    }

    public function test_company_profile_update_rejects_a_malformed_vat_number(): void
    {
        $admin = $this->superAdmin();
        $company = $this->makeCompany('BadVat');

        $response = $this->actingAs($admin)->post('/admin/companies/'.$company->id.'/profile', [
            'name' => $company->name,
            'email' => $company->email,
            'vat_number' => '12345',
        ]);

        $response->assertRedirect('/admin/companies/'.$company->id);
        $this->assertSame('', (string) $company->fresh()->vat_number);
    }

    public function test_company_profile_update_rejects_an_invalid_team_size(): void
    {
        $admin = $this->superAdmin();
        $company = $this->makeCompany('BadTeamSize');

        $response = $this->actingAs($admin)->post('/admin/companies/'.$company->id.'/profile', [
            'name' => $company->name,
            'email' => $company->email,
            'team_size' => 'a-million',
        ]);

        $response->assertRedirect('/admin/companies/'.$company->id);
        $this->assertNotSame('a-million', $company->fresh()->team_size);
    }

    public function test_company_profile_update_accepts_a_valid_payload_and_persists_it(): void
    {
        $admin = $this->superAdmin();
        $company = $this->makeCompany('GoodProfile');

        $response = $this->actingAs($admin)->post('/admin/companies/'.$company->id.'/profile', [
            'name' => 'Acme Renamed',
            'email' => $company->email,
            'city' => 'Riyadh',
            'team_size' => '6-15',
            'cr_number' => '1010101010',
            'vat_number' => '300000000000003',
        ]);

        $response->assertRedirect('/admin/companies/'.$company->id);
        $fresh = $company->fresh();
        $this->assertSame('Acme Renamed', $fresh->name);
        $this->assertSame('6-15', $fresh->team_size);
        $this->assertSame('300000000000003', $fresh->vat_number);
    }

    public function test_company_plan_update_rejects_a_nonexistent_plan_id(): void
    {
        $admin = $this->superAdmin();
        $company = $this->makeCompany('NoSuchPlan');

        $response = $this->actingAs($admin)->post('/admin/companies/'.$company->id.'/plan', [
            'plan_id' => '999999',
            'billing_cycle' => 'monthly',
        ]);

        $response->assertRedirect('/admin/companies/'.$company->id);
        $this->assertNull($company->fresh()->plan_id);
    }
}
