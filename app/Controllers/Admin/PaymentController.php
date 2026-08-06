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
