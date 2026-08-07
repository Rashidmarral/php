<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Moyasar;
use App\Core\Settings;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;

class BillingController extends Controller
{
    public function index(): void
    {
        $companyId = Auth::companyId();
        $company = Company::find($companyId);
        $plans = Plan::query('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
        $currentPlan = $company['plan_id'] ? Plan::find((int) $company['plan_id']) : null;
        $subscription = Subscription::query('SELECT * FROM subscriptions WHERE company_id = ? ORDER BY created_at DESC LIMIT 1', [$companyId])->fetch();
        $payments = Payment::where('company_id', $companyId, 'created_at DESC');
        $pendingPayment = Payment::query("SELECT * FROM payments WHERE company_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1", [$companyId])->fetch();

        $this->view('user/billing/index', [
            'pageTitle' => 'Billing & Subscription',
            'company' => $company,
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'subscription' => $subscription,
            'payments' => $payments,
            'pendingPayment' => $pendingPayment,
        ], 'layouts/app');
    }

    public function checkout(): void
    {
        if (!Auth::isCompanyOwner()) {
            $this->flash('error', 'Only the company owner can change the subscription plan.');
            self::redirect('/app/billing');
        }

        $plan = Plan::first('slug', (string) $this->input('plan'));
        if (!$plan) {
            $this->flash('error', 'Invalid plan selected.');
            self::redirect('/app/billing');
        }
        $cycle = $this->input('cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $amount = (float) ($cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly']);

        $this->view('user/billing/checkout', [
            'pageTitle' => 'Checkout',
            'plan' => $plan,
            'cycle' => $cycle,
            'amount' => $amount,
            'bankTransferEnabled' => Settings::get('bank_transfer_enabled') === '1',
            'bank' => [
                'name' => Settings::get('bank_name', ''),
                'accountName' => Settings::get('bank_account_name', ''),
                'iban' => Settings::get('bank_iban', ''),
                'accountNumber' => Settings::get('bank_account_number', ''),
            ],
            'moyasarConfigured' => Moyasar::isConfigured(),
            'moyasarPublishableKey' => Moyasar::publishableKey(),
        ], 'layouts/app');
    }

    public function requestBankTransfer(): void
    {
        $this->verifyCsrf();
        if (!Auth::isCompanyOwner()) {
            self::redirect('/app/billing');
        }

        $companyId = Auth::companyId();
        $plan = Plan::first('slug', (string) $this->input('plan'));
        if (!$plan) {
            $this->flash('error', 'Invalid plan selected.');
            self::redirect('/app/billing');
        }
        $cycle = $this->input('cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $amount = (float) ($cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly']);

        Payment::create([
            'company_id' => $companyId,
            'subscription_id' => null,
            'plan_id' => $plan['id'],
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'bank_transfer',
            'reference' => 'BT-' . strtoupper(bin2hex(random_bytes(4))),
            'status' => 'pending',
        ]);

        $this->flash('success', 'Your bank transfer request has been submitted. Your plan will be activated once our team confirms receipt of payment.');
        self::redirect('/app/billing');
    }

    public function moyasarCallback(): void
    {
        $companyId = Auth::companyId();
        $paymentId = (string) $this->input('id', '');
        $planSlug = (string) $this->input('plan', '');
        $cycle = $this->input('cycle') === 'yearly' ? 'yearly' : 'monthly';

        $plan = Plan::first('slug', $planSlug);
        $moyasarPayment = $paymentId !== '' ? Moyasar::fetchPayment($paymentId) : null;

        if (!$plan || !$moyasarPayment || ($moyasarPayment['status'] ?? '') !== 'paid') {
            $this->flash('error', 'Payment was not completed. Please try again or use bank transfer.');
            self::redirect('/app/billing');
        }

        // Moyasar amounts are in halalas (SAR x 100); re-derive SAR for our records.
        $amount = ((float) ($moyasarPayment['amount'] ?? 0)) / 100;
        // Present only when the checkout form requested data-save-card="true" — used to charge
        // this same card automatically at the next renewal (see cron/daily_tasks.php).
        $cardToken = $moyasarPayment['source']['token'] ?? null;

        $this->activatePlan((int) $companyId, $plan, $cycle, $cardToken);

        Payment::create([
            'company_id' => $companyId,
            'plan_id' => $plan['id'],
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'moyasar',
            'reference' => (string) ($moyasarPayment['id'] ?? $paymentId),
            'status' => 'paid',
        ]);

        $this->flash('success', "Payment received — you're now on the {$plan['name']} plan.");
        self::redirect('/app/billing');
    }

    /** Kept for the admin's direct subscription override (no payment attached). */
    public function upgrade(): void
    {
        $this->verifyCsrf();
        if (!Auth::isCompanyOwner()) {
            $this->flash('error', 'Only the company owner can change the subscription plan.');
            self::redirect('/app/billing');
        }

        $companyId = Auth::companyId();
        $plan = Plan::first('slug', (string) $this->input('plan'));
        if (!$plan) {
            $this->flash('error', 'Invalid plan selected.');
            self::redirect('/app/billing');
        }

        $cycle = $this->input('cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $amount = $cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly'];

        $this->activatePlan($companyId, $plan, $cycle);

        Payment::create([
            'company_id' => $companyId,
            'plan_id' => $plan['id'],
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'mada',
            'reference' => 'PMT-' . strtoupper(bin2hex(random_bytes(3))),
            'status' => 'paid',
        ]);

        $this->flash('success', "You're now on the {$plan['name']} plan.");
        self::redirect('/app/billing');
    }

    public static function activatePlan(int $companyId, array $plan, string $cycle, ?string $cardToken = null): void
    {
        Company::update($companyId, ['plan_id' => $plan['id'], 'status' => 'active']);

        Subscription::create([
            'company_id' => $companyId,
            'plan_id' => $plan['id'],
            'billing_cycle' => $cycle,
            'status' => 'active',
            'current_period_end' => date('Y-m-d', strtotime($cycle === 'yearly' ? '+1 year' : '+30 days')),
            'moyasar_card_token' => $cardToken,
        ]);
    }
}
