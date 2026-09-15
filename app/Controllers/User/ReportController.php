<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;

class ReportController extends Controller
{
    public function __construct()
    {
        Feature::requireOrRedirect('reports');
    }

    public function overview(): void
    {
        $companyId = Auth::companyId();

        $activeProjects = Project::count('company_id = ? AND status != ?', [$companyId, 'completed']);
        $totalBudget = Project::sum('budget', 'company_id = ?', [$companyId]);
        $totalRevenuePaid = Invoice::sum('total', "company_id = ? AND status = 'paid'", [$companyId]);
        $totalOutstanding = Invoice::sum('total', "company_id = ? AND status != 'paid'", [$companyId]);

        $estimates = Estimate::where('company_id', $companyId);
        $accepted = count(array_filter($estimates, fn($e) => $e['status'] === 'accepted'));
        $declined = count(array_filter($estimates, fn($e) => $e['status'] === 'declined'));
        $decided = $accepted + $declined;
        $winRate = $decided > 0 ? round($accepted / $decided * 100) : null;

        $paidInvoices = Invoice::query("SELECT total, created_at FROM invoices WHERE company_id = ? AND status = 'paid'", [$companyId])->fetchAll();
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-{$i} months"));
            $monthly[$key] = 0.0;
        }
        foreach ($paidInvoices as $inv) {
            $key = substr((string) $inv['created_at'], 0, 7);
            if (isset($monthly[$key])) {
                $monthly[$key] += (float) $inv['total'];
            }
        }
        $maxMonthly = max(array_merge($monthly, [1]));

        $this->view('user/reports/overview', [
            'pageTitle' => 'Performance Analytics',
            'activeProjects' => $activeProjects,
            'totalBudget' => $totalBudget,
            'totalRevenuePaid' => $totalRevenuePaid,
            'totalOutstanding' => $totalOutstanding,
            'winRate' => $winRate,
            'accepted' => $accepted,
            'declined' => $declined,
            'monthly' => $monthly,
            'maxMonthly' => $maxMonthly,
        ], 'layouts/app');
    }

    public function profit(): void
    {
        $companyId = Auth::companyId();
        $projects = Project::where('company_id', $companyId, 'created_at DESC');

        $rows = [];
        foreach ($projects as $p) {
            $invoiced = Invoice::sum('total', 'project_id = ?', [$p['id']]);
            $paid = Invoice::sum('total', "project_id = ? AND status = 'paid'", [$p['id']]);
            $budget = (float) $p['budget'];
            $profit = $paid - $budget;
            $margin = $paid > 0 ? round($profit / $paid * 100, 1) : null;
            $rows[] = [
                'project' => $p,
                'budget' => $budget,
                'invoiced' => $invoiced,
                'paid' => $paid,
                'profit' => $profit,
                'margin' => $margin,
            ];
        }

        $this->view('user/reports/profit', [
            'pageTitle' => 'Profit Tracker',
            'rows' => $rows,
        ], 'layouts/app');
    }

    public function tax(): void
    {
        $companyId = Auth::companyId();
        $invoices = Invoice::query(
            "SELECT invoice_number, total, vat_rate, vat_amount, status, created_at FROM invoices WHERE company_id = ? AND vat_amount > 0 ORDER BY created_at DESC",
            [$companyId]
        )->fetchAll();

        $byMonth = [];
        foreach ($invoices as $inv) {
            $key = substr((string) $inv['created_at'], 0, 7);
            if (!isset($byMonth[$key])) {
                $byMonth[$key] = ['subtotal' => 0.0, 'vat' => 0.0, 'total' => 0.0, 'count' => 0];
            }
            $byMonth[$key]['subtotal'] += (float) $inv['total'] - (float) $inv['vat_amount'];
            $byMonth[$key]['vat'] += (float) $inv['vat_amount'];
            $byMonth[$key]['total'] += (float) $inv['total'];
            $byMonth[$key]['count']++;
        }
        krsort($byMonth);

        $totalVat = array_sum(array_map(fn($i) => (float) $i['vat_amount'], $invoices));
        $totalTaxable = array_sum(array_map(fn($i) => (float) $i['total'] - (float) $i['vat_amount'], $invoices));

        $this->view('user/reports/tax', [
            'pageTitle' => 'Tax Summary',
            'byMonth' => $byMonth,
            'totalVat' => $totalVat,
            'totalTaxable' => $totalTaxable,
            'invoiceCount' => count($invoices),
        ], 'layouts/app');
    }

    public function retention(): void
    {
        $companyId = Auth::companyId();
        $rows = Invoice::query(
            "SELECT i.*, c.name AS client_name FROM invoices i LEFT JOIN clients c ON c.id = i.client_id
             WHERE i.company_id = ? AND i.retention_amount > 0 ORDER BY i.retention_released ASC, i.created_at DESC",
            [$companyId]
        )->fetchAll();

        $outstanding = array_sum(array_map(fn($r) => $r['retention_released'] ? 0 : (float) $r['retention_amount'], $rows));
        $released = array_sum(array_map(fn($r) => $r['retention_released'] ? (float) $r['retention_amount'] : 0, $rows));

        $this->view('user/reports/retention', [
            'pageTitle' => 'Retention Ledger',
            'rows' => $rows,
            'outstanding' => $outstanding,
            'released' => $released,
        ], 'layouts/app');
    }
}
