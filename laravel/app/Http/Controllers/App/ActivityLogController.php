<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Company-level activity/audit log: who on this company's own team created,
 * updated, approved, or deleted what, and when. Strictly scoped to this
 * company's own rows (`where('company_id', Auth::user()->company_id)`) —
 * never another company's, same tenant-isolation requirement as every other
 * company_id-scoped feature in this app. Rows are written by
 * App\Models\AuditLog::recordForCompany() from the various App controllers
 * (EstimateController, InvoiceController, ProjectController, etc.) — see
 * that method's docblock.
 *
 * Restricted to owner/admin (the same roles the 'manage_team' Gate allows),
 * since this is an internal-controls/dispute-resolution tool rather than a
 * general team feature every role should see.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('activity_log')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;
        $actionFilter = trim((string) $request->query('action', ''));
        $userFilter = trim((string) $request->query('user', ''));

        $logs = AuditLog::query()
            ->where('company_id', $companyId)
            ->when($actionFilter !== '', fn ($q) => $q->where('action', $actionFilter))
            ->when($userFilter !== '', fn ($q) => $q->where('admin_name', 'like', '%' . $userFilter . '%'))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $actions = AuditLog::query()
            ->where('company_id', $companyId)
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('app.activity-log.index', [
            'logs' => $logs,
            'actions' => $actions,
            'actionFilter' => $actionFilter,
            'userFilter' => $userFilter,
        ]);
    }
}
