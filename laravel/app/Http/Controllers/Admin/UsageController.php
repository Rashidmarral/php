<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UsageController extends Controller
{
    public function index(): View
    {
        $rows = DB::table('companies as c')
            ->leftJoin('plans as p', 'p.id', '=', 'c.plan_id')
            ->select(
                'c.id', 'c.name', 'c.status', 'p.name as plan_name', 'p.max_users', 'p.max_projects',
                DB::raw('(SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS user_count'),
                DB::raw('(SELECT COUNT(*) FROM projects pr WHERE pr.company_id = c.id) AS project_count')
            )
            ->orderBy('c.name')
            ->get()
            ->map(fn ($r) => (array) $r);

        $usage = array_map(function ($r) {
            $userLimit = (int) ($r['max_users'] ?? 0);
            $projectLimit = (int) ($r['max_projects'] ?? 0);
            $r['user_pct'] = $userLimit > 0 && $userLimit < 999 ? min(999, round(($r['user_count'] / $userLimit) * 100)) : null;
            $r['project_pct'] = $projectLimit > 0 && $projectLimit < 999 ? min(999, round(($r['project_count'] / $projectLimit) * 100)) : null;
            return $r;
        }, $rows->all());

        // Companies at or near either limit surface first — that's who needs an upgrade conversation.
        usort($usage, fn ($a, $b) => max($b['user_pct'] ?? 0, $b['project_pct'] ?? 0) <=> max($a['user_pct'] ?? 0, $a['project_pct'] ?? 0));

        return view('admin.usage.index', ['usage' => $usage]);
    }
}
