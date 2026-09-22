<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChangeOrder;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\VendorBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

    /**
     * A profit erosion of at least this fraction of the expected profit (or a swing all the way to
     * a loss) trips the "profit is eroding" alert on a project's cost-variance row.
     */
    private const EROSION_ALERT_THRESHOLD = 0.15;

    /**
     * Estimated-vs-Actual cost dashboard, replacing the old budget-vs-paid-invoices "profit" report
     * (which conflated cash collected with cost, and had no category breakdown at all).
     *
     * "Estimated cost" per category = SUM(estimate_items.total) for that project's ACCEPTED
     * estimate(s), grouped by item_type. estimate_items.total is qty*unit_cost — a raw cost figure
     * with no markup/tax applied (markup_percent/markup_amount/tax_amount are applied once, at the
     * estimate level, to derive the estimate's own sell-price `total` — see EstimateController's
     * item-building code). So category totals here are true costs, not sell prices.
     *
     * "Contract value" = SUM(accepted estimates.total) [the post-markup/tax sell price] + approved
     * change orders for the project — the same "budget + approved change orders" composition
     * ProjectController::show()/budgetHealthByProject() already use to get a project's revised
     * budget, applied here to the contract's sell-price baseline instead.
     *
     * "Actual cost" per category = SUM(vendor_bills.amount) grouped by category, with no status
     * filter (unpaid bills are still a real incurred cost, not a maybe) — this matches the exact
     * convention ProjectController::show()/budgetHealthByProject() already use for a project's
     * actualCostTotal (Task #10's committed-cost tracking), so this report agrees with the project
     * page's own numbers instead of inventing a second definition of "actual cost".
     *
     * A project with no accepted estimate has no cost baseline to compare against, so it is flagged
     * 'no estimate baseline' rather than showing misleading zeros for estimated cost / profit.
     */
    public function costVariance(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('reports')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $categories = array_keys(VendorBill::CATEGORIES);

        $projects = Project::where('company_id', $companyId)->orderByDesc('created_at')->get();

        // Accepted estimates, one row per estimate — a project can have more than one (e.g. an
        // original estimate plus later change-order estimates), so both contract value and
        // estimated cost are summed across all of a project's accepted estimates below.
        $acceptedEstimates = Estimate::where('company_id', $companyId)
            ->where('status', 'accepted')
            ->whereNotNull('project_id')
            ->get(['id', 'project_id', 'total']);
        $acceptedEstimateIds = $acceptedEstimates->pluck('id');
        $contractValueByProject = $acceptedEstimates->groupBy('project_id')
            ->map(fn ($rows) => (float) $rows->sum('total'));

        $estimatedByProjectAndCategory = DB::table('estimate_items')
            ->whereIn('estimate_id', $acceptedEstimateIds)
            ->join('estimates', 'estimates.id', '=', 'estimate_items.estimate_id')
            ->select('estimates.project_id', 'estimate_items.item_type', DB::raw('SUM(estimate_items.total) as total'))
            ->groupBy('estimates.project_id', 'estimate_items.item_type')
            ->get()
            ->groupBy('project_id');

        $actualByProjectAndCategory = VendorBill::where('company_id', $companyId)
            ->select('project_id', 'category', DB::raw('SUM(amount) as total'))
            ->groupBy('project_id', 'category')
            ->get()
            ->groupBy('project_id');

        $approvedCoByProject = ChangeOrder::where('company_id', $companyId)
            ->where('status', 'approved')
            ->select('project_id', DB::raw('SUM(amount) as total'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $rows = [];
        $totals = ['contractValue' => 0.0, 'estimatedCost' => 0.0, 'actualCost' => 0.0, 'expectedProfit' => 0.0, 'actualProfit' => 0.0];
        $alertCount = 0;

        foreach ($projects as $p) {
            $hasBaseline = $acceptedEstimates->contains('project_id', $p->id);

            $estimatedByCategory = array_fill_keys($categories, 0.0);
            foreach ($estimatedByProjectAndCategory->get($p->id, collect()) as $r) {
                if (array_key_exists($r->item_type, $estimatedByCategory)) {
                    $estimatedByCategory[$r->item_type] = (float) $r->total;
                }
            }
            $actualByCategory = array_fill_keys($categories, 0.0);
            foreach ($actualByProjectAndCategory->get($p->id, collect()) as $r) {
                if (array_key_exists($r->category, $actualByCategory)) {
                    $actualByCategory[$r->category] = (float) $r->total;
                }
            }

            $estimatedCostTotal = array_sum($estimatedByCategory);
            $actualCostTotal = array_sum($actualByCategory);
            $contractValue = ($contractValueByProject[$p->id] ?? 0.0) + (float) ($approvedCoByProject[$p->id] ?? 0);

            $expectedProfit = null;
            $actualProfit = null;
            $erosion = null;
            $alert = false;
            if ($hasBaseline) {
                $expectedProfit = $contractValue - $estimatedCostTotal;
                $actualProfit = $contractValue - $actualCostTotal;
                $erosion = $expectedProfit - $actualProfit;
                // Flag when actual profit has already gone negative, or has eaten more than the
                // threshold share of the expected profit — mirrors the worked example: expected
                // profit reduced from a positive figure to a much smaller (or negative) one.
                $alert = $actualProfit < 0
                    || ($expectedProfit > 0 && $erosion > $expectedProfit * self::EROSION_ALERT_THRESHOLD);
                if ($alert) {
                    $alertCount++;
                }
                $totals['contractValue'] += $contractValue;
                $totals['estimatedCost'] += $estimatedCostTotal;
                $totals['actualCost'] += $actualCostTotal;
                $totals['expectedProfit'] += $expectedProfit;
                $totals['actualProfit'] += $actualProfit;
            }

            $rows[] = [
                'project' => $p->toArray(),
                'hasBaseline' => $hasBaseline,
                'estimatedByCategory' => $estimatedByCategory,
                'actualByCategory' => $actualByCategory,
                'estimatedCostTotal' => $estimatedCostTotal,
                'actualCostTotal' => $actualCostTotal,
                'contractValue' => $contractValue,
                'expectedProfit' => $expectedProfit,
                'actualProfit' => $actualProfit,
                'erosion' => $erosion,
                'alert' => $alert,
            ];
        }

        return view('app.reports.profit', [
            'rows' => $rows,
            'categories' => VendorBill::CATEGORIES,
            'totals' => $totals,
            'alertCount' => $alertCount,
            'erosionThresholdPercent' => (int) round(self::EROSION_ALERT_THRESHOLD * 100),
        ]);
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
            if (! isset($byMonth[$key])) {
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
