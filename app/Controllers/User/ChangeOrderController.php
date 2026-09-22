<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Models\ChangeOrder;
use App\Models\Project;

class ChangeOrderController extends Controller
{
    public function __construct()
    {
        Feature::requireOrRedirect('change_orders');
    }

    public function store(string $projectId): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $project = $this->findOwnedProject((int) $projectId);

        $title = trim((string) $this->input('title'));
        $amount = (float) $this->input('amount', 0);
        if ($title === '' || $amount == 0.0) {
            $this->flash('error', 'A title and a non-zero amount are required (use a negative amount for a scope reduction).');
            self::redirect('/app/projects/' . $project['id']);
        }

        ChangeOrder::create([
            'company_id' => Auth::companyId(),
            'project_id' => $project['id'],
            'title' => $title,
            'title_ar' => trim((string) $this->input('title_ar', '')),
            'description' => $this->input('description', ''),
            'description_ar' => $this->input('description_ar', ''),
            'amount' => $amount,
            'status' => 'pending',
        ]);

        $this->flash('success', 'Change order added.');
        self::redirect('/app/projects/' . $project['id']);
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $changeOrder = $this->findOwned((int) $id);
        $status = (string) $this->input('status');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            self::redirect('/app/projects/' . $changeOrder['project_id']);
        }

        ChangeOrder::update($changeOrder['id'], [
            'status' => $status,
            'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
        ]);

        $this->flash('success', 'Change order ' . $status . '.');
        self::redirect('/app/projects/' . $changeOrder['project_id']);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $changeOrder = $this->findOwned((int) $id);
        ChangeOrder::delete($changeOrder['id']);
        $this->flash('success', 'Change order removed.');
        self::redirect('/app/projects/' . $changeOrder['project_id']);
    }

    private function findOwned(int $id): array
    {
        $changeOrder = ChangeOrder::find($id);
        if (!$changeOrder || (int) $changeOrder['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Change order not found.');
        }
        return $changeOrder;
    }

    private function findOwnedProject(int $id): array
    {
        $project = Project::find($id);
        if (!$project || (int) $project['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Project not found.');
        }
        return $project;
    }
}
