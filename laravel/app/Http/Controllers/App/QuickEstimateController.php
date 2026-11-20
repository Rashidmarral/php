<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Project;
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
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
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
        $vatRate = (float) Setting::get('vat_rate', '15');

        if (!$region || !$foundation) {
            return $this->redirectWithFlash('/app/quick-estimate', 'error', t('user.quick_estimate.inputs_required'));
        }

        $totalArea = $this->validatedNumber($request->input('total_area', 0), 0.01, 999999.99);
        if ($totalArea === null) {
            return $this->redirectWithFlash('/app/quick-estimate', 'error', t('user.quick_estimate.area_invalid'));
        }
        $discountPercent = $this->validatedNumberOrDefault($request->input('discount_percent', 0), 0, 100, 0.0);
        if ($discountPercent === null) {
            return $this->redirectWithFlash('/app/quick-estimate', 'error', t('user.quick_estimate.discount_invalid'));
        }

        $selectedAddonIds = array_map('intval', (array) $request->input('addons', []));
        $addonRows = empty($selectedAddonIds)
            ? []
            : QuickEstimateAddon::whereIn('id', $selectedAddonIds)->get()->toArray();
        $addonQuantities = $this->validatedAddonQuantities($selectedAddonIds, (array) $request->input('addon_qty', []));
        if ($addonQuantities === null) {
            return $this->redirectWithFlash('/app/quick-estimate', 'error', t('user.quick_estimate.addon_qty_invalid'));
        }

        $result = QuickEstimateCalc::compute($region->toArray(), $foundation->toArray(), $addonRows, $totalArea, $discountPercent, $vatRate, $addonQuantities, $qualityTier?->toArray() ?? []);

        $clientId = $request->input('client_id') ?: null;
        $client = $this->ownedClient($clientId, $companyId);
        $projectId = $request->input('project_id') ?: null;
        $project = $this->ownedProject($projectId, $companyId);

        $estimate = QuickEstimate::create([
            'company_id' => $companyId,
            'client_id' => $client?->id,
            'project_id' => $project?->id,
            'project_name' => $project->name ?? null,
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

        $this->flash('success', t('user.quick_estimate.flash_generated'));
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
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
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
        $vatRate = (float) Setting::get('vat_rate', '15');
        $editUrl = '/app/quick-estimate/' . $estimate->id . '/edit';

        if (!$region || !$foundation) {
            return $this->redirectWithFlash($editUrl, 'error', t('user.quick_estimate.inputs_required'));
        }

        $totalArea = $this->validatedNumber($request->input('total_area', 0), 0.01, 999999.99);
        if ($totalArea === null) {
            return $this->redirectWithFlash($editUrl, 'error', t('user.quick_estimate.area_invalid'));
        }
        $discountPercent = $this->validatedNumberOrDefault($request->input('discount_percent', 0), 0, 100, 0.0);
        if ($discountPercent === null) {
            return $this->redirectWithFlash($editUrl, 'error', t('user.quick_estimate.discount_invalid'));
        }

        $selectedAddonIds = array_map('intval', (array) $request->input('addons', []));
        $addonRows = empty($selectedAddonIds)
            ? []
            : QuickEstimateAddon::whereIn('id', $selectedAddonIds)->get()->toArray();
        $addonQuantities = $this->validatedAddonQuantities($selectedAddonIds, (array) $request->input('addon_qty', []));
        if ($addonQuantities === null) {
            return $this->redirectWithFlash($editUrl, 'error', t('user.quick_estimate.addon_qty_invalid'));
        }

        $result = QuickEstimateCalc::compute($region->toArray(), $foundation->toArray(), $addonRows, $totalArea, $discountPercent, $vatRate, $addonQuantities, $qualityTier?->toArray() ?? []);

        $clientId = $request->input('client_id') ?: null;
        $client = $this->ownedClient($clientId, $companyId);
        $projectId = $request->input('project_id') ?: null;
        $project = $this->ownedProject($projectId, $companyId);

        $estimate->update([
            'client_id' => $client?->id,
            'project_id' => $project?->id,
            'project_name' => $project->name ?? null,
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

        $this->flash('success', t('user.quick_estimate.updated'));
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
        $company = Company::find($estimate->company_id);

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

    public function pdf(int $id): Response
    {
        $estimate = $this->findOwned($id);
        $region = $estimate->region_id ? QuickEstimateRegion::find($estimate->region_id) : null;
        $foundation = $estimate->foundation_id ? QuickEstimateFoundation::find($estimate->foundation_id) : null;
        $qualityTier = $estimate->quality_tier_id ? QuickEstimateQualityTier::find($estimate->quality_tier_id) : null;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];
        $company = Company::find($estimate->company_id);
        $template = $company->activeInvoiceTemplate();
        $invoiceTemplate = $company->activeInvoiceTemplateFor('quick_estimate');
        $lang = $this->pdfLangFor($invoiceTemplate, $estimate->lang === 'ar' ? 'ar' : 'en');

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
            'invoiceTemplate' => $invoiceTemplate,
            'company' => $company,
            'documentType' => 'quick_estimate',
            'pageSize' => $invoiceTemplate ? ($invoiceTemplate->page_size ?: 'a4') : 'a4',
            'partyLabelKey' => 'pdf.bill_to',
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
            'project_id' => $estimate->project_id,
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

        $this->flash('success', t('user.quick_estimate.converted'));
        return redirect('/app/estimates/' . $newEstimate->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        $this->flash('success', t('user.quick_estimate.deleted'));
        return redirect('/app/quick-estimate');
    }

    private function findOwned(int $id): QuickEstimate
    {
        $estimate = QuickEstimate::find($id);
        abort_if(!$estimate || $estimate->company_id !== Auth::user()->company_id, 404, 'Quick estimate not found.');
        return $estimate;
    }

    /**
     * Parses a submitted field as a number and checks it against inclusive bounds,
     * returning null for anything missing/non-numeric/out of range — store()/update()
     * use this for total_area and discount_percent, which previously were only ever
     * cast with (float)/max()/min() and silently coerced instead of rejected (same gap
     * EstimateController::validatedNumber() closes for estimate numbers, and the
     * CR/VAT/postal-code gaps App\Support\ZatcaAddressValidator closed for address
     * fields).
     */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected — used for optional fields (discount_percent) where "cleared" has always meant "none". */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $this->validatedNumber($value, $min, $max);
    }

    /**
     * Validates the addon_qty[] map (manual quantities for non-area-priced add-ons)
     * for only the SELECTED add-ons — an unchecked add-on's qty input is still
     * submitted (the field stays in the DOM either way) but never reaches
     * QuickEstimateCalc::compute(), so a stray/garbage value left in it must not
     * block the whole request. For a selected add-on, a blank value defaults to 0
     * (matching the old floatval('') behavior); anything else must be numeric and
     * within [0, 999999.99], or the request is rejected rather than silently
     * floatval()'d — a crafted huge quantity would otherwise overflow
     * quick_estimates' decimal(12,2) total/subtotal columns and throw a database
     * error instead of a clean validation message.
     *
     * @param  array<int, int>  $selectedAddonIds
     * @param  array<int|string, mixed>  $raw
     * @return array<int, float>|null
     */
    private function validatedAddonQuantities(array $selectedAddonIds, array $raw): ?array
    {
        $quantities = [];
        foreach ($selectedAddonIds as $addonId) {
            $value = $raw[$addonId] ?? null;
            if ($value === null || $value === '') {
                $quantities[$addonId] = 0.0;
                continue;
            }
            $qty = $this->validatedNumber($value, 0, 999999.99);
            if ($qty === null) {
                return null;
            }
            $quantities[$addonId] = $qty;
        }
        return $quantities;
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

    /** Only returns the project if it belongs to $companyId — never let a quick estimate link to another company's project via a tampered id. */
    private function ownedProject(?int $id, int $companyId): ?Project
    {
        if (!$id) {
            return null;
        }
        $project = Project::find($id);
        return ($project && $project->company_id === $companyId) ? $project : null;
    }
}
