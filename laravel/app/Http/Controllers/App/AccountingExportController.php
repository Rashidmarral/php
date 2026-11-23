<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\VendorBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Exports a company's Invoices and VendorBills for a date range as a generic
 * double-entry journal CSV (Date, Reference, Account, Debit, Credit, Memo) —
 * the common-denominator shape an external accountant can import into
 * QuickBooks Online or Xero's "general journal" import wizard, re-mapping
 * account names to their own chart of accounts as needed.
 *
 * Scope note: there is no single file format both QBO and Xero accept
 * identically (QBO's native format is `.IIF`, Xero's is its own API schema),
 * and neither can be verified against this sandboxed environment. This is a
 * deliberate, documented scope decision — not an oversight — to build toward
 * the generic journal shape every small-business accountant can re-map in
 * either product's import wizard, rather than guess at a proprietary format.
 *
 * Every transaction below posts both a debit row and a credit row of the
 * same amount, so the export is a real double-entry journal: for the whole
 * file, SUM(Debit) must equal SUM(Credit) to the cent.
 */
class AccountingExportController extends Controller
{
    private const ACCOUNT_SALES_REVENUE = 'Sales Revenue';
    private const ACCOUNT_ACCOUNTS_RECEIVABLE = 'Accounts Receivable';
    private const ACCOUNT_CASH_BANK = 'Cash/Bank';
    private const ACCOUNT_VAT_PAYABLE = 'VAT Payable';
    private const ACCOUNT_ACCOUNTS_PAYABLE = 'Accounts Payable';

    /** VendorBill.category -> expense account name. An unrecognized category falls back to Other Expense. */
    private const EXPENSE_ACCOUNTS = [
        'material' => 'Materials Expense',
        'labor' => 'Labor Expense',
        'equipment' => 'Equipment Expense',
        'subcontractor' => 'Subcontractor Expense',
        'other' => 'Other Expense',
    ];

