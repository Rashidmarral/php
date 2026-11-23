<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Core\Lang;
use App\Core\QuickEstimateCalc;
use App\Core\Settings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\QuickEstimate;
use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateRegion;

class QuickEstimateController extends Controller
{
    public function __construct()
    {
        Feature::requireOrRedirect('quick_estimate');
    }

    public function index(): void
    {
        $companyId = Auth::companyId();
        $regions = QuickEstimateRegion::query('SELECT * FROM quick_estimate_regions WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $foundations = QuickEstimateFoundation::query('SELECT * FROM quick_estimate_foundations WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $addons = QuickEstimateAddon::query('SELECT * FROM quick_estimate_addons WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $clients = Client::where('company_id', $companyId, 'name ASC');
        $quotes = QuickEstimate::query(
            'SELECT qe.*, r.name_en AS region_name, f.name_en AS foundation_name, c.name AS client_name
             FROM quick_estimates qe
             LEFT JOIN quick_estimate_regions r ON r.id = qe.region_id
             LEFT JOIN quick_estimate_foundations f ON f.id = qe.foundation_id
             LEFT JOIN clients c ON c.id = qe.client_id
             WHERE qe.company_id = ? ORDER BY qe.created_at DESC',
            [$companyId]
        )->fetchAll();

        $this->view('user/quick-estimate/index', [
            'pageTitle' => 'Quick Estimate',
            'regions' => $regions,
            'foundations' => $foundations,
            'addons' => $addons,
            'clients' => $clients,
            'quotes' => $quotes,
            'vatRate' => (float) Settings::get('vat_rate', 15),
        ], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $companyId = Auth::companyId();

        $region = QuickEstimateRegion::find((int) $this->input('region_id'));
        $foundation = QuickEstimateFoundation::find((int) $this->input('foundation_id'));
        $totalArea = max(0, (float) $this->input('total_area', 0));
        $discountPercent = min(100, max(0, (float) $this->input('discount_percent', 0)));
        $vatRate = (float) Settings::get('vat_rate', 15);

        if (!$region || !$foundation || $totalArea <= 0) {
            $this->flash('error', 'Please choose a region, a foundation type, and enter a total area.');
            self::redirect('/app/quick-estimate');
        }

        $selectedAddonIds = array_map('intval', (array) $this->input('addons', []));
        $addonRows = [];
        if (!empty($selectedAddonIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedAddonIds), '?'));
            $addonRows = QuickEstimateAddon::query("SELECT * FROM quick_estimate_addons WHERE id IN ({$placeholders})", $selectedAddonIds)->fetchAll();
        }

        $result = QuickEstimateCalc::compute($region, $foundation, $addonRows, $totalArea, $discountPercent, $vatRate);

        $clientId = $this->input('client_id') ?: null;
        $client = $clientId ? Client::find((int) $clientId) : null;

        $id = QuickEstimate::create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'project_name' => trim((string) $this->input('project_name')) ?: null,
            'region_id' => $region['id'],
            'foundation_id' => $foundation['id'],
            'total_area' => $totalArea,
            'discount_percent' => $discountPercent,
            'addons_json' => json_encode($result['addons_payload']),
            'subtotal' => $result['subtotal'],
            'vat_amount' => $result['vat_amount'],
            'total' => $result['total'],
            'lang' => Lang::locale(),
            'contact_name' => $client['name'] ?? null,
            'contact_email' => $client['email'] ?? null,
            'contact_phone' => $client['phone'] ?? null,
            'status' => 'internal',
        ]);

        $this->flash('success', 'Quick estimate generated.');
        self::redirect('/app/quick-estimate/' . $id);
    }

    public function show(string $id): void
    {
        $estimate = $this->findOwned((int) $id);
        $region = $estimate['region_id'] ? QuickEstimateRegion::find((int) $estimate['region_id']) : null;
        $foundation = $estimate['foundation_id'] ? QuickEstimateFoundation::find((int) $estimate['foundation_id']) : null;
        $addons = json_decode((string) $estimate['addons_json'], true) ?: [];
        $client = $estimate['client_id'] ? Client::find((int) $estimate['client_id']) : null;

        $this->view('user/quick-estimate/show', [
            'pageTitle' => $estimate['project_name'] ?: ('Quick Estimate #' . $estimate['id']),
            'estimate' => $estimate,
            'region' => $region,
            'foundation' => $foundation,
            'addons' => $addons,
            'client' => $client,
            'vatRate' => (float) Settings::get('vat_rate', 15),
        ], 'layouts/app');
    }

    public function pdf(string $id): void
    {
        $estimate = $this->findOwned((int) $id);
        $region = $estimate['region_id'] ? QuickEstimateRegion::find((int) $estimate['region_id']) : null;
        $foundation = $estimate['foundation_id'] ? QuickEstimateFoundation::find((int) $estimate['foundation_id']) : null;
        $addons = json_decode((string) $estimate['addons_json'], true) ?: [];
        $lang = $this->input('lang') === 'ar' ? 'ar' : ($estimate['lang'] === 'ar' ? 'ar' : 'en');
        $template = in_array($this->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $this->input('template') : 'modern';
        $company = Company::find((int) $estimate['company_id']);

        $items = QuickEstimateCalc::pdfItems($estimate, $region, $foundation, $addons, $lang);

        $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'تسعيرة سريعة' : 'Quick Estimate',
            'docNumber' => (string) $estimate['id'],
            'docDate' => $estimate['created_at'],
            'issuer' => ['name' => $company['name'] ?? '', 'meta' => array_filter([$company['phone'] ?? null, ($company['vat_number'] ?? null) ? 'VAT: ' . $company['vat_number'] : null])],
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

    public function convertToEstimate(string $id): void
    {
        $this->verifyCsrf();
        $estimate = $this->findOwned((int) $id);
        $region = $estimate['region_id'] ? QuickEstimateRegion::find((int) $estimate['region_id']) : null;
        $foundation = $estimate['foundation_id'] ? QuickEstimateFoundation::find((int) $estimate['foundation_id']) : null;
        $addons = json_decode((string) $estimate['addons_json'], true) ?: [];
        $lang = $estimate['lang'] === 'ar' ? 'ar' : 'en';
        $items = QuickEstimateCalc::pdfItems($estimate, $region, $foundation, $addons, $lang);

        $companyId = Auth::companyId();
        $newId = Estimate::create([
            'company_id' => $companyId,
            'project_id' => null,
            'client_id' => $estimate['client_id'],
            'title' => $estimate['project_name'] ?: ('Quick Estimate #' . $estimate['id']),
            'status' => 'draft',
            'total' => $estimate['total'],
        ]);
        foreach ($items as $item) {
            EstimateItem::create([
                'estimate_id' => $newId,
                'description' => $item['description'],
                'qty' => $item['qty'],
                'unit_cost' => $item['unit_price'],
                'total' => $item['total'],
            ]);
        }

        $this->flash('success', 'Converted to a formal estimate.');
        self::redirect('/app/estimates/' . $newId);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $estimate = $this->findOwned((int) $id);
        QuickEstimate::delete($estimate['id']);
        $this->flash('success', 'Quick estimate deleted.');
        self::redirect('/app/quick-estimate');
    }

    private function findOwned(int $id): array
    {
        $estimate = QuickEstimate::find($id);
        if (!$estimate || (int) $estimate['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Quick estimate not found.');
        }
        return $estimate;
    }
}
