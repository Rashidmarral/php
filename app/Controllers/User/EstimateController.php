<?php

namespace App\Controllers\User;

use App\Core\AiEstimateGenerator;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Env;
use App\Core\Lang;
use App\Core\WhatsApp;
use App\Models\BuildingType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\EstimateTemplate;
use App\Models\EstimateTemplateItem;
use App\Models\Material;
use App\Models\Project;

class EstimateController extends Controller
{
    public function index(): void
    {
        $estimates = Estimate::query(
            'SELECT e.*, c.name AS client_name FROM estimates e LEFT JOIN clients c ON c.id = e.client_id WHERE e.company_id = ? ORDER BY e.created_at DESC',
            [Auth::companyId()]
        )->fetchAll();
        $this->view('user/estimates/index', ['pageTitle' => 'Estimates', 'estimates' => $estimates], 'layouts/app');
    }

    /** Landing screen: blank estimate / default template / template gallery / AI generator. */
    public function newChoice(): void
    {
        $templates = EstimateTemplate::active();
        $defaultTemplate = null;
        foreach ($templates as $t) {
            if ($t['is_default_choice']) {
                $defaultTemplate = $t;
                break;
            }
        }
        $this->view('user/estimates/new', [
            'pageTitle' => 'Create an Estimate',
            'templates' => $templates,
            'defaultTemplate' => $defaultTemplate,
        ], 'layouts/app');
    }

    public function templatePreview(string $id): void
    {
        $template = EstimateTemplate::find((int) $id);
        if (!$template || !$template['is_active']) {
            http_response_code(404);
            die('Template not found.');
        }
        $items = EstimateTemplateItem::forTemplate($template['id']);
        $companyId = Auth::companyId();

        $this->view('user/estimates/template-preview', [
            'pageTitle' => $template['name_en'],
            'template' => $template,
            'items' => $items,
            'subtotal' => array_sum(array_map(fn($i) => (float) $i['default_qty'] * (float) $i['unit_cost'], $items)),
            'clients' => Client::where('company_id', $companyId, 'name ASC'),
            'buildingTypes' => BuildingType::where('company_id', $companyId, 'sort_order ASC, id ASC'),
        ], 'layouts/app');
    }

    public function storeFromTemplate(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $template = EstimateTemplate::find((int) $id);
        if (!$template || !$template['is_active']) {
            http_response_code(404);
            die('Template not found.');
        }
        $companyId = Auth::companyId();
        $templateItems = EstimateTemplateItem::forTemplate($template['id']);
        $includeQuantities = (bool) $this->input('include_quantities');

        $total = 0;
        $rows = [];
        foreach ($templateItems as $ti) {
            $qty = $includeQuantities ? (float) $ti['default_qty'] : 0;
            $lineTotal = $qty * (float) $ti['unit_cost'];
            $total += $lineTotal;
            $rows[] = [
                'description' => $ti['description_en'],
                'section_title' => $ti['section_number'] . ' ' . $ti['section_title_en'],
                'item_type' => $ti['item_type'],
                'qty' => $qty,
                'uom' => $ti['uom'],
                'unit_cost' => $ti['unit_cost'],
                'total' => $lineTotal,
            ];
        }

        $estimateId = Estimate::create([
            'company_id' => $companyId,
            'project_id' => null,
            'client_id' => $this->input('client_id') ?: null,
            'title' => trim((string) $this->input('title')) ?: $template['name_en'],
            'status' => 'draft',
            'total' => $total,
            'share_token' => bin2hex(random_bytes(20)),
            'building_type' => trim((string) $this->input('building_type', '')),
            'job_address' => trim((string) $this->input('job_address', '')),
            'template_id' => $template['id'],
            'source' => 'template',
        ]);

        foreach ($rows as $row) {
            EstimateItem::create(['estimate_id' => $estimateId, ...$row]);
        }

        $this->flash('success', 'Estimate created from "' . $template['name_en'] . '".');
        self::redirect('/app/estimates/' . $estimateId);
    }

    public function aiGenerator(): void
    {
        $this->view('user/estimates/ai', [
            'pageTitle' => 'AI Estimate Generator',
            'aiConfigured' => AiEstimateGenerator::isConfigured(),
        ], 'layouts/app');
    }

    public function aiGenerate(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');

        $description = trim((string) $this->input('description'));
        if ($description === '') {
            $this->flash('error', 'Describe the project first.');
            self::redirect('/app/estimates/ai');
        }

        $result = AiEstimateGenerator::generate($description);
        $companyId = Auth::companyId();

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

        $estimateId = Estimate::create([
            'company_id' => $companyId,
            'project_id' => null,
            'client_id' => null,
            'title' => $result['title'],
            'status' => 'draft',
            'total' => $total,
            'share_token' => bin2hex(random_bytes(20)),
            'source' => 'ai',
        ]);

        foreach ($rows as $row) {
            EstimateItem::create(['estimate_id' => $estimateId, ...$row]);
        }

        if (!empty($result['note'])) {
            $this->flash('success', $result['note']);
        } else {
            $this->flash('success', 'AI-generated estimate created — review and adjust as needed.');
        }
        self::redirect('/app/estimates/' . $estimateId);
    }

