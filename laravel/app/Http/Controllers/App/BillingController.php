<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Support\Billing;
use App\Support\Moyasar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BillingController extends Controller
{
    private const ALLOWED_RECEIPT_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function index(): View
    {
        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get()->toArray();
        $currentPlan = $company->plan_id ? Plan::find($company->plan_id) : null;
        $subscription = Subscription::where('company_id', $companyId)->orderByDesc('created_at')->first();
        $payments = Payment::where('company_id', $companyId)->orderByDesc('created_at')->get()->toArray();
        $pendingPayment = Payment::where('company_id', $companyId)->where('status', 'pending')->orderByDesc('created_at')->first();

        return view('app.billing.index', [
            'company' => $company->toArray(),
            'plans' => $plans,
            'currentPlan' => $currentPlan?->toArray(),
            'subscription' => $subscription?->toArray(),
            'payments' => $payments,
            'pendingPayment' => $pendingPayment?->toArray(),
        ]);
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        if (!Auth::user()->isCompanyOwner()) {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.billing.owner_only'));
        }

        $plan = Plan::where('slug', (string) $request->input('plan'))->first();
        if (!$plan) {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.billing.invalid_plan'));
        }
        $cycle = $request->input('cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $amount = (float) ($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);

        return view('app.billing.checkout', [
            'plan' => $plan->toArray(),
            'cycle' => $cycle,
            'amount' => $amount,
            'bankTransferEnabled' => Setting::get('bank_transfer_enabled') === '1',
            'bank' => [
                'name' => Setting::get('bank_name', ''),
                'accountName' => Setting::get('bank_account_name', ''),
                'iban' => Setting::get('bank_iban', ''),
                'accountNumber' => Setting::get('bank_account_number', ''),
            ],
            'moyasarConfigured' => Moyasar::isConfigured(),
            'moyasarPublishableKey' => Moyasar::publishableKey(),
        ]);
    }

    public function requestBankTransfer(Request $request): RedirectResponse
    {
        if (!Auth::user()->isCompanyOwner()) {
            return redirect('/app/billing');
        }

        $companyId = Auth::user()->company_id;
        $plan = Plan::where('slug', (string) $request->input('plan'))->first();
        if (!$plan) {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.billing.invalid_plan'));
        }
        $cycle = $request->input('cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $amount = (float) ($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);

        $data = [
            'company_id' => $companyId,
            'subscription_id' => null,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'bank_transfer',
            'reference' => 'BT-' . strtoupper(bin2hex(random_bytes(4))),
            'status' => 'pending',
        ];

        $receiptAttached = false;
        $receipt = $request->file('receipt');
        if ($receipt && $receipt->isValid()) {
            $mime = $receipt->getMimeType();
            if (!isset(self::ALLOWED_RECEIPT_TYPES[$mime])) {
                return $this->redirectWithFlash('/app/billing/checkout?plan=' . urlencode($plan->slug) . '&cycle=' . $cycle, 'error', t('user.billing.receipt_pdf_jpg_png'));
            }
            if ($receipt->getSize() > 10 * 1024 * 1024) {
                return $this->redirectWithFlash('/app/billing/checkout?plan=' . urlencode($plan->slug) . '&cycle=' . $cycle, 'error', t('user.billing.receipt_max_size'));
            }
            $filename = 'receipt-' . $companyId . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_RECEIPT_TYPES[$mime];
            $receipt->move(public_path('uploads/payment-receipts'), $filename);
            $data['proof_file_path'] = "/uploads/payment-receipts/{$filename}";
            $receiptAttached = true;
        }

        Payment::create($data);

        $this->flash('success', $receiptAttached ? t('user.billing.bank_transfer_submitted_with_receipt') : t('user.billing.bank_transfer_submitted'));
        return redirect('/app/billing');
    }

    public function moyasarCallback(Request $request): RedirectResponse
    {
        $companyId = Auth::user()->company_id;
        $paymentId = (string) $request->input('id', '');
        $planSlug = (string) $request->input('plan', '');
        $cycle = $request->input('cycle') === 'yearly' ? 'yearly' : 'monthly';

        $plan = Plan::where('slug', $planSlug)->first();
        $moyasarPayment = $paymentId !== '' ? Moyasar::fetchPayment($paymentId) : null;

        if (!$plan || !$moyasarPayment || ($moyasarPayment['status'] ?? '') !== 'paid') {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.billing.payment_not_completed'));
        }

        // Moyasar amounts are in halalas (SAR x 100); re-derive SAR for our records.
        $amount = ((float) ($moyasarPayment['amount'] ?? 0)) / 100;
        // Present only when the checkout form requested data-save-card="true" — used to charge
        // this same card automatically at the next renewal.
        $cardToken = $moyasarPayment['source']['token'] ?? null;

        Billing::activatePlan($companyId, $plan, $cycle, $cardToken);

        Payment::create([
            'company_id' => $companyId,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'moyasar',
            'reference' => (string) ($moyasarPayment['id'] ?? $paymentId),
            'status' => 'paid',
        ]);

        $this->flash('success', t('user.billing.payment_received_on_plan', ['plan' => $plan->name]));
        return redirect('/app/billing');
    }

    /** Kept for the admin's direct subscription override (no payment attached). */
    public function upgrade(Request $request): RedirectResponse
    {
        if (!Auth::user()->isCompanyOwner()) {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.billing.owner_only'));
        }

        $companyId = Auth::user()->company_id;
        $plan = Plan::where('slug', (string) $request->input('plan'))->first();
        if (!$plan) {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.billing.invalid_plan'));
        }

        $cycle = $request->input('cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $amount = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        Billing::activatePlan($companyId, $plan, $cycle);

        Payment::create([
            'company_id' => $companyId,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'mada',
            'reference' => 'PMT-' . strtoupper(bin2hex(random_bytes(3))),
            'status' => 'paid',
        ]);

        $this->flash('success', t('user.billing.now_on_plan', ['plan' => $plan->name]));
        return redirect('/app/billing');
    }
}
