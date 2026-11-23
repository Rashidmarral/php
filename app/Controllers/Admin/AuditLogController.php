<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(): void
    {
        $adminFilter = trim((string) $this->input('admin', ''));
        $actionFilter = trim((string) $this->input('action', ''));

        $sql = 'SELECT * FROM audit_logs WHERE 1=1';
        $params = [];
        if ($adminFilter !== '') {
            $sql .= ' AND admin_name LIKE ?';
            $params[] = '%' . $adminFilter . '%';
        }
        if ($actionFilter !== '') {
            $sql .= ' AND action = ?';
            $params[] = $actionFilter;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 300';

        $logs = AuditLog::query($sql, $params)->fetchAll();
        $actions = AuditLog::query('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC')->fetchAll(\PDO::FETCH_COLUMN);

        $this->view('admin/audit-log/index', [
            'pageTitle' => 'Audit Log',
            'logs' => $logs,
            'actions' => $actions,
            'adminFilter' => $adminFilter,
            'actionFilter' => $actionFilter,
        ], 'layouts/admin');
    }
}
