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
    private const ALLOWED_RECEIPT_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

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

        $data = [
            'company_id' => $companyId,
            'subscription_id' => null,
            'plan_id' => $plan['id'],
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'bank_transfer',
            'reference' => 'BT-' . strtoupper(bin2hex(random_bytes(4))),
            'status' => 'pending',
        ];

        $uploadError = $this->handleReceiptUpload($data);
        if ($uploadError) {
            $this->flash('error', $uploadError);
            self::redirect('/app/billing/checkout?plan=' . urlencode($plan['slug']) . '&cycle=' . $cycle);
        }

        Payment::create($data);

        $this->flash('success', 'Your bank transfer request has been submitted' . (isset($data['proof_file_path']) ? ' with your receipt attached' : '') . '. Your plan will be activated once our team confirms receipt of payment.');
        self::redirect('/app/billing');
    }

    /** @param array $data by reference — sets proof_file_path on success */
    private function handleReceiptUpload(array &$data): ?string
    {
        if (empty($_FILES['receipt']['tmp_name']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $mime = mime_content_type($_FILES['receipt']['tmp_name']);
        if (!isset(self::ALLOWED_RECEIPT_TYPES[$mime])) {
            return 'Receipt must be a PDF, JPG, or PNG file.';
        }
        if ($_FILES['receipt']['size'] > 10 * 1024 * 1024) {
            return 'Receipt must be smaller than 10MB.';
        }
        $dir = BASE_PATH . '/public/uploads/payment-receipts';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'receipt-' . Auth::companyId() . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_RECEIPT_TYPES[$mime];
        move_uploaded_file($_FILES['receipt']['tmp_name'], "{$dir}/{$filename}");
        $data['proof_file_path'] = "/uploads/payment-receipts/{$filename}";
        return null;
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
