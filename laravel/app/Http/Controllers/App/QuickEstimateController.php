<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuickEstimateController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $quotes = DB::table('quick_estimates as qe')
            ->leftJoin('quick_estimate_regions as r', 'r.id', '=', 'qe.region_id')
            ->leftJoin('quick_estimate_foundations as f', 'f.id', '=', 'qe.foundation_id')
            ->leftJoin('clients as c', 'c.id', '=', 'qe.client_id')
            ->where('qe.company_id', $companyId)
            ->orderByDesc('qe.created_at')
            ->select('qe.*', 'r.name_en as region_name', 'f.name_en as foundation_name', 'c.name as client_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.quick-estimate.index', [
            'regions' => QuickEstimateRegion::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
            'foundations' => QuickEstimateFoundation::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
            'addons' => QuickEstimateAddon::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
            'qualityTiers' => QuickEstimateQualityTier::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'quotes' => $quotes,
            'vatRate' => (float) Setting::get('vat_rate', '15'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $region = QuickEstimateRegion::find((int) $request->input('region_id'));
        $foundation = QuickEstimateFoundation::find((int) $request->input('foundation_id'));
        $qualityTier = QuickEstimateQualityTier::find((int) $request->input('quality_tier_id'));
        $totalArea = max(0, (float) $request->input('total_area', 0));
        $discountPercent = min(100, max(0, (float) $request->input('discount_percent', 0)));
        $vatRate = (float) Setting::get('vat_rate', '15');

        if (!$region || !$foundation || $totalArea <= 0) {
            return $this->redirectWithFlash('/app/quick-estimate', 'error', 'Please choose a region, a foundation type, and enter a total area.');
        }

        $selectedAddonIds = array_map('intval', (array) $request->input('addons', []));
        $addonRows = empty($selectedAddonIds)
            ? []
            : QuickEstimateAddon::whereIn('id', $selectedAddonIds)->get()->toArray();
        $addonQuantities = array_map('floatval', (array) $request->input('addon_qty', []));

        $result = QuickEstimateCalc::compute($region->toArray(), $foundation->toArray(), $addonRows, $totalArea, $discountPercent, $vatRate, $addonQuantities, $qualityTier?->toArray() ?? []);

        $clientId = $request->input('client_id') ?: null;
        $client = $this->ownedClient($clientId, $companyId);

        $estimate = QuickEstimate::create([
            'company_id' => $companyId,
            'client_id' => $client?->id,
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
            'contact_name' => $client->name ?? null,
            'contact_email' => $client->email ?? null,
            'contact_phone' => $client->phone ?? null,
            'status' => 'internal',
        ]);

        $this->flash('success', 'Quick estimate generated.');
        return redirect('/app/quick-estimate/' . $estimate->id);
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $companyId = Auth::user()->company_id;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];
        $addonQty = [];
        foreach ($addons as $a) {
            $addonQty[(int) $a['id']] = $a['qty'];
        }

        return view('app.quick-estimate.edit', [
            'estimate' => $estimate->toArray(),
            'regions' => QuickEstimateRegion::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
            'foundations' => QuickEstimateFoundation::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
            'addons' => QuickEstimateAddon::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
            'qualityTiers' => QuickEstimateQualityTier::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'vatRate' => (float) Setting::get('vat_rate', '15'),
            'selectedAddonIds' => array_column($addons, 'id'),
            'addonQty' => $addonQty,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $companyId = Auth::user()->company_id;

        $region = QuickEstimateRegion::find((int) $request->input('region_id'));
        $foundation = QuickEstimateFoundation::find((int) $request->input('foundation_id'));
        $qualityTier = QuickEstimateQualityTier::find((int) $request->input('quality_tier_id'));
        $totalArea = max(0, (float) $request->input('total_area', 0));
        $discountPercent = min(100, max(0, (float) $request->input('discount_percent', 0)));
        $vatRate = (float) Setting::get('vat_rate', '15');

        if (!$region || !$foundation || $totalArea <= 0) {
            return $this->redirectWithFlash('/app/quick-estimate/' . $estimate->id . '/edit', 'error', 'Please choose a region, a foundation type, and enter a total area.');
        }

        $selectedAddonIds = array_map('intval', (array) $request->input('addons', []));
        $addonRows = empty($selectedAddonIds)
            ? []
            : QuickEstimateAddon::whereIn('id', $selectedAddonIds)->get()->toArray();
        $addonQuantities = array_map('floatval', (array) $request->input('addon_qty', []));

        $result = QuickEstimateCalc::compute($region->toArray(), $foundation->toArray(), $addonRows, $totalArea, $discountPercent, $vatRate, $addonQuantities, $qualityTier?->toArray() ?? []);

        $clientId = $request->input('client_id') ?: null;
        $client = $this->ownedClient($clientId, $companyId);

        $estimate->update([
            'client_id' => $client?->id,
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
            'contact_name' => $client->name ?? null,
            'contact_email' => $client->email ?? null,
            'contact_phone' => $client->phone ?? null,
        ]);

        $this->flash('success', 'Quick estimate updated.');
        return redirect('/app/quick-estimate/' . $estimate->id);
    }

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $region = $estimate->region_id ? QuickEstimateRegion::find($estimate->region_id) : null;
        $foundation = $estimate->foundation_id ? QuickEstimateFoundation::find($estimate->foundation_id) : null;
        $qualityTier = $estimate->quality_tier_id ? QuickEstimateQualityTier::find($estimate->quality_tier_id) : null;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];
        $client = $this->ownedClient($estimate->client_id, $estimate->company_id);

        return view('app.quick-estimate.show', [
            'estimate' => $estimate->toArray(),
            'region' => $region?->toArray(),
            'foundation' => $foundation?->toArray(),
            'qualityTier' => $qualityTier?->toArray(),
            'addons' => $addons,
            'client' => $client?->toArray(),
            'vatRate' => (float) Setting::get('vat_rate', '15'),
        ]);
    }

    public function pdf(Request $request, int $id): Response
    {
        $estimate = $this->findOwned($id);
        $region = $estimate->region_id ? QuickEstimateRegion::find($estimate->region_id) : null;
        $foundation = $estimate->foundation_id ? QuickEstimateFoundation::find($estimate->foundation_id) : null;
        $qualityTier = $estimate->quality_tier_id ? QuickEstimateQualityTier::find($estimate->quality_tier_id) : null;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];
        $lang = $request->input('lang') === 'ar' ? 'ar' : ($estimate->lang === 'ar' ? 'ar' : 'en');
        $template = in_array($request->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $request->input('template') : 'modern';
        $company = Company::find($estimate->company_id);

        $items = QuickEstimateCalc::pdfItems($estimate->toArray(), $region?->toArray(), $foundation?->toArray(), $addons, $lang, $qualityTier?->toArray());

        return $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'تسعيرة سريعة' : 'Quick Estimate',
            'docNumber' => (string) $estimate->id,
            'docDate' => $estimate->created_at,
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, $company->vat_number ? 'VAT: ' . $company->vat_number : null])],
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

    public function convertToEstimate(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $region = $estimate->region_id ? QuickEstimateRegion::find($estimate->region_id) : null;
        $foundation = $estimate->foundation_id ? QuickEstimateFoundation::find($estimate->foundation_id) : null;
        $qualityTier = $estimate->quality_tier_id ? QuickEstimateQualityTier::find($estimate->quality_tier_id) : null;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];
        $lang = $estimate->lang === 'ar' ? 'ar' : 'en';
        $items = QuickEstimateCalc::pdfItems($estimate->toArray(), $region?->toArray(), $foundation?->toArray(), $addons, $lang, $qualityTier?->toArray());

        $companyId = Auth::user()->company_id;
        $taxPercent = (float) $estimate->subtotal > 0 ? round((float) $estimate->vat_amount / (float) $estimate->subtotal * 100, 2) : 0;

        // Mirrors EstimateController::approvalFieldsForNewEstimate() — a company that
        // requires internal approval before estimates reach a client must not be able
        // to bypass that gate just by routing a quick estimate through this conversion.
        $company = Company::find($companyId);
        $approvalFields = ($company && $company->requiresEstimateApproval())
            ? ['approval_status' => 'pending', 'approval_requested_by' => Auth::id(), 'approval_requested_at' => now()]
            : [];

        $newEstimate = Estimate::create([
            'company_id' => $companyId,
            'project_id' => null,
            'client_id' => $estimate->client_id,
            'title' => $estimate->project_name ?: ('Quick Estimate #' . $estimate->id),
            'status' => 'draft',
            'subtotal' => $estimate->subtotal,
            'markup_percent' => 0,
            'markup_amount' => 0,
            'tax_percent' => $taxPercent,
            'tax_amount' => $estimate->vat_amount,
            'total' => $estimate->total,
            'share_token' => bin2hex(random_bytes(20)),
            'valid_until' => now()->addDays(30)->toDateString(),
            ...$approvalFields,
        ]);
        foreach ($items as $item) {
            EstimateItem::create([
                'estimate_id' => $newEstimate->id,
                'description' => $item['description'],
                'qty' => $item['qty'],
                'unit_cost' => $item['unit_price'],
                'total' => $item['total'],
            ]);
        }

        $this->flash('success', 'Converted to a formal estimate.');
        return redirect('/app/estimates/' . $newEstimate->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        $this->flash('success', 'Quick estimate deleted.');
        return redirect('/app/quick-estimate');
    }

    private function findOwned(int $id): QuickEstimate
    {
        $estimate = QuickEstimate::find($id);
        abort_if(!$estimate || $estimate->company_id !== Auth::user()->company_id, 404, 'Quick estimate not found.');
        return $estimate;
    }

    /** Only returns the client if it belongs to $companyId — never leak another company's contact data via a foreign key. */
    private function ownedClient(?int $id, int $companyId): ?Client
    {
        if (!$id) {
            return null;
        }
        $client = Client::find($id);
        return ($client && $client->company_id === $companyId) ? $client : null;
    }
}
