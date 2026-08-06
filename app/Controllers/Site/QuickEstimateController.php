<?php

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Lang;
use App\Core\Settings;
use App\Models\QuickEstimate;
use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateRegion;

class QuickEstimateController extends Controller
{
    public function index(): void
    {
        $regions = QuickEstimateRegion::query('SELECT * FROM quick_estimate_regions WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $foundations = QuickEstimateFoundation::query('SELECT * FROM quick_estimate_foundations WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $addons = QuickEstimateAddon::query('SELECT * FROM quick_estimate_addons WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

        $this->view('site/quick-estimate', [
            'pageTitle' => 'Quick Estimate',
            'regions' => $regions,
            'foundations' => $foundations,
            'addons' => $addons,
            'vatRate' => (float) Settings::get('vat_rate', 15),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $region = QuickEstimateRegion::find((int) $this->input('region_id'));
        $foundation = QuickEstimateFoundation::find((int) $this->input('foundation_id'));
        $totalArea = max(0, (float) $this->input('total_area', 0));
        $discountPercent = min(100, max(0, (float) $this->input('discount_percent', 0)));
        $vatRate = (float) Settings::get('vat_rate', 15);

        if (!$region || !$foundation || $totalArea <= 0) {
            $this->flash('error', 'Please choose a region, a foundation type, and enter a total area.');
            self::redirect('/quick-estimate');
        }

        $selectedAddonIds = array_map('intval', (array) $this->input('addons', []));
        $addonRows = [];
        $addonCost = 0.0;
        if (!empty($selectedAddonIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedAddonIds), '?'));
            $addonRows = QuickEstimateAddon::query("SELECT * FROM quick_estimate_addons WHERE id IN ({$placeholders})", $selectedAddonIds)->fetchAll();
            foreach ($addonRows as $addon) {
                $addonCost += (float) $addon['unit_price'] * $totalArea;
            }
        }

        $baseCost = $totalArea * (float) $region['price_per_sqm'];
        $foundationCost = $totalArea * (float) $foundation['price_per_sqm'];
        $multiplier = (float) $region['multiplier'] ?: 1.0;
        $subtotal = ($baseCost + $foundationCost + $addonCost) * $multiplier;
        $discountAmount = $subtotal * $discountPercent / 100;
        $taxable = $subtotal - $discountAmount;
        $vatAmount = $taxable * $vatRate / 100;
        $total = $taxable + $vatAmount;

        $lang = Lang::locale();
        $id = QuickEstimate::create([
            'project_name' => trim((string) $this->input('project_name')) ?: null,
            'region_id' => $region['id'],
            'foundation_id' => $foundation['id'],
            'total_area' => $totalArea,
            'discount_percent' => $discountPercent,
            'addons_json' => json_encode(array_map(fn($a) => ['id' => (int) $a['id'], 'name_en' => $a['name_en'], 'name_ar' => $a['name_ar'], 'cost' => (float) $a['unit_price'] * $totalArea], $addonRows)),
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'lang' => $lang,
            'contact_name' => trim((string) $this->input('contact_name')) ?: null,
            'contact_email' => trim((string) $this->input('contact_email')) ?: null,
            'contact_phone' => trim((string) $this->input('contact_phone')) ?: null,
            'status' => 'new',
        ]);

        self::redirect('/quick-estimate/' . $id);
    }

    public function show(string $id): void
    {
        $estimate = QuickEstimate::find((int) $id);
        if (!$estimate) {
            http_response_code(404);
            die('Estimate not found.');
        }
        $region = $estimate['region_id'] ? QuickEstimateRegion::find((int) $estimate['region_id']) : null;
        $foundation = $estimate['foundation_id'] ? QuickEstimateFoundation::find((int) $estimate['foundation_id']) : null;
        $addons = json_decode((string) $estimate['addons_json'], true) ?: [];

        $this->view('site/quick-estimate-result', [
            'pageTitle' => 'Your Estimate',
            'estimate' => $estimate,
            'region' => $region,
            'foundation' => $foundation,
            'addons' => $addons,
            'vatRate' => (float) Settings::get('vat_rate', 15),
        ]);
    }

    public function pdf(string $id): void
    {
        $estimate = QuickEstimate::find((int) $id);
        if (!$estimate) {
            http_response_code(404);
            die('Estimate not found.');
        }
        $region = $estimate['region_id'] ? QuickEstimateRegion::find((int) $estimate['region_id']) : null;
        $foundation = $estimate['foundation_id'] ? QuickEstimateFoundation::find((int) $estimate['foundation_id']) : null;
        $addons = json_decode((string) $estimate['addons_json'], true) ?: [];
        $lang = $estimate['lang'] === 'ar' ? 'ar' : 'en';
        $template = in_array($this->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant'], true) ? $this->input('template') : 'modern';

        $items = [];
        $nameKey = $lang === 'ar' ? 'name_ar' : 'name_en';
        if ($region) {
            $items[] = ['description' => ($lang === 'ar' ? 'التكلفة الأساسية للبناء' : 'Base construction cost') . ' — ' . $region[$nameKey], 'qty' => $estimate['total_area'], 'unit_price' => $region['price_per_sqm'], 'total' => $estimate['total_area'] * $region['price_per_sqm']];
        }
        if ($foundation) {
            $items[] = ['description' => ($lang === 'ar' ? 'الأساسات' : 'Foundation') . ' — ' . $foundation[$nameKey], 'qty' => $estimate['total_area'], 'unit_price' => $foundation['price_per_sqm'], 'total' => $estimate['total_area'] * $foundation['price_per_sqm']];
        }
        foreach ($addons as $addon) {
            $items[] = ['description' => $addon[$nameKey] ?? $addon['name_en'], 'qty' => $estimate['total_area'], 'unit_price' => (float) $addon['cost'] / max(1, $estimate['total_area']), 'total' => $addon['cost']];
        }

        $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'تسعيرة سريعة' : 'Quick Estimate',
            'docNumber' => (string) $estimate['id'],
            'docDate' => $estimate['created_at'],
            'issuer' => ['name' => 'BuildXact Saudi', 'meta' => []],
            'billTo' => $estimate['contact_name'] ? ['name' => $estimate['contact_name'], 'meta' => array_filter([$estimate['contact_email'], $estimate['contact_phone']])] : null,
            'items' => $items,
            'subtotal' => (float) $estimate['subtotal'],
            'discountPercent' => (float) $estimate['discount_percent'],
            'discountAmount' => (float) $estimate['subtotal'] * (float) $estimate['discount_percent'] / 100,
            'vatRate' => Settings::get('vat_rate', 15),
            'vatAmount' => (float) $estimate['vat_amount'],
            'total' => (float) $estimate['total'],
            'footerNote' => $lang === 'ar' ? 'تسعيرة تقديرية — تم إنشاؤها بواسطة BuildXact Saudi' : 'Preliminary estimate — generated by BuildXact Saudi',
        ], 'Quick-Estimate-' . $estimate['id'] . '.pdf');
    }
}
