<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\Task;

class ProjectController extends Controller
{
    public function index(): void
    {
        $companyId = Auth::companyId();
        $projects = Project::query(
            'SELECT p.*, c.name AS client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.company_id = ? ORDER BY p.created_at DESC',
            [$companyId]
        )->fetchAll();

        $this->view('user/projects/index', [
            'pageTitle' => 'Projects',
            'projects' => $projects,
            'projectLimit' => Feature::projectLimit(),
            'withinProjectLimit' => Feature::withinProjectLimit(),
        ], 'layouts/app');
    }

    public function create(): void
    {
        if (!Feature::withinProjectLimit()) {
            $this->flash('error', "Your plan's project limit (" . Feature::projectLimit() . ") has been reached. Upgrade to create more.");
            self::redirect('/app/billing');
        }
        $companyId = Auth::companyId();
        $clients = Client::where('company_id', $companyId, 'name ASC');
        $this->view('user/projects/form', ['pageTitle' => 'New Project', 'clients' => $clients, 'project' => null], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $companyId = Auth::companyId();

        if (!Feature::withinProjectLimit()) {
            $this->flash('error', "Your plan's project limit has been reached. Upgrade to create more.");
            self::redirect('/app/billing');
        }

        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Project name is required.');
            self::redirect('/app/projects/create');
        }

        $id = Project::create([
            'company_id' => $companyId,
            'client_id' => $this->input('client_id') ?: null,
            'name' => $name,
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'description' => $this->input('description', ''),
            'description_ar' => $this->input('description_ar', ''),
            'status' => $this->input('status', 'planning'),
            'budget' => (float) $this->input('budget', 0),
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
        ]);

        $this->flash('success', 'Project created.');
        self::redirect('/app/projects/' . $id);
    }

    public function show(string $id): void
    {
        $project = $this->findOwned((int) $id);
        $client = $project['client_id'] ? Client::find((int) $project['client_id']) : null;
        $estimates = Estimate::where('project_id', $project['id']);
        $invoices = Invoice::where('project_id', $project['id']);
        $tasks = Task::query('SELECT * FROM schedule_tasks WHERE project_id = ? ORDER BY start_date ASC', [$project['id']])->fetchAll();
        $changeOrders = ChangeOrder::where('project_id', $project['id'], 'created_at DESC');
        $approvedTotal = array_sum(array_map(fn($co) => $co['status'] === 'approved' ? (float) $co['amount'] : 0, $changeOrders));
        $photos = ProjectPhoto::where('project_id', $project['id'], 'taken_on DESC, created_at DESC');

        $this->view('user/projects/show', [
            'pageTitle' => $project['name'],
            'project' => $project,
            'client' => $client,
            'estimates' => $estimates,
            'invoices' => $invoices,
            'tasks' => $tasks,
            'changeOrders' => $changeOrders,
            'approvedChangeOrdersTotal' => $approvedTotal,
            'photos' => $photos,
        ], 'layouts/app');
    }

    public function edit(string $id): void
    {
        $project = $this->findOwned((int) $id);
        $clients = Client::where('company_id', Auth::companyId(), 'name ASC');
        $this->view('user/projects/form', ['pageTitle' => 'Edit Project', 'clients' => $clients, 'project' => $project], 'layouts/app');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $project = $this->findOwned((int) $id);

        Project::update($project['id'], [
            'client_id' => $this->input('client_id') ?: null,
            'name' => trim((string) $this->input('name')),
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'description' => $this->input('description', ''),
            'description_ar' => $this->input('description_ar', ''),
            'status' => $this->input('status', 'planning'),
            'budget' => (float) $this->input('budget', 0),
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
        ]);

        $this->flash('success', 'Project updated.');
        self::redirect('/app/projects/' . $project['id']);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $project = $this->findOwned((int) $id);
        Project::delete($project['id']);
        $this->flash('success', 'Project deleted.');
        self::redirect('/app/projects');
    }

    private function findOwned(int $id): array
    {
        $project = Project::find($id);
        if (!$project || (int) $project['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Project not found.');
        }
        return $project;
    }
}
