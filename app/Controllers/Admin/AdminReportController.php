<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\QuickEstimate;

class AdminReportController extends Controller
{
    public function index(): void
    {
        $statusCounts = Company::query(
            "SELECT status, COUNT(*) AS c FROM companies GROUP BY status"
        )->fetchAll();
        $statusMap = ['trial' => 0, 'active' => 0, 'suspended' => 0, 'cancelled' => 0];
        foreach ($statusCounts as $row) {
            $statusMap[$row['status']] = (int) $row['c'];
        }
        $totalCompanies = array_sum($statusMap);

        $planCounts = Plan::query(
            'SELECT p.name, COUNT(c.id) AS company_count FROM plans p LEFT JOIN companies c ON c.plan_id = p.id GROUP BY p.id ORDER BY p.sort_order'
        )->fetchAll();

        $zatcaCounts = Company::query("SELECT zatca_status, COUNT(*) AS c FROM companies GROUP BY zatca_status")->fetchAll();
        $zatcaMap = ['not_started' => 0, 'csr_generated' => 0, 'compliance_csid' => 0, 'active' => 0, 'error' => 0];
        foreach ($zatcaCounts as $row) {
            $key = $row['zatca_status'] ?: 'not_started';
            $zatcaMap[$key] = ($zatcaMap[$key] ?? 0) + (int) $row['c'];
        }

        $leadCounts = QuickEstimate::query("SELECT status, COUNT(*) AS c FROM quick_estimates WHERE company_id IS NULL GROUP BY status")->fetchAll();
        $leadMap = ['new' => 0, 'contacted' => 0, 'converted' => 0, 'dismissed' => 0];
        foreach ($leadCounts as $row) {
            $leadMap[$row['status']] = (int) $row['c'];
        }

        $paymentCounts = Payment::query("SELECT status, COUNT(*) AS c, SUM(amount) AS total FROM payments GROUP BY status")->fetchAll();
        $paymentMap = ['paid' => ['c' => 0, 'total' => 0.0], 'pending' => ['c' => 0, 'total' => 0.0], 'failed' => ['c' => 0, 'total' => 0.0], 'refunded' => ['c' => 0, 'total' => 0.0]];
        foreach ($paymentCounts as $row) {
            $paymentMap[$row['status']] = ['c' => (int) $row['c'], 'total' => (float) $row['total']];
        }
        $totalTransactions = array_sum(array_column($paymentMap, 'c'));

        // Revenue collected per month, last 6 months.
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthly[date('Y-m', strtotime("-{$i} months"))] = 0.0;
        }
        $paidPayments = Payment::query("SELECT amount, created_at FROM payments WHERE status = 'paid'")->fetchAll();
        foreach ($paidPayments as $p) {
            $month = substr((string) $p['created_at'], 0, 7);
            if (isset($monthly[$month])) {
                $monthly[$month] += (float) $p['amount'];
            }
        }
        $maxMonthly = max(1, max($monthly));

        // New company signups per month, last 6 months.
        $signupsMonthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $signupsMonthly[date('Y-m', strtotime("-{$i} months"))] = 0;
        }
        $allCompanies = Company::query('SELECT created_at FROM companies')->fetchAll();
        foreach ($allCompanies as $c) {
            $month = substr((string) $c['created_at'], 0, 7);
            if (isset($signupsMonthly[$month])) {
                $signupsMonthly[$month]++;
            }
        }
        $maxSignups = max(1, max($signupsMonthly));

        $topCompanies = Company::query(
            "SELECT c.id, c.name, COALESCE(SUM(pay.amount), 0) AS total_paid
             FROM companies c LEFT JOIN payments pay ON pay.company_id = c.id AND pay.status = 'paid'
             GROUP BY c.id ORDER BY total_paid DESC LIMIT 5"
        )->fetchAll();

        $this->view('admin/reports/index', [
            'pageTitle' => 'Reports',
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
        ], 'layouts/admin');
    }
}
