<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Support\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        $payments = DB::table('payments as pay')
            ->join('companies as c', 'c.id', '=', 'pay.company_id')
            ->select('pay.*', 'c.name as company_name')
            ->orderByDesc('pay.created_at')
            ->get()
            ->map(fn ($r) => (array) $r);

        $total = $payments->where('status', 'paid')->sum(fn ($p) => (float) $p['amount']);
        $pendingCount = $payments->where('status', 'pending')->count();

        return view('admin.payments.index', [
            'payments' => $payments,
            'total' => $total,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function show(int $id): View
    {
        $payment = DB::table('payments as pay')
            ->join('companies as c', 'c.id', '=', 'pay.company_id')
            ->where('pay.id', $id)
            ->select('pay.*', 'c.name as company_name')
            ->first();
        abort_if(!$payment, 404, 'Payment not found.');

        return view('admin.payments.show', [
            'payment' => (array) $payment,
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    /** Corrects a transaction's own details (amount, reference, method, status) without touching the company's plan. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $payment = Payment::findOrFail($id);

        $status = (string) $request->input('status', $payment->status);
        if (!in_array($status, ['paid', 'pending', 'failed', 'refunded'], true)) {
            $status = $payment->status;
        }

        $payment->update([
            'amount' => (float) $request->input('amount', $payment->amount),
            'currency' => trim((string) $request->input('currency', $payment->currency)) ?: 'SAR',
            'method' => trim((string) $request->input('method', $payment->method)),
            'reference' => trim((string) $request->input('reference', '')),
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return $this->redirectWithFlash('/admin/payments/' . $payment->id, 'success', 'Transaction updated.');
    }

    /** Reassigns which plan/cycle this transaction grants, and pushes that plan live on the company now. */
    public function applyPlan(Request $request, int $id): RedirectResponse
    {
        $payment = Payment::findOrFail($id);
        $plan = Plan::find((int) $request->input('plan_id'));
        if (!$plan) {
            return $this->redirectWithFlash('/admin/payments/' . $payment->id, 'error', 'Invalid plan.');
        }
        $cycle = $request->input('billing_cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        Billing::activatePlan($payment->company_id, $plan, $cycle);

        $payment->update([
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'status' => 'paid',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return $this->redirectWithFlash('/admin/payments/' . $payment->id, 'success', "Transaction reassigned to {$plan->name} ({$cycle}) and applied to the company.");
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $payment = Payment::find($id);
        abort_if(!$payment || $payment->status !== 'pending', 404, 'Payment not found or already processed.');

        $plan = $payment->plan_id ? Plan::find($payment->plan_id) : null;
        if ($plan) {
            Billing::activatePlan($payment->company_id, $plan, $payment->billing_cycle ?: 'monthly');
        }

        $payment->update([
            'status' => 'paid',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditLog::record($request->user(), 'payment_approve', 'payment', $payment->id, "{$payment->reference} — " . number_format((float) $payment->amount, 2) . ' SAR');

        return $this->redirectWithFlash('/admin/payments', 'success', 'Payment approved' . ($plan ? " and the company's plan has been activated." : '.'));
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $payment = Payment::find($id);
        abort_if(!$payment || $payment->status !== 'pending', 404, 'Payment not found or already processed.');

        $payment->update([
            'status' => 'failed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditLog::record($request->user(), 'payment_reject', 'payment', $payment->id, "{$payment->reference} — " . number_format((float) $payment->amount, 2) . ' SAR');

        return $this->redirectWithFlash('/admin/payments', 'success', 'Payment rejected.');
    }

    public function exportCsv(): Response
    {
        $payments = DB::table('payments as pay')
            ->join('companies as c', 'c.id', '=', 'pay.company_id')
            ->select('pay.*', 'c.name as company_name')
            ->orderByDesc('pay.created_at')
            ->get();

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['ID', 'Company', 'Amount', 'Currency', 'Method', 'Reference', 'Status', 'Created At']);
        foreach ($payments as $p) {
            fputcsv($csv, [$p->id, $p->company_name, $p->amount, $p->currency, $p->method, $p->reference, $p->status, $p->created_at]);
        }
        rewind($csv);
        $body = stream_get_contents($csv);
        fclose($csv);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="payments-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