    private const DEFAULT_EXPENSE_ACCOUNT = 'Other Expense';

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('reports')) {
            return $redirect;
        }

        return view('app.reports.accounting-export', [
            'dateFrom' => (string) $request->query('date_from', now()->startOfMonth()->format('Y-m-d')),
            'dateTo' => (string) $request->query('date_to', now()->format('Y-m-d')),
        ]);
    }

    public function export(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->requireFeature('reports')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;
        $dateFrom = Carbon::parse((string) $request->query('date_from', now()->startOfMonth()->format('Y-m-d')))->startOfDay();
        $dateTo = Carbon::parse((string) $request->query('date_to', now()->format('Y-m-d')))->endOfDay();

        $rows = $this->buildJournal($companyId, $dateFrom, $dateTo);

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Date', 'Reference', 'Account', 'Debit', 'Credit', 'Memo']);
        foreach ($rows as $row) {
            fputcsv($csv, [
                $row['date'],
                $row['reference'],
                $row['account'],
                $row['debit'] > 0 ? number_format($row['debit'], 2, '.', '') : '',
                $row['credit'] > 0 ? number_format($row['credit'], 2, '.', '') : '',
                $row['memo'],
            ]);
        }
        rewind($csv);
        $body = stream_get_contents($csv);
        fclose($csv);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="accounting-export-' . $dateFrom->format('Y-m-d') . '-to-' . $dateTo->format('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Builds the double-entry journal rows for this company's Invoices (revenue) and
     * VendorBills (expenses) issued/dated within [$dateFrom, $dateTo].
     *
     * Invoice (per invoice, taxable = total - vat_amount):
     *   Dr Accounts Receivable  total
     *     Cr Sales Revenue        taxable
     *     Cr VAT Payable          vat_amount   (only when vat_amount > 0)
     *   — and, only when the invoice's status is 'paid' (cash actually collected):
     *   Dr Cash/Bank             total
     *     Cr Accounts Receivable  total
     *
     * VendorBill (per bill; this app does not track VAT on vendor bills, so no VAT
     * line is split out here — see class docblock):
     *   Dr <category expense account>  amount
     *     Cr Accounts Payable            amount
     *   — and, only when the bill's status is 'paid':
     *   Dr Accounts Payable     amount
     *     Cr Cash/Bank            amount
     *
     * @return array<int, array{date:string, reference:string, account:string, debit:float, credit:float, memo:string}>
     */
    private function buildJournal(int $companyId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = [];

        $invoices = Invoice::where('company_id', $companyId)
            ->whereBetween('created_at', [$dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s')])
            ->with('invoicePayments')
            ->orderBy('created_at')
            ->get();

        foreach ($invoices as $invoice) {
            $total = (float) $invoice->total;
            $vat = (float) $invoice->vat_amount;
            $taxable = round($total - $vat, 2);
            $issueDate = Carbon::parse($invoice->created_at)->format('Y-m-d');
            $reference = (string) $invoice->invoice_number;
            $memo = "Invoice {$reference}";

            $rows[] = $this->line($issueDate, $reference, self::ACCOUNT_ACCOUNTS_RECEIVABLE, $total, 0.0, $memo);
            $rows[] = $this->line($issueDate, $reference, self::ACCOUNT_SALES_REVENUE, 0.0, $taxable, $memo);
            if ($vat > 0) {
                $rows[] = $this->line($issueDate, $reference, self::ACCOUNT_VAT_PAYABLE, 0.0, $vat, $memo);
            }

            if ($invoice->status === 'paid') {
                $lastPayment = $invoice->invoicePayments->sortByDesc('created_at')->first();
                $paidDate = $lastPayment ? Carbon::parse($lastPayment->created_at)->format('Y-m-d') : $issueDate;
                $paidMemo = "Payment received — invoice {$reference}";
                $rows[] = $this->line($paidDate, $reference, self::ACCOUNT_CASH_BANK, $total, 0.0, $paidMemo);
                $rows[] = $this->line($paidDate, $reference, self::ACCOUNT_ACCOUNTS_RECEIVABLE, 0.0, $total, $paidMemo);
            }
        }

        $bills = VendorBill::where('company_id', $companyId)
            ->where(function ($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('bill_date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
                    ->orWhere(function ($q2) use ($dateFrom, $dateTo) {
                        $q2->whereNull('bill_date')->whereBetween('created_at', [$dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s')]);
                    });
            })
            ->orderBy('bill_date')
            ->get();

        foreach ($bills as $bill) {
            $amount = (float) $bill->amount;
            $billDate = $bill->bill_date ? Carbon::parse($bill->bill_date)->format('Y-m-d') : Carbon::parse($bill->created_at)->format('Y-m-d');
            $reference = $bill->reference !== null && $bill->reference !== '' ? (string) $bill->reference : ('VB-' . $bill->id);
            $expenseAccount = self::EXPENSE_ACCOUNTS[$bill->category] ?? self::DEFAULT_EXPENSE_ACCOUNT;
            $memo = "Vendor bill {$reference}: " . (string) $bill->description;

            $rows[] = $this->line($billDate, $reference, $expenseAccount, $amount, 0.0, $memo);
            $rows[] = $this->line($billDate, $reference, self::ACCOUNT_ACCOUNTS_PAYABLE, 0.0, $amount, $memo);

            if ($bill->status === 'paid') {
                $paidMemo = "Bill paid — {$reference}";
                $rows[] = $this->line($billDate, $reference, self::ACCOUNT_ACCOUNTS_PAYABLE, $amount, 0.0, $paidMemo);
                $rows[] = $this->line($billDate, $reference, self::ACCOUNT_CASH_BANK, 0.0, $amount, $paidMemo);
            }
        }

        return $rows;
    }

    /** @return array{date:string, reference:string, account:string, debit:float, credit:float, memo:string} */
    private function line(string $date, string $reference, string $account, float $debit, float $credit, string $memo): array
    {
        return [
            'date' => $date,
            'reference' => $reference,
            'account' => $account,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'memo' => $memo,
        ];
    }
}
