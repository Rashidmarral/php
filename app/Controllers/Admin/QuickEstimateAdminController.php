<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\QuickEstimate;
use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateRegion;

class QuickEstimateAdminController extends Controller
{
    public function index(): void
    {
        self::redirect('/admin/quick-estimate/regions');
    }

    // ---------------- Regions ----------------

    public function regions(): void
    {
        $this->view('admin/quick-estimate/regions', [
            'pageTitle' => 'Quick Estimate — Regions',
            'regions' => QuickEstimateRegion::all('sort_order ASC'),
        ], 'layouts/admin');
    }

    public function storeRegion(): void
    {
        $this->verifyCsrf();
        QuickEstimateRegion::create([
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'price_per_sqm' => (float) $this->input('price_per_sqm', 0),
            'multiplier' => (float) $this->input('multiplier', 1),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => 1,
        ]);
        $this->flash('success', 'Region added.');
        self::redirect('/admin/quick-estimate/regions');
    }

    public function updateRegion(string $id): void
    {
        $this->verifyCsrf();
        QuickEstimateRegion::update((int) $id, [
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'price_per_sqm' => (float) $this->input('price_per_sqm', 0),
            'multiplier' => (float) $this->input('multiplier', 1),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => $this->input('is_active') ? 1 : 0,
        ]);
        $this->flash('success', 'Region updated.');
        self::redirect('/admin/quick-estimate/regions');
    }

    public function destroyRegion(string $id): void
    {
        $this->verifyCsrf();
        QuickEstimateRegion::delete((int) $id);
        $this->flash('success', 'Region removed.');
        self::redirect('/admin/quick-estimate/regions');
    }

    // ---------------- Foundations ----------------

    public function foundations(): void
    {
        $this->view('admin/quick-estimate/foundations', [
            'pageTitle' => 'Quick Estimate — Foundation Types',
            'foundations' => QuickEstimateFoundation::all('sort_order ASC'),
        ], 'layouts/admin');
    }

    public function storeFoundation(): void
    {
        $this->verifyCsrf();
        QuickEstimateFoundation::create([
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'description_en' => $this->input('description_en', ''),
            'description_ar' => $this->input('description_ar', ''),
            'price_per_sqm' => (float) $this->input('price_per_sqm', 0),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => 1,
        ]);
        $this->flash('success', 'Foundation type added.');
        self::redirect('/admin/quick-estimate/foundations');
    }

    public function updateFoundation(string $id): void
    {
        $this->verifyCsrf();
        QuickEstimateFoundation::update((int) $id, [
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'description_en' => $this->input('description_en', ''),
            'description_ar' => $this->input('description_ar', ''),
            'price_per_sqm' => (float) $this->input('price_per_sqm', 0),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => $this->input('is_active') ? 1 : 0,
        ]);
        $this->flash('success', 'Foundation type updated.');
        self::redirect('/admin/quick-estimate/foundations');
    }

    public function destroyFoundation(string $id): void
    {
        $this->verifyCsrf();
        QuickEstimateFoundation::delete((int) $id);
        $this->flash('success', 'Foundation type removed.');
        self::redirect('/admin/quick-estimate/foundations');
    }

    // ---------------- Add-ons ----------------

    public function addons(): void
    {
        $this->view('admin/quick-estimate/addons', [
            'pageTitle' => 'Quick Estimate — Add-ons',
            'addons' => QuickEstimateAddon::all('sort_order ASC'),
        ], 'layouts/admin');
    }

    public function storeAddon(): void
    {
        $this->verifyCsrf();
        QuickEstimateAddon::create([
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'description_en' => $this->input('description_en', ''),
            'description_ar' => $this->input('description_ar', ''),
            'unit_price' => (float) $this->input('unit_price', 0),
            'unit_type' => $this->input('unit_type', 'sqm'),
            'is_pro' => $this->input('is_pro') ? 1 : 0,
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => 1,
        ]);
        $this->flash('success', 'Add-on added.');
        self::redirect('/admin/quick-estimate/addons');
    }

    public function updateAddon(string $id): void
    {
        $this->verifyCsrf();
        QuickEstimateAddon::update((int) $id, [
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'description_en' => $this->input('description_en', ''),
            'description_ar' => $this->input('description_ar', ''),
            'unit_price' => (float) $this->input('unit_price', 0),
            'unit_type' => $this->input('unit_type', 'sqm'),
            'is_pro' => $this->input('is_pro') ? 1 : 0,
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => $this->input('is_active') ? 1 : 0,
        ]);
        $this->flash('success', 'Add-on updated.');
        self::redirect('/admin/quick-estimate/addons');
    }

    public function destroyAddon(string $id): void
    {
        $this->verifyCsrf();
        QuickEstimateAddon::delete((int) $id);
        $this->flash('success', 'Add-on removed.');
        self::redirect('/admin/quick-estimate/addons');
    }

    // ---------------- Leads (submitted quick estimates) ----------------

    public function leads(): void
    {
        $leads = QuickEstimate::query(
            'SELECT qe.*, r.name_en AS region_name, f.name_en AS foundation_name FROM quick_estimates qe
             LEFT JOIN quick_estimate_regions r ON r.id = qe.region_id
             LEFT JOIN quick_estimate_foundations f ON f.id = qe.foundation_id
             ORDER BY qe.created_at DESC'
        )->fetchAll();

        $this->view('admin/quick-estimate/leads', [
            'pageTitle' => 'Quick Estimate — Leads',
            'leads' => $leads,
        ], 'layouts/admin');
    }

    public function updateLeadStatus(string $id): void
    {
        $this->verifyCsrf();
        $status = (string) $this->input('status', 'new');
        if (in_array($status, ['new', 'contacted', 'converted', 'dismissed'], true)) {
            QuickEstimate::update((int) $id, ['status' => $status]);
        }
        self::redirect('/admin/quick-estimate/leads');
    }
}
