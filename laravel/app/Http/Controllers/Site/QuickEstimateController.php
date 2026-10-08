<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\QuickEstimate;
use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateQualityTier;
use App\Models\QuickEstimateRegion;
use App\Models\Setting;
use App\Support\QuickEstimateCalc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QuickEstimateController extends Controller
{
    public function index(): View
    {
        return view('site.quick-estimate', [
            'pageTitle' => 'Quick Estimate',
            'regions' => QuickEstimateRegion::where('is_active', true)->orderBy('sort_order')->get(),
            'foundations' => QuickEstimateFoundation::where('is_active', true)->orderBy('sort_order')->get(),
            'addons' => QuickEstimateAddon::where('is_active', true)->orderBy('sort_order')->get(),
            'qualityTiers' => QuickEstimateQualityTier::where('is_active', true)->orderBy('sort_order')->get(),
            'vatRate' => (float) Setting::get('vat_rate', '15'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $region = QuickEstimateRegion::find((int) $request->input('region_id'));
        $foundation = QuickEstimateFoundation::find((int) $request->input('foundation_id'));
        $qualityTier = QuickEstimateQualityTier::find((int) $request->input('quality_tier_id'));
        $totalArea = max(0, (float) $request->input('total_area', 0));
        $discountPercent = min(100, max(0, (float) $request->input('discount_percent', 0)));
        $vatRate = (float) Setting::get('vat_rate', '15');

        if (!$region || !$foundation || $totalArea <= 0) {
            return $this->redirectWithFlash('/quick-estimate', 'error', t('user.quick_estimate.inputs_required'));
        }

        $contactName = trim((string) $request->input('contact_name'));
        $contactEmail = trim((string) $request->input('contact_email'));
        $contactPhone = trim((string) $request->input('contact_phone'));
        if ($contactName === '' || $contactEmail === '' || $contactPhone === '') {
            return $this->redirectWithFlash('/quick-estimate', 'error', t('site.quick_estimate.contact_required'));
        }

        $selectedAddonIds = array_map('intval', (array) $request->input('addons', []));
        $addonRows = empty($selectedAddonIds) ? [] : QuickEstimateAddon::whereIn('id', $selectedAddonIds)->get()->map(fn ($a) => $a->toArray())->all();
        $addonQuantities = array_map('floatval', (array) $request->input('addon_qty', []));

        $result = QuickEstimateCalc::compute($region->toArray(), $foundation->toArray(), $addonRows, $totalArea, $discountPercent, $vatRate, $addonQuantities, $qualityTier?->toArray() ?? []);

        $estimate = QuickEstimate::create([
            'project_name' => trim((string) $request->input('project_name')) ?: null,
            'region_id' => $region->id,
            'foundation_id' => $foundation->id,
            'quality_tier_id' => $qualityTier?->id,
            'total_area' => $totalArea,
            'discount_percent' => $discountPercent,
            'addons_json' => json_encode($result['addons_payload']),
            'subtotal' => $result['subtotal'],
            'vat_amount' => $result['vat_amount'],
            'total' => $result['total'],
            'lang' => app()->getLocale(),
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'status' => 'new',
        ]);

        return redirect('/quick-estimate/' . $estimate->id);
    }

    public function show(int $id): View
    {
        $estimate = QuickEstimate::find($id);
        abort_if(!$estimate, 404, 'Estimate not found.');
        $region = $estimate->region_id ? QuickEstimateRegion::find($estimate->region_id) : null;
        $foundation = $estimate->foundation_id ? QuickEstimateFoundation::find($estimate->foundation_id) : null;
        $qualityTier = $estimate->quality_tier_id ? QuickEstimateQualityTier::find($estimate->quality_tier_id) : null;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];

        return view('site.quick-estimate-result', [
            'pageTitle' => 'Your Estimate',
            'estimate' => $estimate->toArray(),
            'region' => $region?->toArray(),
            'foundation' => $foundation?->toArray(),
            'qualityTier' => $qualityTier?->toArray(),
            'addons' => $addons,
            'vatRate' => (float) Setting::get('vat_rate', '15'),
        ]);
    }

    public function pdf(Request $request, int $id): Response
    {
        $estimate = QuickEstimate::find($id);
        abort_if(!$estimate, 404, 'Estimate not found.');
        $region = $estimate->region_id ? QuickEstimateRegion::find($estimate->region_id) : null;
        $foundation = $estimate->foundation_id ? QuickEstimateFoundation::find($estimate->foundation_id) : null;
        $qualityTier = $estimate->quality_tier_id ? QuickEstimateQualityTier::find($estimate->quality_tier_id) : null;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];
        $lang = $estimate->lang === 'ar' ? 'ar' : 'en';
        $template = in_array($request->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $request->input('template') : 'modern';

        $items = QuickEstimateCalc::pdfItems($estimate->toArray(), $region?->toArray(), $foundation?->toArray(), $addons, $lang, $qualityTier?->toArray());

        return $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'تسعيرة سريعة' : 'Quick Estimate',
            'docNumber' => (string) $estimate->id,
            'docDate' => $estimate->created_at,
            'issuer' => ['name' => Setting::siteName(), 'meta' => []],
            'billTo' => $estimate->contact_name ? ['name' => $estimate->contact_name, 'meta' => array_filter([$estimate->contact_email, $estimate->contact_phone])] : null,
            'items' => $items,
            'subtotal' => (float) $estimate->subtotal,
            'discountPercent' => (float) $estimate->discount_percent,
            'discountAmount' => (float) $estimate->subtotal * (float) $estimate->discount_percent / 100,
            'vatRate' => (float) Setting::get('vat_rate', '15'),
            'vatAmount' => (float) $estimate->vat_amount,
            'total' => (float) $estimate->total,
            'footerNote' => $lang === 'ar' ? 'تسعيرة تقديرية — تم إنشاؤها بواسطة ' . Setting::siteName() : 'Preliminary estimate — generated by ' . Setting::siteName(),
        ], 'Quick-Estimate-' . $estimate->id . '.pdf');
    }
}
