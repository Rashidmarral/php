<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    public function index(): View
    {
        $statusCounts = DB::table('companies')->select('status', DB::raw('COUNT(*) AS c'))->groupBy('status')->get();
        $statusMap = ['trial' => 0, 'active' => 0, 'suspended' => 0, 'cancelled' => 0];
        foreach ($statusCounts as $row) {
            $statusMap[$row->status] = (int) $row->c;
        }
        $totalCompanies = array_sum($statusMap);

        $planCounts = DB::table('plans as p')
            ->leftJoin('companies as c', 'c.plan_id', '=', 'p.id')
            ->select('p.name', DB::raw('COUNT(c.id) AS company_count'))
            ->groupBy('p.id', 'p.name')
            ->orderBy('p.sort_order')
            ->get()
            ->map(fn ($r) => (array) $r);

        $zatcaCounts = DB::table('companies')->select('zatca_status', DB::raw('COUNT(*) AS c'))->groupBy('zatca_status')->get();
        $zatcaMap = ['not_started' => 0, 'csr_generated' => 0, 'compliance_pending' => 0, 'compliance_verified' => 0, 'onboarded' => 0, 'error' => 0];
        foreach ($zatcaCounts as $row) {
            $key = $row->zatca_status ?: 'not_started';
            $zatcaMap[$key] = ($zatcaMap[$key] ?? 0) + (int) $row->c;
        }

        $leadCounts = DB::table('quick_estimates')->whereNull('company_id')->select('status', DB::raw('COUNT(*) AS c'))->groupBy('status')->get();
        $leadMap = ['new' => 0, 'contacted' => 0, 'converted' => 0, 'dismissed' => 0];
        foreach ($leadCounts as $row) {
            $leadMap[$row->status] = (int) $row->c;
        }

        $paymentCounts = DB::table('payments')->select('status', DB::raw('COUNT(*) AS c'), DB::raw('SUM(amount) AS total'))->groupBy('status')->get();
        $paymentMap = ['paid' => ['c' => 0, 'total' => 0.0], 'pending' => ['c' => 0, 'total' => 0.0], 'failed' => ['c' => 0, 'total' => 0.0], 'refunded' => ['c' => 0, 'total' => 0.0]];
        foreach ($paymentCounts as $row) {
            $paymentMap[$row->status] = ['c' => (int) $row->c, 'total' => (float) $row->total];
        }
        $totalTransactions = array_sum(array_column($paymentMap, 'c'));

        // Revenue collected per month, last 6 months.
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthly[now()->subMonths($i)->format('Y-m')] = 0.0;
        }
        $paidPayments = DB::table('payments')->where('status', 'paid')->select('amount', 'created_at')->get();
        foreach ($paidPayments as $p) {
            $month = substr((string) $p->created_at, 0, 7);
            if (isset($monthly[$month])) {
                $monthly[$month] += (float) $p->amount;
            }
        }
        $maxMonthly = max(1, max($monthly));

        // New company signups per month, last 6 months.
        $signupsMonthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $signupsMonthly[now()->subMonths($i)->format('Y-m')] = 0;
        }
        $allCompanies = DB::table('companies')->select('created_at')->get();
        foreach ($allCompanies as $c) {
            $month = substr((string) $c->created_at, 0, 7);
            if (isset($signupsMonthly[$month])) {
                $signupsMonthly[$month]++;
            }
        }
        $maxSignups = max(1, max($signupsMonthly));

        $topCompanies = DB::table('companies as c')
            ->leftJoin('payments as pay', function ($join) {
                $join->on('pay.company_id', '=', 'c.id')->where('pay.status', '=', 'paid');
            })
            ->select('c.id', 'c.name', DB::raw('COALESCE(SUM(pay.amount), 0) AS total_paid'))
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total_paid')
            ->limit(5)
            ->get()
            ->map(fn ($r) => (array) $r);

        return view('admin.reports.index', [
            'statusMap' => $statusMap,
            'totalCompanies' => $totalCompanies,
            'planCounts' => $planCounts,
            'zatcaMap' => $zatcaMap,
            'leadMap' => $leadMap,
            'paymentMap' => $paymentMap,
            'totalTransactions' => $totalTransactions,
            'monthly' => $monthly,
            'maxMonthly' => $maxMonthly,
            'signupsMonthly' => $signupsMonthly,
            'maxSignups' => $maxSignups,
            'topCompanies' => $topCompanies,
        ]);
    }
}