    public function create(): void
    {
        $companyId = Auth::companyId();
        $clients = Client::where('company_id', $companyId, 'name ASC');
        $projects = Project::where('company_id', $companyId, 'name ASC');
        $materials = Material::query(
            'SELECT m.*, s.name AS supplier_name FROM materials m LEFT JOIN suppliers s ON s.id = m.supplier_id WHERE m.company_id = ? ORDER BY m.category ASC, m.name ASC',
            [$companyId]
        )->fetchAll();
        $this->view('user/estimates/form', ['pageTitle' => 'New Estimate', 'clients' => $clients, 'projects' => $projects, 'materials' => $materials], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $companyId = Auth::companyId();
        $title = trim((string) $this->input('title'));

        if ($title === '') {
            $this->flash('error', 'Estimate title is required.');
            self::redirect('/app/estimates/create');
        }

        $descriptions = $_POST['item_description'] ?? [];
        $qtys = $_POST['item_qty'] ?? [];
        $costs = $_POST['item_cost'] ?? [];

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
            $items[] = ['description' => $desc, 'qty' => $qty, 'unit_cost' => $cost, 'total' => $lineTotal];
        }

        $estimateId = Estimate::create([
            'company_id' => $companyId,
            'project_id' => $this->input('project_id') ?: null,
            'client_id' => $this->input('client_id') ?: null,
            'title' => $title,
            'status' => 'draft',
            'total' => $total,
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        foreach ($items as $item) {
            EstimateItem::create(['estimate_id' => $estimateId, ...$item]);
        }

        $this->flash('success', 'Estimate created.');
        self::redirect('/app/estimates/' . $estimateId);
    }

    public function show(string $id): void
    {
        $estimate = $this->findOwned((int) $id);
        $items = EstimateItem::where('estimate_id', $estimate['id'], 'id ASC');
        $client = $estimate['client_id'] ? Client::find((int) $estimate['client_id']) : null;
        $project = $estimate['project_id'] ? Project::find((int) $estimate['project_id']) : null;

        if (empty($estimate['share_token'])) {
            Estimate::update($estimate['id'], ['share_token' => bin2hex(random_bytes(20))]);
            $estimate['share_token'] = Estimate::find($estimate['id'])['share_token'];
        }
        $shareUrl = rtrim(Env::get('APP_URL', ''), '/') . '/e/' . $estimate['share_token'];

        $whatsappLink = null;
        if ($client && !empty($client['phone'])) {
            $company = Company::find((int) $estimate['company_id']);
            $message = "Hi {$client['name']}, here's your estimate \"{$estimate['title']}\" from {$company['name']} — please review and sign: {$shareUrl}";
            $whatsappLink = WhatsApp::shareLink($client['phone'], $message);
        }

        $this->view('user/estimates/show', [
            'pageTitle' => $estimate['title'],
            'estimate' => $estimate,
            'items' => $items,
            'client' => $client,
            'project' => $project,
            'whatsappLink' => $whatsappLink,
            'shareUrl' => $shareUrl,
        ], 'layouts/app');
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $estimate = $this->findOwned((int) $id);
        $status = (string) $this->input('status', 'draft');
        if (in_array($status, ['draft', 'sent', 'accepted', 'declined'], true)) {
            Estimate::update($estimate['id'], ['status' => $status]);
            $this->flash('success', 'Estimate status updated.');
        }
        self::redirect('/app/estimates/' . $estimate['id']);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $estimate = $this->findOwned((int) $id);
        Estimate::query('DELETE FROM estimate_items WHERE estimate_id = ?', [$estimate['id']]);
        Estimate::delete($estimate['id']);
        $this->flash('success', 'Estimate deleted.');
        self::redirect('/app/estimates');
    }

    public function pdf(string $id): void
    {
        $estimate = $this->findOwned((int) $id);
        $items = EstimateItem::where('estimate_id', $estimate['id'], 'id ASC');
        $client = $estimate['client_id'] ? Client::find((int) $estimate['client_id']) : null;
        $company = Company::find((int) $estimate['company_id']);
        $template = in_array($this->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $this->input('template') : 'modern';
        $lang = $this->input('lang') === 'ar' ? 'ar' : Lang::locale();

        $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'تسعيرة' : 'Estimate',
            'docNumber' => (string) $estimate['id'],
            'docDate' => $estimate['created_at'],
            'status' => ucfirst($estimate['status']),
            'issuer' => ['name' => $company['name'] ?? '', 'meta' => array_filter([$company['phone'] ?? null, $company['vat_number'] ?? null ? 'VAT: ' . $company['vat_number'] : null, ($company['cr_number'] ?? null) ? 'CR: ' . $company['cr_number'] : null])],
            'companyNameAr' => $company['name_ar'] ?? '',
            'companyLogo' => !empty($company['logo_path']) ? ('file://' . BASE_PATH . '/public' . $company['logo_path']) : null,
            'billTo' => $client ? ['name' => $client['name'], 'meta' => array_filter([$client['email'] ?? null, $client['phone'] ?? null, $client['address'] ?? null])] : null,
            'items' => array_map(fn($i) => ['description' => $i['description'], 'qty' => $i['qty'], 'unit_price' => $i['unit_cost'], 'total' => $i['total']], $items),
            'subtotal' => (float) $estimate['total'],
            'discountPercent' => 0,
            'discountAmount' => 0,
            'total' => (float) $estimate['total'],
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة BuildXact Saudi' : 'Generated by BuildXact Saudi',
        ], 'Estimate-' . $estimate['id'] . '.pdf');
    }

    private function findOwned(int $id): array
    {
        $estimate = Estimate::find($id);
        if (!$estimate || (int) $estimate['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Estimate not found.');
        }
        return $estimate;
    }
}
