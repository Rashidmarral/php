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
        $input = $this->collectRegionInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/quick-estimate/regions', 'error', $input);
        }
        QuickEstimateRegion::create($input + ['is_active' => true]);

        return $this->redirectWithFlash('/admin/quick-estimate/regions', 'success', t('admin.quick_estimate.region_added'));
    }

    public function updateRegion(Request $request, int $id): RedirectResponse
    {
        $region = QuickEstimateRegion::findOrFail($id);
        $input = $this->collectRegionInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/quick-estimate/regions', 'error', $input);
        }
        $region->update($input + ['is_active' => (bool) $request->input('is_active')]);

        return $this->redirectWithFlash('/admin/quick-estimate/regions', 'success', t('admin.quick_estimate.region_updated'));
    }

    /** Returns the validated/bounded column data, or a translated error message string when a required field is missing or a value is out of range. */
    private function collectRegionInput(Request $request): array|string
    {
        $nameEn = trim((string) $request->input('name_en'));
        $nameAr = trim((string) $request->input('name_ar'));
        if ($nameEn === '' || $nameAr === '' || mb_strlen($nameEn) > 100 || mb_strlen($nameAr) > 100) {
            return t('admin.quick_estimate.name_required');
        }
        $pricePerSqm = $this->validatedNumberOrDefault($request->input('price_per_sqm', 0), 0, 99999999.99, 0.0);
        if ($pricePerSqm === null) {
            return t('admin.quick_estimate.price_per_sqm_invalid');
        }
        $multiplier = $this->validatedNumberOrDefault($request->input('multiplier', 1), 0, 999.99, 1.0);
        if ($multiplier === null) {
            return t('admin.quick_estimate.multiplier_invalid');
        }
        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return t('common.sort_order_invalid');
        }

        return [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'price_per_sqm' => $pricePerSqm,
            'multiplier' => $multiplier,
            'sort_order' => (int) $sortOrder,
        ];
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
        $input = $this->collectFoundationInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/quick-estimate/foundations', 'error', $input);
        }
        QuickEstimateFoundation::create($input + ['is_active' => true]);

        return $this->redirectWithFlash('/admin/quick-estimate/foundations', 'success', t('admin.quick_estimate.foundation_added'));
    }

    public function updateFoundation(Request $request, int $id): RedirectResponse
    {
        $foundation = QuickEstimateFoundation::findOrFail($id);
        $input = $this->collectFoundationInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/quick-estimate/foundations', 'error', $input);
        }
        $foundation->update($input + ['is_active' => (bool) $request->input('is_active')]);

        return $this->redirectWithFlash('/admin/quick-estimate/foundations', 'success', t('admin.quick_estimate.foundation_updated'));
    }

    /** Returns the validated/bounded column data, or a translated error message string when a required field is missing or a value is out of range. */
    private function collectFoundationInput(Request $request): array|string
    {
        $nameEn = trim((string) $request->input('name_en'));
        $nameAr = trim((string) $request->input('name_ar'));
        if ($nameEn === '' || $nameAr === '' || mb_strlen($nameEn) > 100 || mb_strlen($nameAr) > 100) {
            return t('admin.quick_estimate.name_required');
        }
        $pricePerSqm = $this->validatedNumberOrDefault($request->input('price_per_sqm', 0), 0, 99999999.99, 0.0);
        if ($pricePerSqm === null) {
            return t('admin.quick_estimate.price_per_sqm_invalid');
        }
        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return t('common.sort_order_invalid');
        }

        return [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'description_en' => mb_substr((string) $request->input('description_en', ''), 0, 255),
            'description_ar' => mb_substr((string) $request->input('description_ar', ''), 0, 255),
            'price_per_sqm' => $pricePerSqm,
            'sort_order' => (int) $sortOrder,
        ];
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
        $input = $this->collectAddonInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/quick-estimate/addons', 'error', $input);
        }
        QuickEstimateAddon::create($input + ['is_active' => true]);

        return $this->redirectWithFlash('/admin/quick-estimate/addons', 'success', t('admin.quick_estimate.addon_added'));
    }

    public function updateAddon(Request $request, int $id): RedirectResponse
    {
        $addon = QuickEstimateAddon::findOrFail($id);
        $input = $this->collectAddonInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/quick-estimate/addons', 'error', $input);
        }
        $addon->update($input + ['is_active' => (bool) $request->input('is_active')]);

        return $this->redirectWithFlash('/admin/quick-estimate/addons', 'success', t('admin.quick_estimate.addon_updated'));
    }

    /** Returns the validated/bounded column data, or a translated error message string when a required field is missing or a value is out of range. */
    private function collectAddonInput(Request $request): array|string
    {
        $nameEn = trim((string) $request->input('name_en'));
        $nameAr = trim((string) $request->input('name_ar'));
        if ($nameEn === '' || $nameAr === '' || mb_strlen($nameEn) > 100 || mb_strlen($nameAr) > 100) {
            return t('admin.quick_estimate.name_required');
        }
        $unitPrice = $this->validatedNumberOrDefault($request->input('unit_price', 0), 0, 99999999.99, 0.0);
        if ($unitPrice === null) {
            return t('admin.quick_estimate.unit_price_invalid');
        }
        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return t('common.sort_order_invalid');
        }

        return [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'description_en' => mb_substr((string) $request->input('description_en', ''), 0, 255),
            'description_ar' => mb_substr((string) $request->input('description_ar', ''), 0, 255),
            'unit_price' => $unitPrice,
            'unit_type' => mb_substr(trim((string) $request->input('unit_type')), 0, 20) ?: 'sqm',
            'qty_mode' => $request->input('qty_mode') === 'manual' ? 'manual' : 'area',
            'is_pro' => (bool) $request->input('is_pro'),
            'sort_order' => (int) $sortOrder,
        ];
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
        $input = $this->collectQualityTierInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/quick-estimate/quality-tiers', 'error', $input);
        }
        QuickEstimateQualityTier::create($input + ['is_active' => true]);

        return $this->redirectWithFlash('/admin/quick-estimate/quality-tiers', 'success', t('admin.quick_estimate.quality_tier_added'));
    }

    public function updateQualityTier(Request $request, int $id): RedirectResponse
    {
        $tier = QuickEstimateQualityTier::findOrFail($id);
        $input = $this->collectQualityTierInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/quick-estimate/quality-tiers', 'error', $input);
        }
        $tier->update($input + ['is_active' => (bool) $request->input('is_active')]);

        return $this->redirectWithFlash('/admin/quick-estimate/quality-tiers', 'success', t('admin.quick_estimate.quality_tier_updated'));
    }

    /** Returns the validated/bounded column data, or a translated error message string when a required field is missing or a value is out of range. */
    private function collectQualityTierInput(Request $request): array|string
    {
        $nameEn = trim((string) $request->input('name_en'));
        $nameAr = trim((string) $request->input('name_ar'));
        if ($nameEn === '' || $nameAr === '' || mb_strlen($nameEn) > 100 || mb_strlen($nameAr) > 100) {
            return t('admin.quick_estimate.name_required');
        }
        $multiplier = $this->validatedNumberOrDefault($request->input('multiplier', 1), 0, 999.99, 1.0);
        if ($multiplier === null) {
            return t('admin.quick_estimate.multiplier_invalid');
        }
        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return t('common.sort_order_invalid');
        }

        return [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'multiplier' => $multiplier,
            'sort_order' => (int) $sortOrder,
        ];
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

    /** Rejects a non-numeric or out-of-range value instead of silently casting it — see App\Http\Controllers\App\ProjectController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $float = (float) $value;

        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected. */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return $this->validatedNumber($value, $min, $max);
    }
}
