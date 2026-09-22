<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $statusCounts = DB::table('companies')->select('status', DB::raw('COUNT(*) AS c'))->groupBy('status')->get();
        $statusMap = ['trial' => 0, 'active' => 0, 'past_due' => 0, 'suspended' => 0, 'cancelled' => 0];
        foreach ($statusCounts as $row) {
            $statusMap[$row->status] = (int) $row->c;
        }
        $totalCompanies = array_sum($statusMap);

        $revenueThisMonth = (float) Payment::where('status', 'paid')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');
        $revenueLastMonth = (float) Payment::where('status', 'paid')
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->sum('amount');
        $revenueDelta = $revenueLastMonth > 0 ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100) : null;

        // Approximate MRR: active subscriptions' monthly-equivalent price (yearly / 12).
        $mrr = (float) DB::table('subscriptions as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->where('s.status', 'active')
            ->selectRaw("SUM(CASE WHEN s.billing_cycle = 'yearly' THEN p.price_yearly / 12 ELSE p.price_monthly END) as mrr")
            ->value('mrr') ?? 0;

        $pendingPayments = Payment::where('status', 'pending')->count();
        $expiringTrials = Company::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now()->addDays(3))
            ->count();
        $pendingConsultations = Consultation::where('status', 'requested')->count();

        $planCounts = DB::table('plans as p')
            ->leftJoin('companies as c', 'c.plan_id', '=', 'p.id')
            ->select('p.name', DB::raw('COUNT(c.id) AS company_count'))
            ->groupBy('p.id', 'p.name')->orderBy('p.sort_order')
            ->get();
        $maxPlanCount = max(1, $planCounts->max('company_count'));

        $zatcaActive = Company::where('zatca_status', 'onboarded')->count();

        $recentCompanies = Company::orderByDesc('created_at')->limit(6)->get(['id', 'name', 'status', 'created_at']);
        $recentPayments = DB::table('payments as pm')
            ->leftJoin('companies as c', 'c.id', '=', 'pm.company_id')
            ->orderByDesc('pm.created_at')
            ->limit(6)
            ->select('pm.*', 'c.name as company_name')
            ->get();

        return view('admin.dashboard', [
            'totalCompanies' => $totalCompanies,
            'statusMap' => $statusMap,
            'revenueThisMonth' => $revenueThisMonth,
            'revenueDelta' => $revenueDelta,
            'mrr' => $mrr,
            'pendingPayments' => $pendingPayments,
            'expiringTrials' => $expiringTrials,
            'pendingConsultations' => $pendingConsultations,
            'planCounts' => $planCounts,
            'maxPlanCount' => $maxPlanCount,
            'zatcaActive' => $zatcaActive,
            'recentCompanies' => $recentCompanies,
            'recentPayments' => $recentPayments,
        ]);
    }
}
