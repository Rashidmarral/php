<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BuildingType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\EstimateTemplate;
use App\Models\EstimateTemplateItem;
use App\Models\Project;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Support\AiEstimateGenerator;
use App\Support\EstimateCalc;
use App\Support\WebhookDispatcher;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EstimateController extends Controller
{
    public function index(): View
    {
        $estimates = DB::table('estimates as e')
            ->leftJoin('clients as c', 'c.id', '=', 'e.client_id')
            ->where('e.company_id', Auth::user()->company_id)
            ->orderByDesc('e.created_at')
            ->select('e.*', 'c.name as client_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.estimates.index', ['estimates' => $estimates]);
    }

    /** Landing screen: blank estimate / default template / template gallery / AI generator. */
    public function newChoice(): View
    {
        $templates = EstimateTemplate::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->toArray();
        $defaultTemplate = null;
        foreach ($templates as $t) {
            if ($t['is_default_choice']) {
                $defaultTemplate = $t;
                break;
            }
        }

        return view('app.estimates.new', [
            'templates' => $templates,
            'defaultTemplate' => $defaultTemplate,
        ]);
    }

    public function templatePreview(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('estimate_templates')) {
            return $redirect;
        }
        $template = EstimateTemplate::find($id);
        abort_if(!$template || !$template->is_active, 404, 'Template not found.');

        $items = EstimateTemplateItem::where('template_id', $template->id)->orderBy('sort_order')->orderBy('id')->get()->toArray();
        $companyId = Auth::user()->company_id;

        return view('app.estimates.template-preview', [
            'template' => $template->toArray(),
            'items' => $items,
            'subtotal' => array_sum(array_map(fn ($i) => (float) $i['default_qty'] * (float) $i['unit_cost'], $items)),
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'buildingTypes' => BuildingType::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
        ]);
    }

    public function storeFromTemplate(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('estimate_templates')) {
            return $redirect;
        }
        $template = EstimateTemplate::find($id);
        abort_if(!$template || !$template->is_active, 404, 'Template not found.');

        $companyId = Auth::user()->company_id;
        $templateItems = EstimateTemplateItem::where('template_id', $template->id)->orderBy('sort_order')->orderBy('id')->get();
        $includeQuantities = (bool) $request->input('include_quantities');

        $total = 0;
        $rows = [];
        foreach ($templateItems as $ti) {
            $qty = $includeQuantities ? (float) $ti->default_qty : 0;
            $lineTotal = $qty * (float) $ti->unit_cost;
            $total += $lineTotal;
            $rows[] = [
                'description' => $ti->description_en,
                'description_ar' => $ti->description_ar ?? '',
                'section_title' => $ti->section_number . ' ' . $ti->section_title_en,
                'section_title_ar' => $ti->section_number . ' ' . ($ti->section_title_ar ?? ''),
                'item_type' => $ti->item_type,
                'qty' => $qty,
                'uom' => $ti->uom,
                'unit_cost' => $ti->unit_cost,
                'total' => $lineTotal,
            ];
        }

        [$markupPercent, $taxRateId, $taxPercent] = $this->defaultMarkupAndTax($companyId);
        $calc = EstimateCalc::compute($total, $markupPercent, $taxPercent);

        $estimate = Estimate::create([
            'company_id' => $companyId,
            'project_id' => null,
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $companyId)?->id,
            'title' => trim((string) $request->input('title')) ?: $template->name_en,
            'title_ar' => trim((string) $request->input('title_ar', '')) ?: ($template->name_ar ?? ''),
            'status' => 'draft',
            'subtotal' => $total,
            'markup_percent' => $markupPercent,
            'markup_amount' => $calc['markup_amount'],
            'tax_rate_id' => $taxRateId,
            'tax_percent' => $taxPercent,
            'tax_amount' => $calc['tax_amount'],
            'total' => $calc['total'],
            'share_token' => bin2hex(random_bytes(20)),
            'building_type' => trim((string) $request->input('building_type', '')),
            'job_address' => trim((string) $request->input('job_address', '')),
            'template_id' => $template->id,
            'source' => 'template',
        ]);

        foreach ($rows as $row) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$row]);
        }
        WebhookDispatcher::dispatch($companyId, 'estimate.created', $estimate->toArray());

        $this->flash('success', 'Estimate created from "' . $template->name_en . '".');
        return redirect('/app/estimates/' . $estimate->id);
    }

    /** @return array{0: float, 1: ?int, 2: float} [markup_percent, tax_rate_id, tax_percent] */
    private function defaultMarkupAndTax(int $companyId): array
    {
        $markupPercent = (float) (Company::find($companyId)?->default_markup_percent ?? 0);
        $defaultTax = TaxRate::where('company_id', $companyId)->where('is_default', true)->first();
        return [$markupPercent, $defaultTax?->id, (float) ($defaultTax->rate_percent ?? 0)];
    }

    public function aiGenerator(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('ai_estimate_generator')) {
            return $redirect;
        }
        return view('app.estimates.ai', [
            'aiConfigured' => AiEstimateGenerator::isConfigured(),
        ]);
    }

    public function aiGenerate(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('ai_estimate_generator')) {
            return $redirect;
        }

        $description = trim((string) $request->input('description'));
        if ($description === '') {
            return $this->redirectWithFlash('/app/estimates/ai', 'error', 'Describe the project first.');
        }

        $result = AiEstimateGenerator::generate($description);
        $companyId = Auth::user()->company_id;

        $total = 0;
        $rows = [];
        foreach ($result['items'] as $item) {
            $lineTotal = $item['qty'] * $item['unit_cost'];
            $total += $lineTotal;
            $rows[] = [
                'description' => $item['description'],
                'section_title' => $item['section_title'],
                'item_type' => $item['item_type'],
                'qty' => $item['qty'],
                'uom' => $item['uom'],
                'unit_cost' => $item['unit_cost'],
                'total' => $lineTotal,
            ];
        }

        [$markupPercent, $taxRateId, $taxPercent] = $this->defaultMarkupAndTax($companyId);
        $calc = EstimateCalc::compute($total, $markupPercent, $taxPercent);

        $estimate = Estimate::create([
            'company_id' => $companyId,
            'project_id' => null,
            'client_id' => null,
            'title' => $result['title'],
            'status' => 'draft',
            'subtotal' => $total,
            'markup_percent' => $markupPercent,
            'markup_amount' => $calc['markup_amount'],
            'tax_rate_id' => $taxRateId,
            'tax_percent' => $taxPercent,
            'tax_amount' => $calc['tax_amount'],
            'total' => $calc['total'],
            'share_token' => bin2hex(random_bytes(20)),
            'source' => 'ai',
        ]);

        foreach ($rows as $row) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$row]);
        }
        WebhookDispatcher::dispatch($companyId, 'estimate.created', $estimate->toArray());

        if (!empty($result['note'])) {
            $this->flash('success', $result['note']);
        } else {
            $this->flash('success', 'AI-generated estimate created — review and adjust as needed.');
        }
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function create(): View
    {
        $companyId = Auth::user()->company_id;
        $materials = DB::table('materials as m')
            ->leftJoin('suppliers as s', 's.id', '=', 'm.supplier_id')
            ->where('m.company_id', $companyId)
            ->orderBy('m.category')->orderBy('m.name')
            ->select('m.*', 's.name as supplier_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        [$markupPercent, $taxRateId] = $this->defaultMarkupAndTax($companyId);

        return view('app.estimates.form', [
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'materials' => $materials,
            'units' => UnitOfMeasure::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'taxRates' => TaxRate::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'defaultMarkupPercent' => $markupPercent,
            'defaultTaxRateId' => $taxRateId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $title = trim((string) $request->input('title'));

        if ($title === '') {
            return $this->redirectWithFlash('/app/estimates/create', 'error', 'Estimate title is required.');
        }

        $sections = $request->input('item_section', []);
        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $types = $request->input('item_type', []);
        $qtys = $request->input('item_qty', []);
        $uoms = $request->input('item_uom', []);
        $costs = $request->input('item_cost', []);

        $subtotal = 0;
        $items = [];
        $lastSection = null;
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $section = trim((string) ($sections[$i] ?? ''));
            if ($section !== '') {
                $lastSection = $section;
            }
            $qty = (float) ($qtys[$i] ?? 1);
            $cost = (float) ($costs[$i] ?? 0);
            $lineTotal = $qty * $cost;
            $subtotal += $lineTotal;
            $items[] = [
                'description' => $desc,
                'description_ar' => trim((string) ($descriptionsAr[$i] ?? '')),
                'section_title' => $lastSection,
                'item_type' => in_array($types[$i] ?? '', ['labor', 'material', 'equipment', 'subcontractor', 'other'], true) ? $types[$i] : 'material',
                'qty' => $qty,
                'uom' => trim((string) ($uoms[$i] ?? '')) ?: 'each',
                'unit_cost' => $cost,
                'total' => $lineTotal,
            ];
        }

        $markupPercent = min(100, max(0, (float) $request->input('markup_percent', 0)));
        $taxRate = $this->ownedTaxRate($request->input('tax_rate_id') ?: null, $companyId);
        $taxPercent = (float) ($taxRate->rate_percent ?? 0);
        $calc = EstimateCalc::compute($subtotal, $markupPercent, $taxPercent);

        $estimate = Estimate::create([
            'company_id' => $companyId,
            'project_id' => $this->ownedProject($request->input('project_id') ?: null, $companyId)?->id,
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $companyId)?->id,
            'title' => $title,
            'title_ar' => trim((string) $request->input('title_ar', '')),
            'status' => 'draft',
            'subtotal' => $subtotal,
            'markup_percent' => $markupPercent,
            'markup_amount' => $calc['markup_amount'],
            'tax_rate_id' => $taxRate?->id,
            'tax_percent' => $taxPercent,
            'tax_amount' => $calc['tax_amount'],
            'total' => $calc['total'],
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        foreach ($items as $item) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$item]);
        }
        WebhookDispatcher::dispatch($companyId, 'estimate.created', $estimate->toArray());

        $this->flash('success', 'Estimate created.');
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function updateTotals(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $markupPercent = min(100, max(0, (float) $request->input('markup_percent', 0)));
        $taxRate = $this->ownedTaxRate($request->input('tax_rate_id') ?: null, $estimate->company_id);
        $taxPercent = (float) ($taxRate->rate_percent ?? 0);
        $calc = EstimateCalc::compute((float) $estimate->subtotal, $markupPercent, $taxPercent);

        $estimate->update([
            'markup_percent' => $markupPercent,
            'markup_amount' => $calc['markup_amount'],
            'tax_rate_id' => $taxRate?->id,
            'tax_percent' => $taxPercent,
            'tax_amount' => $calc['tax_amount'],
            'total' => $calc['total'],
        ]);

        $this->flash('success', 'Markup and tax updated.');
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function show(int $id): View
    {
        $estimate = $this->findOwned($id);
        $items = EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get()->toArray();
        $client = $this->ownedClient($estimate->client_id, $estimate->company_id);
        $project = $this->ownedProject($estimate->project_id, $estimate->company_id);

        if (empty($estimate->share_token)) {
            $estimate->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/e/' . $estimate->share_token;

        $whatsappLink = null;
        if ($client && !empty($client->phone)) {
            $company = Company::find($estimate->company_id);
            $message = "Hi {$client->name}, here's your estimate \"{$estimate->title}\" from {$company->name} — please review and sign: {$shareUrl}";
            $whatsappLink = WhatsApp::shareLink($client->phone, $message);
        }

        return view('app.estimates.show', [
            'estimate' => $estimate->toArray(),
            'items' => $items,
            'client' => $client,
            'project' => $project,
            'whatsappLink' => $whatsappLink,
            'shareUrl' => $shareUrl,
            'taxRates' => TaxRate::where('company_id', $estimate->company_id)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $status = (string) $request->input('status', 'draft');
        if (in_array($status, ['draft', 'sent', 'accepted', 'declined'], true)) {
            $estimate->update(['status' => $status]);
            $this->flash('success', 'Estimate status updated.');
        }
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        EstimateItem::where('estimate_id', $estimate->id)->delete();
        $estimate->delete();
        $this->flash('success', 'Estimate deleted.');
        return redirect('/app/estimates');
    }

    public function pdf(Request $request, int $id): Response
    {
        $estimate = $this->findOwned($id);
        $items = EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get();
        $client = $this->ownedClient($estimate->client_id, $estimate->company_id);
        $company = Company::find($estimate->company_id);
        $template = in_array($request->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $request->input('template') : 'modern';
        $lang = $request->input('lang') === 'ar' ? 'ar' : app()->getLocale();

        return $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'تسعيرة' : 'Estimate',
            'docNumber' => (string) $estimate->id,
            'docDate' => $estimate->created_at,
            'status' => ucfirst($estimate->status),
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, $company->vat_number ? 'VAT: ' . $company->vat_number : null, $company->cr_number ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'billTo' => $client ? ['name' => ($lang === 'ar' && !empty($client->name_ar)) ? $client->name_ar : $client->name, 'meta' => array_filter([$client->email ?? null, $client->phone ?? null, $client->address ?? null])] : null,
            'items' => $items->map(fn ($i) => ['description' => ($lang === 'ar' && !empty($i->description_ar)) ? $i->description_ar : $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_cost, 'total' => $i->total])->all(),
            'subtotal' => (float) $estimate->subtotal + (float) $estimate->markup_amount,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => (float) $estimate->tax_percent,
            'vatAmount' => (float) $estimate->tax_amount,
            'total' => (float) $estimate->total,
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة ' . Setting::siteName() : 'Generated by ' . Setting::siteName(),
        ], 'Estimate-' . $estimate->id . '.pdf');
    }

    private function findOwned(int $id): Estimate
    {
        $estimate = Estimate::find($id);
        abort_if(!$estimate || $estimate->company_id !== Auth::user()->company_id, 404, 'Estimate not found.');
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

    private function ownedProject(?int $id, int $companyId): ?Project
    {
        if (!$id) {
            return null;
        }
        $project = Project::find($id);
        return ($project && $project->company_id === $companyId) ? $project : null;
    }

    private function ownedTaxRate(?int $id, int $companyId): ?TaxRate
    {
        if (!$id) {
            return null;
        }
        $taxRate = TaxRate::find($id);
        return ($taxRate && $taxRate->company_id === $companyId) ? $taxRate : null;
    }
}
