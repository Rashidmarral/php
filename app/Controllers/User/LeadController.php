<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Client;
use App\Models\Lead;

class LeadController extends Controller
{
    private const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];
    private const SOURCES = ['website', 'quick_estimate', 'referral', 'phone', 'walk_in', 'social_media', 'other'];

    public function index(): void
    {
        $companyId = Auth::companyId();
        $status = (string) $this->input('status', '');
        $sql = 'SELECT * FROM leads WHERE company_id = ?';
        $params = [$companyId];
        if (in_array($status, self::STATUSES, true)) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC';
        $leads = Lead::query($sql, $params)->fetchAll();

        $this->view('user/leads/index', [
            'pageTitle' => 'Leads',
            'leads' => $leads,
            'statusFilter' => $status,
            'statuses' => self::STATUSES,
            'counts' => $this->statusCounts($companyId),
        ], 'layouts/app');
    }

    public function create(): void
    {
        $this->view('user/leads/form', ['pageTitle' => 'New Lead', 'lead' => null, 'statuses' => self::STATUSES, 'sources' => self::SOURCES], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');

        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Lead name is required.');
            self::redirect('/app/leads/create');
        }

        $id = Lead::create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'company_name' => trim((string) $this->input('company_name', '')),
            'email' => trim((string) $this->input('email', '')),
            'phone' => trim((string) $this->input('phone', '')),
            'source' => in_array($this->input('source'), self::SOURCES, true) ? $this->input('source') : 'other',
            'status' => 'new',
            'estimated_value' => (float) $this->input('estimated_value', 0),
            'notes' => (string) $this->input('notes', ''),
        ]);

        $this->flash('success', 'Lead added.');
        self::redirect('/app/leads/' . $id . '/edit');
    }

    public function edit(string $id): void
    {
        $lead = $this->findOwned((int) $id);
        $this->view('user/leads/form', ['pageTitle' => 'Edit Lead', 'lead' => $lead, 'statuses' => self::STATUSES, 'sources' => self::SOURCES], 'layouts/app');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $lead = $this->findOwned((int) $id);

        Lead::update($lead['id'], [
            'name' => trim((string) $this->input('name')),
            'company_name' => trim((string) $this->input('company_name', '')),
            'email' => trim((string) $this->input('email', '')),
            'phone' => trim((string) $this->input('phone', '')),
            'source' => in_array($this->input('source'), self::SOURCES, true) ? $this->input('source') : 'other',
            'status' => in_array($this->input('status'), self::STATUSES, true) ? $this->input('status') : $lead['status'],
            'estimated_value' => (float) $this->input('estimated_value', 0),
            'notes' => (string) $this->input('notes', ''),
        ]);

        $this->flash('success', 'Lead updated.');
        self::redirect('/app/leads/' . $lead['id'] . '/edit');
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $lead = $this->findOwned((int) $id);
        $status = (string) $this->input('status');
        if (in_array($status, self::STATUSES, true)) {
            Lead::update($lead['id'], ['status' => $status]);
            $this->flash('success', 'Lead status updated.');
        }
        self::redirect('/app/leads');
    }

    public function convertToClient(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $lead = $this->findOwned((int) $id);

        if (!empty($lead['converted_client_id'])) {
            self::redirect('/app/clients/' . $lead['converted_client_id'] . '/edit');
        }

        $clientId = Client::create([
            'company_id' => Auth::companyId(),
            'name' => $lead['company_name'] ?: $lead['name'],
            'email' => $lead['email'],
            'phone' => $lead['phone'],
            'address' => '',
        ]);

        Lead::update($lead['id'], ['status' => 'won', 'converted_client_id' => $clientId]);
        $this->flash('success', 'Lead converted to a client.');
        self::redirect('/app/clients/' . $clientId . '/edit');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $lead = $this->findOwned((int) $id);
        Lead::delete($lead['id']);
        $this->flash('success', 'Lead deleted.');
        self::redirect('/app/leads');
    }

    private function statusCounts(int $companyId): array
    {
        $rows = Lead::query('SELECT status, COUNT(*) AS c FROM leads WHERE company_id = ? GROUP BY status', [$companyId])->fetchAll();
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    private function findOwned(int $id): array
    {
        $lead = Lead::find($id);
        if (!$lead || (int) $lead['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Lead not found.');
        }
        return $lead;
    }
}
