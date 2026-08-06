<?php

namespace App\Controllers\Portal;

use App\Core\Controller;
use App\Core\PortalAuth;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Task;

class PortalController extends Controller
{
    public function showLogin(): void
    {
        if (PortalAuth::check()) {
            self::redirect('/portal');
        }
        $this->view('portal/login', ['pageTitle' => 'Client Portal'], 'layouts/auth');
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $email = trim((string) $this->input('email'));
        $password = (string) $this->input('password');

        if (PortalAuth::attempt($email, $password)) {
            self::redirect('/portal');
        }

        $this->flash('error', 'Invalid email or password, or portal access is not enabled for this account.');
        self::redirect('/portal/login');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        PortalAuth::logout();
        self::redirect('/portal/login');
    }

    public function dashboard(): void
    {
        $client = PortalAuth::client();
        $company = Company::find((int) $client['company_id']);
        $projects = Project::where('client_id', $client['id'], 'created_at DESC');
        $estimates = Estimate::where('client_id', $client['id'], 'created_at DESC');
        $invoices = Invoice::where('client_id', $client['id'], 'created_at DESC');

        $this->view('portal/dashboard', [
            'pageTitle' => 'My Projects',
            'client' => $client,
            'company' => $company,
            'projects' => $projects,
            'estimates' => $estimates,
            'invoices' => $invoices,
        ], 'layouts/portal');
    }

    public function project(string $id): void
    {
        $client = PortalAuth::client();
        $project = $this->findOwnedProject((int) $id, $client['id']);
        $tasks = Task::query('SELECT * FROM schedule_tasks WHERE project_id = ? ORDER BY start_date ASC', [$project['id']])->fetchAll();

        $this->view('portal/project', [
            'pageTitle' => $project['name'],
            'client' => $client,
            'project' => $project,
            'tasks' => $tasks,
        ], 'layouts/portal');
    }

    public function estimate(string $id): void
    {
        $client = PortalAuth::client();
        $estimate = Estimate::find((int) $id);
        if (!$estimate || (int) $estimate['client_id'] !== (int) $client['id']) {
            http_response_code(404);
            die('Not found.');
        }
        $items = EstimateItem::where('estimate_id', $estimate['id'], 'id ASC');

        $this->view('portal/estimate', [
            'pageTitle' => $estimate['title'],
            'client' => $client,
            'estimate' => $estimate,
            'items' => $items,
        ], 'layouts/portal');
    }

    public function invoice(string $id): void
    {
        $client = PortalAuth::client();
        $invoice = Invoice::find((int) $id);
        if (!$invoice || (int) $invoice['client_id'] !== (int) $client['id']) {
            http_response_code(404);
            die('Not found.');
        }
        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');

        $this->view('portal/invoice', [
            'pageTitle' => $invoice['invoice_number'],
            'client' => $client,
            'invoice' => $invoice,
            'items' => $items,
        ], 'layouts/portal');
    }

    private function findOwnedProject(int $id, int $clientId): array
    {
        $project = Project::find($id);
        if (!$project || (int) $project['client_id'] !== $clientId) {
            http_response_code(404);
            die('Not found.');
        }
        return $project;
    }
}
