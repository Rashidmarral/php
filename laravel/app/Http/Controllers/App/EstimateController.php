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
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $estimate = Estimate::create([
            'company_id' => $companyId,
            'project_id' => null,
            'client_id' => $request->input('client_id') ?: null,
            'title' => trim((string) $request->input('title')) ?: $template->name_en,
            'title_ar' => trim((string) $request->input('title_ar', '')) ?: ($template->name_ar ?? ''),
            'status' => 'draft',
            'total' => $total,
            'share_token' => bin2hex(random_bytes(20)),
            'building_type' => trim((string) $request->input('building_type', '')),
            'job_address' => trim((string) $request->input('job_address', '')),
            'template_id' => $template->id,
            'source' => 'template',
        ]);

        foreach ($rows as $row) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$row]);
        }

        $this->flash('success', 'Estimate created from "' . $template->name_en . '".');
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function aiGenerator(): RedirectResponse
    {
        return $this->redirectWithFlash('/app/estimates/new', 'error', 'The AI estimate generator lands in a later phase of this conversion.');
    }

    public function aiGenerate(): RedirectResponse
    {
        return $this->redirectWithFlash('/app/estimates/new', 'error', 'The AI estimate generator lands in a later phase of this conversion.');
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

        return view('app.estimates.form', [
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'materials' => $materials,
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

        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $qtys = $request->input('item_qty', []);
        $costs = $request->input('item_cost', []);

        $total = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1);
            $cost = (float) ($costs[$i] ?? 0);
            $lineTotal = $qty * $cost;
            $total += $lineTotal;
            $items[] = ['description' => $desc, 'description_ar' => trim((string) ($descriptionsAr[$i] ?? '')), 'qty' => $qty, 'unit_cost' => $cost, 'total' => $lineTotal];
        }

        $estimate = Estimate::create([
            'company_id' => $companyId,
            'project_id' => $request->input('project_id') ?: null,
            'client_id' => $request->input('client_id') ?: null,
            'title' => $title,
            'title_ar' => trim((string) $request->input('title_ar', '')),
            'status' => 'draft',
            'total' => $total,
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        foreach ($items as $item) {
            EstimateItem::create(['estimate_id' => $estimate->id, ...$item]);
        }

        $this->flash('success', 'Estimate created.');
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function show(int $id): View
    {
        $estimate = $this->findOwned($id);
        $items = EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get()->toArray();
        $client = $estimate->client_id ? Client::find($estimate->client_id) : null;
        $project = $estimate->project_id ? Project::find($estimate->project_id) : null;

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

    public function pdf(int $id): RedirectResponse
    {
        $estimate = $this->findOwned($id);
        return $this->redirectWithFlash('/app/estimates/' . $estimate->id, 'error', 'PDF export lands in a later phase of this conversion.');
    }

    private function findOwned(int $id): Estimate
    {
        $estimate = Estimate::find($id);
        abort_if(!$estimate || $estimate->company_id !== Auth::user()->company_id, 404, 'Estimate not found.');
        return $estimate;
    }
}
