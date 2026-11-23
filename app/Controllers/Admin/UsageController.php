<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Company;

class UsageController extends Controller
{
    public function index(): void
    {
        $rows = Company::query(
            "SELECT c.id, c.name, c.status, p.name AS plan_name, p.max_users, p.max_projects,
                    (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS user_count,
                    (SELECT COUNT(*) FROM projects pr WHERE pr.company_id = c.id) AS project_count
             FROM companies c LEFT JOIN plans p ON p.id = c.plan_id
             ORDER BY c.name ASC"
        )->fetchAll();

        $usage = array_map(function ($r) {
            $userLimit = (int) ($r['max_users'] ?? 0);
            $projectLimit = (int) ($r['max_projects'] ?? 0);
            $r['user_pct'] = $userLimit > 0 && $userLimit < 999 ? min(999, round(($r['user_count'] / $userLimit) * 100)) : null;
            $r['project_pct'] = $projectLimit > 0 && $projectLimit < 999 ? min(999, round(($r['project_count'] / $projectLimit) * 100)) : null;
            return $r;
        }, $rows);

        // Companies at or near either limit surface first — that's who needs an upgrade conversation.
        usort($usage, fn($a, $b) => max($b['user_pct'] ?? 0, $b['project_pct'] ?? 0) <=> max($a['user_pct'] ?? 0, $a['project_pct'] ?? 0));

        $this->view('admin/usage/index', [
            'pageTitle' => 'Platform Usage',
            'usage' => $usage,
        ], 'layouts/admin');
    }
}
