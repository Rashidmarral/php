<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\QuickEstimate;
use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateRegion;
use App\Models\Setting;
use App\Support\QuickEstimateCalc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $result = QuickEstimateCalc::compute($region->toArray(), $foundation->toArray(), $addonRows, $totalArea, $discountPercent, $vatRate);

        $clientId = $request->input('client_id') ?: null;
        $client = $clientId ? Client::find((int) $clientId) : null;

        $estimate = QuickEstimate::create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'project_name' => trim((string) $request->input('project_name')) ?: null,
            'region_id' => $region->id,
            'foundation_id' => $foundation->id,
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

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $region = $estimate->region_id ? QuickEstimateRegion::find($estimate->region_id) : null;
        $foundation = $estimate->foundation_id ? QuickEstimateFoundation::find($estimate->foundation_id) : null;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];
        $client = $estimate->client_id ? Client::find($estimate->client_id) : null;

        return view('app.quick-estimate.show', [
            'estimate' => $estimate->toArray(),
            'region' => $region?->toArray(),
            'foundation' => $foundation?->toArray(),
            'addons' => $addons,
            'client' => $client?->toArray(),
            'vatRate' => (float) Setting::get('vat_rate', '15'),
        ]);
    }

    public function pdf(int $id): RedirectResponse
    {
        $estimate = $this->findOwned($id);
        return $this->redirectWithFlash('/app/quick-estimate/' . $estimate->id, 'error', 'PDF export lands in a later phase of this conversion.');
    }

    public function convertToEstimate(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('quick_estimate')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $region = $estimate->region_id ? QuickEstimateRegion::find($estimate->region_id) : null;
        $foundation = $estimate->foundation_id ? QuickEstimateFoundation::find($estimate->foundation_id) : null;
        $addons = json_decode((string) $estimate->addons_json, true) ?: [];
        $lang = $estimate->lang === 'ar' ? 'ar' : 'en';
        $items = QuickEstimateCalc::pdfItems($estimate->toArray(), $region?->toArray(), $foundation?->toArray(), $addons, $lang);

        $newEstimate = Estimate::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => null,
            'client_id' => $estimate->client_id,
            'title' => $estimate->project_name ?: ('Quick Estimate #' . $estimate->id),
            'status' => 'draft',
            'total' => $estimate->total,
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
}
