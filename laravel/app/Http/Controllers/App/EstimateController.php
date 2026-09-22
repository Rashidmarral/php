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
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Support\AiEstimateGenerator;
use App\Support\EstimateCalc;
use App\Support\Sms;
use App\Support\SpreadsheetBoqImporter;
use App\Support\WebhookDispatcher;
use App\Support\WhatsApp;
use App\Support\Zatca\InvoiceChainer;
use App\Support\Zatca\ZatcaSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EstimateController extends Controller
{
    private const STATUSES = ['draft', 'sent', 'accepted', 'declined'];

    public function index(Request $request): View
    {
        $companyId = Auth::user()->company_id;
        $status = (string) $request->input('status', '');
        $q = trim((string) $request->input('q', ''));

        $query = DB::table('estimates as e')
            ->leftJoin('clients as c', 'c.id', '=', 'e.client_id')
            ->where('e.company_id', $companyId);

        if (in_array($status, self::STATUSES, true)) {
            $query->where('e.status', $status);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('e.title', 'like', "%{$q}%")
                    ->orWhere('e.title_ar', 'like', "%{$q}%")
                    ->orWhere('c.name', 'like', "%{$q}%")
                    ->orWhere('c.name_ar', 'like', "%{$q}%");
            });
        }

        $estimates = $query->orderByDesc('e.created_at')
            ->select('e.*', 'c.name as client_name', 'c.name_ar as client_name_ar')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->map(fn ($row) => [...$row, 'isExpired' => Estimate::isExpiredRow($row)])
            ->all();

        return view('app.estimates.index', [
            'estimates' => $estimates,
            'statusFilter' => $status,
            'statuses' => self::STATUSES,
            'q' => $q,
            'counts' => $this->statusCounts($companyId),
            'stats' => $this->pipelineStats($companyId),
        ]);
    }

    /** Per-status counts across the whole company (not narrowed by the current filter/search) for the filter toolbar — same pattern as LeadController::statusCounts(). */
    private function statusCounts(int $companyId): array
    {
        $rows = DB::table('estimates')->where('company_id', $companyId)->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->get();
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = (int) $row->c;
            }
        }
        return $counts;
    }

    /**
     * Sales-pipeline summary shown above the estimate list: total count, total
     * pipeline value (everything not declined — a draft/sent/accepted estimate is
     * still "in play"), and a win rate computed the same way as the estimate win
     * rate on the Reports > Performance tab (accepted ÷ (accepted+declined)).
     */
    private function pipelineStats(int $companyId): array
    {
        $all = Estimate::where('company_id', $companyId)->get(['status', 'total']);
        $accepted = $all->where('status', 'accepted')->count();
        $declined = $all->where('status', 'declined')->count();
        $decided = $accepted + $declined;

        return [
            'count' => $all->count(),
            'pipelineValue' => (float) $all->where('status', '!=', 'declined')->sum('total'),
            'winRate' => $decided > 0 ? round($accepted / $decided * 100) : null,
        ];
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
            'valid_until' => now()->addDays(30)->toDateString(),
            ...$this->approvalFieldsForNewEstimate($companyId),
        ]);

        foreach ($rows as $row) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$row]);
        }
        WebhookDispatcher::dispatch($companyId, 'estimate.created', $estimate->toArray());

        $this->flash('success', t('user.estimates.created_from_template', ['template' => $template->name_en]));
        return redirect('/app/estimates/' . $estimate->id);
    }

    /**
     * When the company has opted into requiring internal approval for
     * estimates, a newly created one starts out pending instead of the
     * column's 'not_required' default — otherwise this returns [] and the
     * estimate behaves exactly as it did before this feature existed.
     */
    private function approvalFieldsForNewEstimate(int $companyId): array
    {
        $company = Company::find($companyId);
        if (!$company || !$company->requiresEstimateApproval()) {
            return [];
        }
        return [
            'approval_status' => 'pending',
            'approval_requested_by' => Auth::id(),
            'approval_requested_at' => now(),
        ];
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
        $companyId = Auth::user()->company_id;

        $imagePath = null;
        $fullImagePath = null;
        $photo = $request->file('photo');
        if ($photo && $photo->isValid()) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = $photo->getMimeType();
            if (!isset($allowed[$mime])) {
                return $this->redirectWithFlash('/app/estimates/ai', 'error', t('user.estimates.photo_type_invalid'));
            }
            if ($photo->getSize() > 8 * 1024 * 1024) {
                return $this->redirectWithFlash('/app/estimates/ai', 'error', t('user.estimates.photo_max_size'));
            }
            $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
            $photo->move(public_path("uploads/ai-estimate-tmp/{$companyId}"), $filename);
            $imagePath = "/uploads/ai-estimate-tmp/{$companyId}/{$filename}";
            $fullImagePath = public_path($imagePath);
        }

        if ($description === '' && $imagePath === null) {
            return $this->redirectWithFlash('/app/estimates/ai', 'error', t('user.estimates.description_or_photo_required'));
        }

        // The uploaded photo is transient, one-shot AI input — there's no Estimate row to
        // attach it to yet (one isn't created until after generation, below), so it's
        // deleted once the AI call has run, whether that call succeeds, fails, or throws.
        try {
            $result = AiEstimateGenerator::generate($description, $imagePath);
        } finally {
            if ($fullImagePath) {
                @unlink($fullImagePath);
            }
        }

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
            'valid_until' => now()->addDays(30)->toDateString(),
            ...$this->approvalFieldsForNewEstimate($companyId),
        ]);

        foreach ($rows as $row) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$row]);
        }
        WebhookDispatcher::dispatch($companyId, 'estimate.created', $estimate->toArray());

        if (!empty($result['note'])) {
            $this->flash('success', $result['note']);
        } else {
            $this->flash('success', t('user.estimates.ai_created'));
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
            return $this->redirectWithFlash('/app/estimates/create', 'error', t('user.estimates.title_required'));
        }

        $sections = $request->input('item_section', []);
        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $types = $request->input('item_type', []);
        $qtys = $request->input('item_qty', []);
        $uoms = $request->input('item_uom', []);
        $costs = $request->input('item_cost', []);
        $optionals = $request->input('item_optional', []);

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
            $isOptional = !empty($optionals[$i]);
            // Optional add-ons still get their own real line total, but they
            // must never inflate the estimate's own quoted subtotal/total —
            // those stay the "base quote" of required items only.
            if (!$isOptional) {
                $subtotal += $lineTotal;
            }
            $items[] = [
                'description' => $desc,
                'description_ar' => trim((string) ($descriptionsAr[$i] ?? '')),
                'section_title' => $lastSection,
                'item_type' => in_array($types[$i] ?? '', ['labor', 'material', 'equipment', 'subcontractor', 'other'], true) ? $types[$i] : 'material',
                'qty' => $qty,
                'uom' => trim((string) ($uoms[$i] ?? '')) ?: 'each',
                'unit_cost' => $cost,
                'total' => $lineTotal,
                'is_optional' => $isOptional,
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
            'valid_until' => trim((string) $request->input('valid_until', '')) ?: now()->addDays(30)->toDateString(),
            ...$this->approvalFieldsForNewEstimate($companyId),
        ]);

        foreach ($items as $item) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$item]);
        }
        WebhookDispatcher::dispatch($companyId, 'estimate.created', $estimate->toArray());

        $this->flash('success', t('user.estimates.created'));
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function edit(int $id): View|RedirectResponse
    {
        $estimate = $this->findOwned($id);
        if ($redirect = $this->assertEditable($estimate)) {
            return $redirect;
        }
        $companyId = $estimate->company_id;
        $materials = DB::table('materials as m')
            ->leftJoin('suppliers as s', 's.id', '=', 'm.supplier_id')
            ->where('m.company_id', $companyId)
            ->orderBy('m.category')->orderBy('m.name')
            ->select('m.*', 's.name as supplier_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.estimates.edit', [
            'estimate' => $estimate->toArray(),
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'materials' => $materials,
            'units' => UnitOfMeasure::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'taxRates' => TaxRate::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'prefillItems' => $this->prefillItemRows($estimate),
        ]);
    }

    /**
     * Rebuilds the item_section[]-style rows a browser would have submitted for
     * this estimate's current items, so edit() can prefill the same line-items
     * table store()/update() parse back out of the request — a section name is
     * only carried on the row where it first appears, blank on the rest of that
     * section's rows, exactly mirroring store()'s $lastSection logic.
     */
    private function prefillItemRows(Estimate $estimate): array
    {
        $lastSection = null;
        $rows = [];
        foreach (EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get() as $item) {
            $isNewSection = $item->section_title !== $lastSection;
            if ($isNewSection) {
                $lastSection = $item->section_title;
            }
            $rows[] = [
                'item_section' => $isNewSection ? (string) $item->section_title : '',
                'description' => $item->description,
                'description_ar' => $item->description_ar,
                'item_type' => $item->item_type,
                'qty' => (float) $item->qty,
                'uom' => $item->uom,
                'unit_cost' => (float) $item->unit_cost,
                'is_optional' => (bool) $item->is_optional,
            ];
        }
        return $rows;
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        if ($redirect = $this->assertEditable($estimate)) {
            return $redirect;
        }
        $companyId = $estimate->company_id;
        $title = trim((string) $request->input('title'));

        if ($title === '') {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id . '/edit', 'error', t('user.estimates.title_required'));
        }

        $sections = $request->input('item_section', []);
        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $types = $request->input('item_type', []);
        $qtys = $request->input('item_qty', []);
        $uoms = $request->input('item_uom', []);
        $costs = $request->input('item_cost', []);
        $optionals = $request->input('item_optional', []);

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
            $isOptional = !empty($optionals[$i]);
            // Same rule as store(): optional add-ons never inflate the
            // estimate's own quoted subtotal/total.
            if (!$isOptional) {
                $subtotal += $lineTotal;
            }
            $items[] = [
                'description' => $desc,
                'description_ar' => trim((string) ($descriptionsAr[$i] ?? '')),
                'section_title' => $lastSection,
                'item_type' => in_array($types[$i] ?? '', ['labor', 'material', 'equipment', 'subcontractor', 'other'], true) ? $types[$i] : 'material',
                'qty' => $qty,
                'uom' => trim((string) ($uoms[$i] ?? '')) ?: 'each',
                'unit_cost' => $cost,
                'total' => $lineTotal,
                'is_optional' => $isOptional,
            ];
        }

        $markupPercent = min(100, max(0, (float) $request->input('markup_percent', 0)));
        $taxRate = $this->ownedTaxRate($request->input('tax_rate_id') ?: null, $companyId);
        $taxPercent = (float) ($taxRate->rate_percent ?? 0);
        $calc = EstimateCalc::compute($subtotal, $markupPercent, $taxPercent);

        EstimateItem::where('estimate_id', $estimate->id)->delete();
        foreach ($items as $item) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$item]);
        }

        $estimate->update([
            'title' => $title,
            'title_ar' => trim((string) $request->input('title_ar', '')),
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $companyId)?->id,
            'project_id' => $this->ownedProject($request->input('project_id') ?: null, $companyId)?->id,
            'subtotal' => $subtotal,
            'markup_percent' => $markupPercent,
            'markup_amount' => $calc['markup_amount'],
            'tax_rate_id' => $taxRate?->id,
            'tax_percent' => $taxPercent,
            'tax_amount' => $calc['tax_amount'],
            'total' => $calc['total'],
            'valid_until' => trim((string) $request->input('valid_until', '')) ?: now()->addDays(30)->toDateString(),
        ]);

        $this->flash('success', t('user.estimates.updated'));
        return redirect('/app/estimates/' . $estimate->id);
    }

    /**
     * Bulk-appends new line items parsed from an uploaded spreadsheet to an
     * existing estimate — never replaces the items already there, mirroring
     * store()/update()'s own "these are additional rows" semantics rather
     * than a destructive re-import. Gated by the exact same assertEditable()
     * rule as update()/updateTotals(): once a client has signed (accepted),
     * an import can no longer silently change the numbers under that
     * signature either.
     */
    public function importItems(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        if ($redirect = $this->assertEditable($estimate)) {
            return $redirect;
        }
        $editUrl = '/app/estimates/' . $estimate->id . '/edit';

        $file = $request->file('file');
        if (!$file) {
            return $this->redirectWithFlash($editUrl, 'error', t('user.estimates.import_file_required'));
        }

        $result = SpreadsheetBoqImporter::parse($file, 'estimate');
        if ($result['error'] !== null) {
            return $this->redirectWithFlash($editUrl, 'error', $result['error']);
        }

        $addedSubtotal = 0.0;
        foreach ($result['valid'] as $row) {
            $lineTotal = round($row['qty'] * $row['unit_price'], 2);
            $addedSubtotal += $lineTotal;
            EstimateItem::create([
                'estimate_id' => $estimate->id,
                'description' => $row['description'],
                'section_title' => $row['section'] ?: null,
                'item_type' => $row['type'],
                'qty' => $row['qty'],
                'uom' => $row['uom'] ?: 'each',
                'unit_cost' => $row['unit_price'],
                'total' => $lineTotal,
                'is_optional' => false,
            ]);
        }

        // Recompute the same way store()/update() do, keeping the estimate's
        // existing markup/tax selection — an import only ever adds cost, it
        // never touches those choices.
        if ($addedSubtotal > 0) {
            $subtotal = (float) $estimate->subtotal + $addedSubtotal;
            $calc = EstimateCalc::compute($subtotal, (float) $estimate->markup_percent, (float) $estimate->tax_percent);
            $estimate->update([
                'subtotal' => $subtotal,
                'markup_amount' => $calc['markup_amount'],
                'tax_amount' => $calc['tax_amount'],
                'total' => $calc['total'],
            ]);
        }

        $this->flashImportResult($result, 'user.estimates.import_summary', 'user.estimates.import_row_error', 'user.estimates.import_no_rows');

        return redirect($editUrl);
    }

    /** Downloadable CSV template so users know the exact headers/column order importItems() expects, with one example row — see Controller::streamCsvTemplate(). */
    public function importTemplate(): Response
    {
        return $this->streamCsvTemplate('estimate', 'estimate-import-template.csv');
    }

    public function duplicate(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $source = $this->findOwned($id);
        $companyId = $source->company_id;

        $estimate = Estimate::create([
            'company_id' => $companyId,
            'project_id' => $source->project_id,
            'client_id' => $source->client_id,
            'template_id' => $source->template_id,
            'title' => $source->title . ' (Copy)',
            'title_ar' => !empty($source->title_ar) ? ($source->title_ar . ' (نسخة)') : $source->title_ar,
            'status' => 'draft',
            'subtotal' => $source->subtotal,
            'markup_percent' => $source->markup_percent,
            'markup_amount' => $source->markup_amount,
            'tax_rate_id' => $source->tax_rate_id,
            'tax_percent' => $source->tax_percent,
            'tax_amount' => $source->tax_amount,
            'total' => $source->total,
            'building_type' => $source->building_type,
            'job_address' => $source->job_address,
            'source' => $source->source,
            'share_token' => bin2hex(random_bytes(20)),
            // A duplicate is a fresh quote with its own clock and its own
            // client — never inherit the source's validity window or view
            // history, same reasoning as the signature fields above.
            'valid_until' => now()->addDays(30)->toDateString(),
            ...$this->approvalFieldsForNewEstimate($companyId),
        ]);

        foreach (EstimateItem::where('estimate_id', $source->id)->orderBy('id')->get() as $item) {
            EstimateItem::create([
                'estimate_id' => $estimate->id,
                'description' => $item->description,
                'description_ar' => $item->description_ar,
                'qty' => $item->qty,
                'unit_cost' => $item->unit_cost,
                'total' => $item->total,
                'item_type' => $item->item_type,
                'uom' => $item->uom,
                'section_title' => $item->section_title,
                'section_title_ar' => $item->section_title_ar,
                // is_optional is a property of the line item itself, so it
                // carries over — but client_selected is a decision made by a
                // specific signer and is deliberately NOT copied: a duplicate
                // is unsigned, so it defaults to the column's null ("not yet
                // decided"), same reasoning as the signed_* fields above.
                'is_optional' => $item->is_optional,
            ]);
        }
        WebhookDispatcher::dispatch($companyId, 'estimate.created', $estimate->toArray());

        $this->flash('success', t('user.estimates.duplicated'));
        return redirect('/app/estimates/' . $estimate->id);
    }

    /**
     * Bills the client the sell price an accepted estimate already promised
     * them, not the internal cost — the invoice this creates must reconcile
     * line-by-line with the estimate's subtotal+markup. Only allowed once,
     * from an accepted estimate; see convertToInvoice()'s guards.
     */
    public function convertToInvoice(int $id, ZatcaSyncService $zatcaSync): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);

        if ($estimate->status !== 'accepted') {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.not_accepted_for_invoice'));
        }
        if (Invoice::where('source_estimate_id', $estimate->id)->exists()) {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.already_converted'));
        }

        $companyId = $estimate->company_id;
        $company = Company::find($companyId);
        $client = $this->ownedClient($estimate->client_id, $companyId);
        $project = $this->ownedProject($estimate->project_id, $companyId);

        // Bill only what the client actually agreed to: required items plus
        // any optional add-on they selected when they signed — never an
        // optional item they were offered but didn't choose.
        $items = array_map(fn ($i) => [
            'description' => $i['description'],
            'description_ar' => $i['description_ar'],
            'qty' => $i['qty'],
            'unit_price' => $i['unit_price'],
            'total' => $i['total'],
        ], self::billableItems($estimate));
        $subtotal = array_sum(array_column($items, 'total'));
        $vatRate = (float) $estimate->tax_percent;
        $vatAmount = round($subtotal * $vatRate / 100, 2);
        $retentionPercent = (float) ($company->default_retention_percent ?? 0);
        $retentionAmount = round($subtotal * $retentionPercent / 100, 2);

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'project_id' => $project?->id,
            'client_id' => $client?->id,
            'source_estimate_id' => $estimate->id,
            'invoice_number' => 'INV-' . (1000 + Invoice::where('company_id', $companyId)->count() + 1),
            'status' => 'unpaid',
            'total' => round($subtotal + $vatAmount, 2),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'due_date' => null,
            'retention_percent' => $retentionPercent,
            'retention_amount' => $retentionAmount,
            'share_token' => bin2hex(random_bytes(20)),
            ...$this->approvalFieldsForNewInvoice($companyId),
        ]);

        foreach ($items as $item) {
            InvoiceItem::create(['invoice_id' => $invoice->id, ...$item]);
        }

        InvoiceChainer::chain($invoice->fresh(), $company, $client, $items, $zatcaSync);
        WebhookDispatcher::dispatch($companyId, 'invoice.created', $invoice->fresh()->toArray());

        $this->flash('success', t('user.estimates.invoice_created', ['number' => $invoice->invoice_number]));
        return redirect('/app/invoices/' . $invoice->id);
    }

    /**
     * When the company has opted into requiring internal approval for
     * invoices, a newly created one starts out pending instead of the
     * column's 'not_required' default. Exact analogue of
     * InvoiceController::approvalFieldsForNewInvoice() — duplicated rather
     * than shared since it's 8 lines and each controller already keeps its
     * own approvalFieldsForNew*() copy (see approvalFieldsForNewEstimate()
     * above), matching this app's existing precedent for this exact helper.
     */
    private function approvalFieldsForNewInvoice(int $companyId): array
    {
        $company = Company::find($companyId);
        if (!$company || !$company->requiresInvoiceApproval()) {
            return [];
        }
        return [
            'approval_status' => 'pending',
            'approval_requested_by' => Auth::id(),
            'approval_requested_at' => now(),
        ];
    }

    /**
     * Once a client has actually signed (status=accepted), the estimate's
     * numbers must not silently change under a signature that's already
     * been given — duplicate() is the intended way to revise it instead.
     * 'declined' is deliberately NOT locked: a contractor must be able to
     * revise and resend a declined quote. Same ?RedirectResponse-return
     * convention as requireAbility()/requireFeature(): null means OK, a
     * redirect means blocked.
     */
    private function assertEditable(Estimate $estimate): ?RedirectResponse
    {
        if ($estimate->status !== 'accepted') {
            return null;
        }
        return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.locked_signed'));
    }

    public function updateTotals(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        if ($redirect = $this->assertEditable($estimate)) {
            return $redirect;
        }
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

        $this->flash('success', t('user.estimates.markup_tax_updated'));
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function show(int $id): View
    {
        $estimate = $this->findOwned($id);
        $items = EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get()->toArray();
        // Optional add-ons are shown separately from the required-items table,
        // at their client-facing sell price (not raw cost) — see
        // sellPricedItems() and the "how to change sellPricedItems()'s callers
        // safely" design this feature follows.
        $optionalItems = array_values(array_filter(self::sellPricedItems($estimate), fn ($i) => !empty($i['is_optional'])));
        $client = $this->ownedClient($estimate->client_id, $estimate->company_id);
        $project = $this->ownedProject($estimate->project_id, $estimate->company_id);

        if (empty($estimate->share_token)) {
            $estimate->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/e/' . $estimate->share_token;
        $approvalBlocked = $estimate->isApprovalBlocked();

        $company = Company::find($estimate->company_id);
        $whatsappLink = null;
        if (!$approvalBlocked && $client && !empty($client->phone)) {
            $message = "Hi {$client->name}, here's your estimate \"{$estimate->title}\" from {$company->name} — please review and sign: {$shareUrl}";
            $whatsappLink = WhatsApp::shareLink($client->phone, $message);
        }

        // See InvoiceController::approverPingMessage()'s docblock for why this is sent to the
        // company's own phone rather than a specific approver's — same convention, reused here.
        $approverWhatsappLink = null;
        if ($estimate->approval_status === 'pending' && (int) $estimate->approval_requested_by === (int) Auth::id() && !empty($company->phone)) {
            $approverWhatsappLink = WhatsApp::shareLink($company->phone, $this->approverPingMessage($estimate, $company));
        }

        return view('app.estimates.show', [
            'estimate' => $estimate->toArray(),
            'items' => $items,
            'optionalItems' => $optionalItems,
            'client' => $client,
            'project' => $project,
            'activeTemplate' => $company->activeInvoiceTemplate(),
            'whatsappLink' => $whatsappLink,
            'approverWhatsappLink' => $approverWhatsappLink,
            'whatsappApiConfigured' => WhatsApp::isConfigured(),
            'smsApiConfigured' => Sms::isConfigured(),
            'shareUrl' => $shareUrl,
            'approvalBlocked' => $approvalBlocked,
            'isExpired' => $estimate->isExpired(),
            'taxRates' => TaxRate::where('company_id', $estimate->company_id)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'convertedInvoice' => Invoice::where('source_estimate_id', $estimate->id)->first()?->toArray(),
        ]);
    }

    public function sendSms(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $client = $this->ownedClient($estimate->client_id, $estimate->company_id);
        $company = Company::find($estimate->company_id);

        if ($estimate->isApprovalBlocked()) {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.awaiting_approval_cannot_send'));
        }
        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.no_client_phone'));
        }
        if (empty($estimate->share_token)) {
            $estimate->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/e/' . $estimate->share_token;

        $message = "Hi {$client->name}, here's your estimate \"{$estimate->title}\" from {$company->name} — please review and sign: {$shareUrl}";
        $result = Sms::sendMessage($client->phone, $message);

        if (!empty($result['ok'])) {
            $this->flash('success', t('common.sms_notification_sent'));
        } else {
            $this->flash('error', t('common.sms_send_failed', ['error' => $result['error'] ?? json_encode($result['data'] ?? $result)]));
        }
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $status = (string) $request->input('status', 'draft');
        if ($status === 'sent' && $estimate->isApprovalBlocked()) {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.awaiting_approval_cannot_send'));
        }
        if (in_array($status, ['draft', 'sent', 'accepted', 'declined'], true)) {
            $estimate->update(['status' => $status]);
            $this->flash('success', t('user.estimates.status_updated'));
        }
        return redirect('/app/estimates/' . $estimate->id);
    }

    /** Owner/admin sign-off that clears a pending estimate to reach the client. A non-pending estimate is left untouched. */
    public function approve(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        if ($estimate->approval_status !== 'pending') {
            $this->flash('error', t('user.estimates.not_awaiting_approval'));
            return redirect('/app/estimates/' . $estimate->id);
        }
        $estimate->update(['approval_status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()]);
        $this->flash('success', t('user.estimates.approved'));
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        if ($estimate->approval_status !== 'pending') {
            $this->flash('error', t('user.estimates.not_awaiting_approval'));
            return redirect('/app/estimates/' . $estimate->id);
        }
        $estimate->update([
            'approval_status' => 'rejected',
            'rejection_reason' => trim((string) $request->input('reason', '')) ?: null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
        $this->flash('success', t('user.estimates.rejected'));
        return redirect('/app/estimates/' . $estimate->id);
    }

    /** Same "whoever can approve, on the company's own phone" convention as InvoiceController::approverPingMessage() — see its docblock. */
    private function approverPingMessage(Estimate $estimate, ?Company $company): string
    {
        $requesterName = Auth::user()->name;
        $link = rtrim((string) config('app.url'), '/') . '/app/estimates/' . $estimate->id;
        return "Hi, {$requesterName} is waiting on your approval for estimate \"{$estimate->title}\" at " . ($company->name ?? '') . ". Please review: {$link}";
    }

    /** Lets the person who requested approval nudge their own approver — see approverPingMessage()'s docblock for how "the approver's phone" is determined. */
    public function notifyApprover(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $company = Company::find($estimate->company_id);

        if ($estimate->approval_status !== 'pending') {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.not_awaiting_approval'));
        }
        if ((int) $estimate->approval_requested_by !== (int) Auth::id()) {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.only_requester_can_remind'));
        }
        if (empty($company->phone)) {
            return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', t('user.estimates.no_company_phone'));
        }

        $result = WhatsApp::sendMessage($company->phone, $this->approverPingMessage($estimate, $company));

        if (!empty($result['ok'])) {
            $this->flash('success', t('common.whatsapp_approval_sent'));
        } else {
            $this->flash('error', t('common.whatsapp_send_failed', ['error' => $result['error'] ?? json_encode($result['data'] ?? $result)]));
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
        $this->flash('success', t('user.estimates.deleted'));
        return redirect('/app/estimates');
    }

    public function pdf(Request $request, int $id): Response
    {
        $estimate = $this->findOwned($id);
        $client = $this->ownedClient($estimate->client_id, $estimate->company_id);
        $company = Company::find($estimate->company_id);
        $template = in_array($request->input('template'), Company::INVOICE_TEMPLATES, true) ? $request->input('template') : $company->activeInvoiceTemplate();
        $lang = $request->input('lang') === 'ar' ? 'ar' : app()->getLocale();

        // Client-facing figures must reconcile: line items are the sell price
        // (cost scaled by markup), and subtotal/VAT/total are derived from those
        // same lines rather than the estimate's stored cost-based subtotal — see
        // sellPricedItems() above. Only REQUIRED items drive the PDF — the shared
        // document.blade.php template has no notion of a line item that's
        // excluded from the printed Subtotal, and listing optional add-ons
        // alongside required ones would make the item list visually sum to more
        // than the Subtotal/Total shown (exactly the reconciliation bug the
        // prior two commits fixed), so optional items are omitted from the PDF
        // for this v1 — they're only shown, with their own toggle, on the web
        // share page and the internal show page.
        $sellItems = self::requiredItems(self::sellPricedItems($estimate));
        $items = array_map(fn ($i) => [
            'description' => ($lang === 'ar' && !empty($i['description_ar'])) ? $i['description_ar'] : $i['description'],
            'qty' => $i['qty'],
            'unit_price' => $i['unit_price'],
            'total' => $i['total'],
        ], $sellItems);
        $subtotal = array_sum(array_column($sellItems, 'total'));
        $vatAmount = round($subtotal * (float) $estimate->tax_percent / 100, 2);
        $total = round($subtotal + $vatAmount, 2);

        return $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'تسعيرة' : 'Estimate',
            'docNumber' => (string) $estimate->id,
            'docDate' => $estimate->created_at,
            'validUntil' => $estimate->valid_until ? \Illuminate\Support\Carbon::parse($estimate->valid_until)->format('d M Y') : null,
            'status' => ucfirst($estimate->status),
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, $company->vat_number ? 'VAT: ' . $company->vat_number : null, $company->cr_number ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'billTo' => $client ? ['name' => ($lang === 'ar' && !empty($client->name_ar)) ? $client->name_ar : $client->name, 'meta' => array_filter([$client->email ?? null, $client->phone ?? null, $client->address ?? null])] : null,
            'items' => $items,
            'subtotal' => $subtotal,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => (float) $estimate->tax_percent,
            'vatAmount' => $vatAmount,
            'total' => $total,
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة ' . Setting::siteName() : 'Generated by ' . Setting::siteName(),
        ], 'Estimate-' . $estimate->id . '.pdf');
    }

    private function findOwned(int $id): Estimate
    {
        $estimate = Estimate::find($id);
        abort_if(!$estimate || $estimate->company_id !== Auth::user()->company_id, 404, 'Estimate not found.');
        return $estimate;
    }

    /**
     * Each item's cost scaled by the estimate's markup, rounded to 2dp — this is
     * what the client is actually paying per line, needed anywhere a client-facing
     * total must reconcile line-by-line with subtotal+markup: an invoice generated
     * from this estimate via convertToInvoice() above, this controller's own pdf(),
     * and Site\ShareController's public estimate()/estimatePdf() (which call this
     * directly — public static so it can be shared as the single source of truth
     * for sell-price math instead of a second, divergent copy of the formula).
     */
    public static function sellPricedItems(Estimate $estimate): array
    {
        $factor = 1 + ((float) $estimate->markup_percent / 100);
        return EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'description' => $i->description,
                'description_ar' => $i->description_ar,
                'qty' => (float) $i->qty,
                'unit_price' => round((float) $i->unit_cost * $factor, 2),
                'total' => round((float) $i->qty * $i->unit_cost * $factor, 2),
                'is_optional' => (bool) $i->is_optional,
                'client_selected' => $i->client_selected === null ? null : (bool) $i->client_selected,
            ])->all();
    }

    /**
     * Keeps only the non-optional rows of a sellPricedItems() result — the
     * "base quote" that drives the headline Subtotal/VAT/Total everywhere a
     * client sees this estimate before they've chosen any add-ons. On an
     * estimate with zero optional items this is a no-op: it returns the
     * exact same array, so every existing reconciliation invariant holds.
     */
    public static function requiredItems(array $sellPricedItems): array
    {
        return array_values(array_filter($sellPricedItems, fn ($i) => empty($i['is_optional'])));
    }

    /**
     * What an accepted estimate should actually be billed for: every
     * required item plus any optional add-on the client selected when they
     * signed. Used only by convertToInvoice() — never by the client-facing
     * PDF/web totals, which must show required-only until a decision exists.
     */
    public static function billableItems(Estimate $estimate): array
    {
        return array_values(array_filter(
            self::sellPricedItems($estimate),
            fn ($i) => empty($i['is_optional']) || !empty($i['client_selected'])
        ));
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
