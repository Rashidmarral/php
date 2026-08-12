<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ReportController extends Controller
{
    public function overview(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('reports')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $activeProjects = Project::where('company_id', $companyId)->where('status', '!=', 'completed')->count();
        $totalBudget = (float) Project::where('company_id', $companyId)->sum('budget');
        $totalRevenuePaid = (float) Invoice::where('company_id', $companyId)->where('status', 'paid')->sum('total');
        $totalOutstanding = (float) Invoice::where('company_id', $companyId)->where('status', '!=', 'paid')->sum('total');

        $estimates = Estimate::where('company_id', $companyId)->get();
        $accepted = $estimates->where('status', 'accepted')->count();
        $declined = $estimates->where('status', 'declined')->count();
        $decided = $accepted + $declined;
        $winRate = $decided > 0 ? round($accepted / $decided * 100) : null;

        $paidInvoices = Invoice::where('company_id', $companyId)->where('status', 'paid')->get(['total', 'created_at']);
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $monthly[$key] = 0.0;
        }
        foreach ($paidInvoices as $inv) {
            $key = substr((string) $inv->created_at, 0, 7);
            if (isset($monthly[$key])) {
                $monthly[$key] += (float) $inv->total;
            }
        }
        $maxMonthly = max(array_merge($monthly, [1]));

        return view('app.reports.overview', [
            'activeProjects' => $activeProjects,
            'totalBudget' => $totalBudget,
            'totalRevenuePaid' => $totalRevenuePaid,
            'totalOutstanding' => $totalOutstanding,
            'winRate' => $winRate,
            'accepted' => $accepted,
            'declined' => $declined,
            'monthly' => $monthly,
            'maxMonthly' => $maxMonthly,
        ]);
    }

    public function profit(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('reports')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $projects = Project::where('company_id', $companyId)->orderByDesc('created_at')->get();

        $rows = [];
        foreach ($projects as $p) {
            $invoiced = (float) Invoice::where('project_id', $p->id)->sum('total');
            $paid = (float) Invoice::where('project_id', $p->id)->where('status', 'paid')->sum('total');
            $budget = (float) $p->budget;
            $profit = $paid - $budget;
            $margin = $paid > 0 ? round($profit / $paid * 100, 1) : null;
            $rows[] = [
                'project' => $p->toArray(),
                'budget' => $budget,
                'invoiced' => $invoiced,
                'paid' => $paid,
                'profit' => $profit,
                'margin' => $margin,
            ];
        }

        return view('app.reports.profit', ['rows' => $rows]);
    }

    public function tax(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('reports')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $invoices = Invoice::where('company_id', $companyId)
            ->where('vat_amount', '>', 0)
            ->orderByDesc('created_at')
            ->get(['invoice_number', 'total', 'vat_rate', 'vat_amount', 'status', 'created_at']);

        $byMonth = [];
        foreach ($invoices as $inv) {
            $key = substr((string) $inv->created_at, 0, 7);
            if (!isset($byMonth[$key])) {
                $byMonth[$key] = ['subtotal' => 0.0, 'vat' => 0.0, 'total' => 0.0, 'count' => 0];
            }
            $byMonth[$key]['subtotal'] += (float) $inv->total - (float) $inv->vat_amount;
            $byMonth[$key]['vat'] += (float) $inv->vat_amount;
            $byMonth[$key]['total'] += (float) $inv->total;
            $byMonth[$key]['count']++;
        }
        krsort($byMonth);

        $totalVat = (float) $invoices->sum('vat_amount');
        $totalTaxable = (float) $invoices->sum(fn ($i) => (float) $i->total - (float) $i->vat_amount);

        return view('app.reports.tax', [
            'byMonth' => $byMonth,
            'totalVat' => $totalVat,
            'totalTaxable' => $totalTaxable,
            'invoiceCount' => $invoices->count(),
        ]);
    }

    public function retention(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('reports')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $rows = DB::table('invoices as i')
            ->leftJoin('clients as c', 'c.id', '=', 'i.client_id')
            ->where('i.company_id', $companyId)
            ->where('i.retention_amount', '>', 0)
            ->orderBy('i.retention_released')
            ->orderByDesc('i.created_at')
            ->select('i.*', 'c.name as client_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $outstanding = array_sum(array_map(fn ($r) => $r['retention_released'] ? 0 : (float) $r['retention_amount'], $rows));
        $released = array_sum(array_map(fn ($r) => $r['retention_released'] ? (float) $r['retention_amount'] : 0, $rows));

        return view('app.reports.retention', [
            'rows' => $rows,
            'outstanding' => $outstanding,
            'released' => $released,
        ]);
    }
}
