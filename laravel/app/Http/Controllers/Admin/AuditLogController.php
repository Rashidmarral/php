<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $adminFilter = trim((string) $request->query('admin', ''));
        $actionFilter = trim((string) $request->query('action', ''));

        $logs = AuditLog::query()
            ->when($adminFilter !== '', fn ($q) => $q->where('admin_name', 'like', '%' . $adminFilter . '%'))
            ->when($actionFilter !== '', fn ($q) => $q->where('action', $actionFilter))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit-log.index', [
            'logs' => $logs,
            'actions' => $actions,
            'adminFilter' => $adminFilter,
            'actionFilter' => $actionFilter,
        ]);
    }
}
