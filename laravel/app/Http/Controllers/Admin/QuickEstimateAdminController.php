<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuickEstimate;
use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateQualityTier;
use App\Models\QuickEstimateRegion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuickEstimateAdminController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect('/admin/quick-estimate/regions');
    }

    // ---------------- Regions ----------------

    public function regions(): View
    {
        return view('admin.quick-estimate.regions', ['regions' => QuickEstimateRegion::orderBy('sort_order')->get()]);
    }

    public function storeRegion(Request $request): RedirectResponse
    {
        QuickEstimateRegion::create([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'price_per_sqm' => (float) $request->input('price_per_sqm', 0),
            'multiplier' => (float) $request->input('multiplier', 1),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => true,
        ]);
        return $this->redirectWithFlash('/admin/quick-estimate/regions', 'success', t('admin.quick_estimate.region_added'));
    }

    public function updateRegion(Request $request, int $id): RedirectResponse
    {
        QuickEstimateRegion::findOrFail($id)->update([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'price_per_sqm' => (float) $request->input('price_per_sqm', 0),
            'multiplier' => (float) $request->input('multiplier', 1),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => (bool) $request->input('is_active'),
        ]);
        return $this->redirectWithFlash('/admin/quick-estimate/regions', 'success', t('admin.quick_estimate.region_updated'));
    }

    public function destroyRegion(int $id): RedirectResponse
    {
        QuickEstimateRegion::destroy($id);
        return $this->redirectWithFlash('/admin/quick-estimate/regions', 'success', t('admin.quick_estimate.region_removed'));
    }

    // ---------------- Foundations ----------------

    public function foundations(): View
    {
        return view('admin.quick-estimate.foundations', ['foundations' => QuickEstimateFoundation::orderBy('sort_order')->get()]);
    }

    public function storeFoundation(Request $request): RedirectResponse
    {
        QuickEstimateFoundation::create([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'description_en' => $request->input('description_en', ''),
            'description_ar' => $request->input('description_ar', ''),
            'price_per_sqm' => (float) $request->input('price_per_sqm', 0),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => true,
        ]);
        return $this->redirectWithFlash('/admin/quick-estimate/foundations', 'success', t('admin.quick_estimate.foundation_added'));
    }

    public function updateFoundation(Request $request, int $id): RedirectResponse
    {
        QuickEstimateFoundation::findOrFail($id)->update([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'description_en' => $request->input('description_en', ''),
            'description_ar' => $request->input('description_ar', ''),
            'price_per_sqm' => (float) $request->input('price_per_sqm', 0),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => (bool) $request->input('is_active'),
        ]);
        return $this->redirectWithFlash('/admin/quick-estimate/foundations', 'success', t('admin.quick_estimate.foundation_updated'));
    }

    public function destroyFoundation(int $id): RedirectResponse
    {
        QuickEstimateFoundation::destroy($id);
        return $this->redirectWithFlash('/admin/quick-estimate/foundations', 'success', t('admin.quick_estimate.foundation_removed'));
    }

    // ---------------- Add-ons ----------------

    public function addons(): View
    {
        return view('admin.quick-estimate.addons', ['addons' => QuickEstimateAddon::orderBy('sort_order')->get()]);
    }

    public function storeAddon(Request $request): RedirectResponse
    {
        QuickEstimateAddon::create([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'description_en' => $request->input('description_en', ''),
            'description_ar' => $request->input('description_ar', ''),
            'unit_price' => (float) $request->input('unit_price', 0),
            'unit_type' => trim((string) $request->input('unit_type')) ?: 'sqm',
            'qty_mode' => $request->input('qty_mode') === 'manual' ? 'manual' : 'area',
            'is_pro' => (bool) $request->input('is_pro'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => true,
        ]);
        return $this->redirectWithFlash('/admin/quick-estimate/addons', 'success', t('admin.quick_estimate.addon_added'));
    }

    public function updateAddon(Request $request, int $id): RedirectResponse
    {
        QuickEstimateAddon::findOrFail($id)->update([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'description_en' => $request->input('description_en', ''),
            'description_ar' => $request->input('description_ar', ''),
            'unit_price' => (float) $request->input('unit_price', 0),
            'unit_type' => trim((string) $request->input('unit_type')) ?: 'sqm',
            'qty_mode' => $request->input('qty_mode') === 'manual' ? 'manual' : 'area',
            'is_pro' => (bool) $request->input('is_pro'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => (bool) $request->input('is_active'),
        ]);
        return $this->redirectWithFlash('/admin/quick-estimate/addons', 'success', t('admin.quick_estimate.addon_updated'));
    }

    public function destroyAddon(int $id): RedirectResponse
    {
        QuickEstimateAddon::destroy($id);
        return $this->redirectWithFlash('/admin/quick-estimate/addons', 'success', t('admin.quick_estimate.addon_removed'));
    }

    // ---------------- Quality tiers ----------------

    public function qualityTiers(): View
    {
        return view('admin.quick-estimate.quality-tiers', ['qualityTiers' => QuickEstimateQualityTier::orderBy('sort_order')->get()]);
    }

    public function storeQualityTier(Request $request): RedirectResponse
    {
        QuickEstimateQualityTier::create([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'multiplier' => (float) $request->input('multiplier', 1),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => true,
        ]);
        return $this->redirectWithFlash('/admin/quick-estimate/quality-tiers', 'success', t('admin.quick_estimate.quality_tier_added'));
    }

    public function updateQualityTier(Request $request, int $id): RedirectResponse
    {
        QuickEstimateQualityTier::findOrFail($id)->update([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'multiplier' => (float) $request->input('multiplier', 1),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => (bool) $request->input('is_active'),
        ]);
        return $this->redirectWithFlash('/admin/quick-estimate/quality-tiers', 'success', t('admin.quick_estimate.quality_tier_updated'));
    }

    public function destroyQualityTier(int $id): RedirectResponse
    {
        QuickEstimateQualityTier::destroy($id);
        return $this->redirectWithFlash('/admin/quick-estimate/quality-tiers', 'success', t('admin.quick_estimate.quality_tier_removed'));
    }

    // ---------------- Leads (submitted quick estimates) ----------------

    public function leads(): View
    {
        $leads = QuickEstimate::query()
            ->leftJoin('quick_estimate_regions as r', 'r.id', '=', 'quick_estimates.region_id')
            ->leftJoin('quick_estimate_foundations as f', 'f.id', '=', 'quick_estimates.foundation_id')
            ->whereNull('quick_estimates.company_id')
            ->orderByDesc('quick_estimates.created_at')
            ->select('quick_estimates.*', 'r.name_en as region_name', 'f.name_en as foundation_name')
            ->get();

        return view('admin.quick-estimate.leads', ['leads' => $leads]);
    }

    public function updateLeadStatus(Request $request, int $id): RedirectResponse
    {
        $status = (string) $request->input('status', 'new');
        if (in_array($status, ['new', 'contacted', 'converted', 'dismissed'], true)) {
            QuickEstimate::whereKey($id)->update(['status' => $status]);
        }
        return redirect('/admin/quick-estimate/leads');
    }
}
