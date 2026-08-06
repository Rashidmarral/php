<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Controllers\User\BillingController;
use App\Models\Payment;
use App\Models\Plan;

class PaymentController extends Controller
{
    public function index(): void
    {
        $payments = Payment::query(
            'SELECT pay.*, c.name AS company_name FROM payments pay JOIN companies c ON c.id = pay.company_id ORDER BY pay.created_at DESC'
        )->fetchAll();
        $total = array_sum(array_map(fn($p) => (float) $p['amount'], array_filter($payments, fn($p) => $p['status'] === 'paid')));
        $pendingCount = count(array_filter($payments, fn($p) => $p['status'] === 'pending'));

        $this->view('admin/payments/index', [
            'pageTitle' => 'Payments',
            'payments' => $payments,
            'total' => $total,
            'pendingCount' => $pendingCount,
        ], 'layouts/admin');
    }

    public function show(string $id): void
    {
        $payment = Payment::query(
            'SELECT pay.*, c.name AS company_name FROM payments pay JOIN companies c ON c.id = pay.company_id WHERE pay.id = ?',
            [(int) $id]
        )->fetch();
        if (!$payment) {
            http_response_code(404);
            die('Payment not found.');
        }
        $plans = Plan::all('sort_order ASC');

        $this->view('admin/payments/show', [
            'pageTitle' => 'Transaction #' . $payment['id'],
            'payment' => $payment,
            'plans' => $plans,
        ], 'layouts/admin');
    }

    /** Corrects a transaction's own details (amount, reference, method, status) without touching the company's plan. */
    public function update(string $id): void
    {
        $this->verifyCsrf();
        $payment = Payment::find((int) $id);
        if (!$payment) {
            http_response_code(404);
            die('Payment not found.');
        }

        $status = (string) $this->input('status', $payment['status']);
        if (!in_array($status, ['paid', 'pending', 'failed', 'refunded'], true)) {
            $status = $payment['status'];
        }

        Payment::update($payment['id'], [
            'amount' => (float) $this->input('amount', $payment['amount']),
            'currency' => trim((string) $this->input('currency', $payment['currency'])) ?: 'SAR',
            'method' => trim((string) $this->input('method', $payment['method'])),
            'reference' => trim((string) $this->input('reference', '')),
            'status' => $status,
            'reviewed_by' => Auth::user()['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash('success', 'Transaction updated.');
        self::redirect('/admin/payments/' . $payment['id']);
    }

    /** Reassigns which plan/cycle this transaction grants, and pushes that plan live on the company now. */
    public function applyPlan(string $id): void
    {
        $this->verifyCsrf();
        $payment = Payment::find((int) $id);
        if (!$payment) {
            http_response_code(404);
            die('Payment not found.');
        }
        $plan = Plan::find((int) $this->input('plan_id'));
        if (!$plan) {
            $this->flash('error', 'Invalid plan.');
            self::redirect('/admin/payments/' . $payment['id']);
        }
        $cycle = $this->input('billing_cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        BillingController::activatePlan((int) $payment['company_id'], $plan, $cycle);

        Payment::update($payment['id'], [
            'plan_id' => $plan['id'],
            'billing_cycle' => $cycle,
            'status' => 'paid',
            'reviewed_by' => Auth::user()['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash('success', "Transaction reassigned to {$plan['name']} ({$cycle}) and applied to the company.");
        self::redirect('/admin/payments/' . $payment['id']);
    }

    public function approve(string $id): void
    {
        $this->verifyCsrf();
        $payment = Payment::find((int) $id);
        if (!$payment || $payment['status'] !== 'pending') {
            http_response_code(404);
            die('Payment not found or already processed.');
        }

        $plan = $payment['plan_id'] ? Plan::find((int) $payment['plan_id']) : null;
        if ($plan) {
            BillingController::activatePlan((int) $payment['company_id'], $plan, $payment['billing_cycle'] ?: 'monthly');
        }

        Payment::update($payment['id'], [
            'status' => 'paid',
            'reviewed_by' => Auth::user()['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash('success', 'Payment approved' . ($plan ? " and the company's plan has been activated." : '.'));
        self::redirect('/admin/payments');
    }

    public function reject(string $id): void
    {
        $this->verifyCsrf();
        $payment = Payment::find((int) $id);
        if (!$payment || $payment['status'] !== 'pending') {
            http_response_code(404);
            die('Payment not found or already processed.');
        }

        Payment::update($payment['id'], [
            'status' => 'failed',
            'reviewed_by' => Auth::user()['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash('success', 'Payment rejected.');
        self::redirect('/admin/payments');
    }
}
