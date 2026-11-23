<?php

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Models\Consultation;

class ConsultationAdminController extends Controller
{
    public function index(): void
    {
        $status = (string) $this->input('status', '');
        $sql = 'SELECT co.*, c.name AS company_name, u.name AS requested_by_name
                FROM consultations co
                JOIN companies c ON c.id = co.company_id
                LEFT JOIN users u ON u.id = co.requested_by
                WHERE 1=1';
        $params = [];
        if (in_array($status, Consultation::STATUSES, true)) {
            $sql .= ' AND co.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY (co.status = \'requested\') DESC, co.created_at DESC';

        $consultations = Consultation::query($sql, $params)->fetchAll();

        $this->view('admin/consultations/index', [
            'pageTitle' => 'Expert Consultations',
            'consultations' => $consultations,
            'statusFilter' => $status,
        ], 'layouts/admin');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $consultation = Consultation::find((int) $id);
        if (!$consultation) {
            http_response_code(404);
            die('Consultation not found.');
        }

        $status = (string) $this->input('status', $consultation['status']);
        if (!in_array($status, Consultation::STATUSES, true)) {
            $status = $consultation['status'];
        }

        Consultation::update($consultation['id'], [
            'status' => $status,
            'assigned_engineer' => trim((string) $this->input('assigned_engineer', '')),
            'scheduled_at' => $this->input('scheduled_at') ?: null,
            'admin_notes' => trim((string) $this->input('admin_notes', '')),
        ]);
        Audit::log('consultation_update', 'consultation', $consultation['id'], "{$consultation['topic']} → {$status}");

        $this->flash('success', 'Consultation updated.');
        self::redirect('/admin/consultations');
    }
}
