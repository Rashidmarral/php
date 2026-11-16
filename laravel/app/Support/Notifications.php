<?php

namespace App\Support;

use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\PaymentCertificate;
use App\Models\Project;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Transactional email triggers. All sends go through Mailer, which is a no-op (returns
 * false, logs nothing) when the platform admin hasn't configured SMTP yet — every method
 * here is safe to call unconditionally regardless of whether email is set up.
 *
 * A handful of the more time-sensitive/urgent events below also send a short SMS to the
 * company's own phone number (Company::phone — there's no per-user phone column) as an
 * ADDITIONAL channel alongside email, via smsCompany(). That's a best-effort add-on: a
 * missing company phone number or an unreachable/misconfigured SMS gateway is swallowed
 * silently so it never breaks the email send happening in the same method.
 *
 * Each method below also records an in-app notification row (record()) alongside its
 * existing email/SMS sends — a purely additive third channel feeding the bell-icon
 * notification center (App\Http\Controllers\App\NotificationController), never a
 * replacement for the sends above.
 */
class Notifications
{
    public static function invoicePaid(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            return;
        }
        $company = Company::find($invoice->company_id);
        self::record($invoice->company_id, 'invoice_paid', "Invoice {$invoice->invoice_number} was paid", number_format((float) $invoice->total, 2) . ' SAR', "/app/invoices/{$invoice->id}");
        $owner = self::companyOwner($invoice->company_id);
        if ($owner) {
            Mailer::send(
                $owner->email,
                $owner->name,
                "Invoice {$invoice->invoice_number} was paid",
                "Hi {$owner->name},\n\nGood news — invoice {$invoice->invoice_number} for " . number_format((float) $invoice->total, 2) . " SAR has been paid.\n\nView it: " . rtrim((string) config('app.url'), '/') . "/app/invoices/{$invoice->id}"
            );
        }

        if (!empty($invoice->client_id)) {
            $client = Client::find($invoice->client_id);
            if ($client && !empty($client->email)) {
                Mailer::send(
                    $client->email,
                    $client->name,
                    "Payment received — {$invoice->invoice_number}",
                    "Hi {$client->name},\n\nThank you — your payment for invoice {$invoice->invoice_number} (" . number_format((float) $invoice->total, 2) . " SAR) has been received by " . ($company->name ?? 'the company') . ".\n\nThis is your receipt confirmation."
                );
            }
        }
    }

    public static function estimateSigned(int $estimateId, string $signedByName): void
    {
        $estimate = Estimate::find($estimateId);
        if (!$estimate) {
            return;
        }
        self::record($estimate->company_id, 'estimate_signed', "Estimate \"{$estimate->title}\" was signed", "{$signedByName} signed and accepted this estimate.", "/app/estimates/{$estimate->id}");
        $owner = self::companyOwner($estimate->company_id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            "Estimate \"{$estimate->title}\" was signed",
            "Hi {$owner->name},\n\n{$signedByName} just signed and accepted the estimate \"{$estimate->title}\" (" . number_format((float) $estimate->total, 2) . " SAR).\n\nView it: " . rtrim((string) config('app.url'), '/') . "/app/estimates/{$estimate->id}"
        );
    }

    public static function paymentCertificateSigned(int $certificateId, string $signedByName): void
    {
        $certificate = PaymentCertificate::find($certificateId);
        if (!$certificate) {
            return;
        }
        self::record($certificate->company_id, 'payment_certificate_signed', "Payment Certificate #{$certificate->certificate_number} was signed", "{$signedByName} signed off on this certificate.", "/app/payment-certificates/{$certificate->id}");
        $owner = self::companyOwner($certificate->company_id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            "Payment Certificate #{$certificate->certificate_number} was signed",
            "Hi {$owner->name},\n\n{$signedByName} just signed off on Payment Certificate #{$certificate->certificate_number} (" . number_format((float) $certificate->net_payable, 2) . " SAR net payable).\n\nView it: " . rtrim((string) config('app.url'), '/') . "/app/payment-certificates/{$certificate->id}"
        );
    }

    public static function changeOrderSigned(int $changeOrderId, string $signedByName): void
    {
        $changeOrder = ChangeOrder::find($changeOrderId);
        if (!$changeOrder) {
            return;
        }
        self::record($changeOrder->company_id, 'change_order_signed', "Change order \"{$changeOrder->title}\" was signed", "{$signedByName} signed this change order.", "/app/change-orders/{$changeOrder->id}");
        $owner = self::companyOwner($changeOrder->company_id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            "Change order \"{$changeOrder->title}\" was signed",
            "Hi {$owner->name},\n\n{$signedByName} just signed the change order \"{$changeOrder->title}\" (" . number_format((float) $changeOrder->amount, 2) . " SAR).\n\nView it: " . rtrim((string) config('app.url'), '/') . "/app/change-orders/{$changeOrder->id}"
        );
    }

    /**
     * Fired when a supplier submits their own bid through the public rfq-bid link
     * (ShareController::submitRfqBid()) rather than the GC typing it in via
     * RfqController::recordQuote() — the GC still needs to know a bid came in
     * either way, so this is the self-service path's analog of estimateSigned()
     * etc. above.
     */
    public static function rfqQuoteSubmitted(int $quoteId): void
    {
        $quote = RfqQuote::find($quoteId);
        if (!$quote) {
            return;
        }
        $rfq = Rfq::find($quote->rfq_id);
        if (!$rfq) {
            return;
        }
        $supplierName = Supplier::find($quote->supplier_id)->name ?? 'A supplier';
        self::record($rfq->company_id, 'rfq_quote_submitted', "New quote submitted for \"{$rfq->title}\"", "{$supplierName} submitted their own quote: " . number_format((float) $quote->total_amount, 2) . ' SAR.', "/app/rfqs/{$rfq->id}");
        $owner = self::companyOwner($rfq->company_id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            "New quote submitted for \"{$rfq->title}\"",
            "Hi {$owner->name},\n\n{$supplierName} just submitted their own quote for \"{$rfq->title}\" (" . number_format((float) $quote->total_amount, 2) . " SAR).\n\nCompare it: " . rtrim((string) config('app.url'), '/') . "/app/rfqs/{$rfq->id}"
        );
    }

    public static function trialEndingSoon(Company $company, int $daysLeft): void
    {
        self::record($company->id, 'trial_ending_soon', "Your trial ends in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's'), 'Choose a plan to keep using the platform without interruption.', '/app/billing');
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        $siteName = Setting::siteName();
        Mailer::send(
            $owner->email,
            $owner->name,
            "Your {$siteName} trial ends in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's'),
            "Hi {$owner->name},\n\nYour free trial ends in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's') . ". Choose a plan to keep using {$siteName} without interruption.\n\nSubscribe: " . rtrim((string) config('app.url'), '/') . '/app/billing'
        );
        self::smsCompany($company, "{$siteName}: your trial ends in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's') . " — subscribe at " . rtrim((string) config('app.url'), '/') . '/app/billing');
    }

    public static function subscriptionRenewed(Company $company, float $amount): void
    {
        self::record($company->id, 'subscription_renewed', 'Your subscription was renewed', number_format($amount, 2) . ' SAR was charged to your card on file.', '/app/billing');
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            'Your ' . Setting::siteName() . ' subscription was renewed',
            "Hi {$owner->name},\n\nYour subscription was renewed automatically — " . number_format($amount, 2) . " SAR was charged to your card on file.\n\nView your billing history: " . rtrim((string) config('app.url'), '/') . '/app/billing'
        );
    }

    public static function renewalFailed(Company $company, int $attempt): void
    {
        self::record($company->id, 'renewal_failed', "We couldn't renew your subscription", "Payment attempt {$attempt} didn't go through — update your payment method to avoid losing access.", '/app/billing');
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            "We couldn't renew your " . Setting::siteName() . ' subscription',
            "Hi {$owner->name},\n\nWe tried to charge your card on file for your subscription renewal, but the payment didn't go through (attempt {$attempt}). Please update your payment method to avoid losing access.\n\nUpdate billing: " . rtrim((string) config('app.url'), '/') . '/app/billing'
        );
        self::smsCompany($company, Setting::siteName() . ": your subscription renewal failed (attempt {$attempt}) — update your payment method at " . rtrim((string) config('app.url'), '/') . '/app/billing');
    }

    public static function complianceDocumentExpiring(Company $company, \App\Models\ComplianceDocument $document): void
    {
        self::record($company->id, 'compliance_document_expiring', "{$document->name} expires soon", "Due to expire on {$document->expiry_date?->format('Y-m-d')}.", '/app/business-setup/compliance');
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            "{$document->name} expires soon",
            "Hi {$owner->name},\n\nYour \"{$document->name}\" is due to expire on {$document->expiry_date?->format('Y-m-d')}. Renew it soon to stay eligible for government tenders and stay compliant.\n\nManage your compliance documents: " . rtrim((string) config('app.url'), '/') . '/app/business-setup/compliance'
        );
        self::smsCompany($company, "{$document->name} expires on {$document->expiry_date?->format('Y-m-d')} — renew it soon to stay compliant.");
    }

    public static function teamMemberDocumentExpiring(Company $company, User $member, \App\Models\TeamMemberDocument $document): void
    {
        self::record($company->id, 'team_member_document_expiring', "{$member->name}'s {$document->name} expires soon", "Due to expire on {$document->expiry_date?->format('Y-m-d')}.", "/app/team/{$member->id}/documents");
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            "{$member->name}'s {$document->name} expires soon",
            "Hi {$owner->name},\n\n{$member->name}'s \"{$document->name}\" is due to expire on {$document->expiry_date?->format('Y-m-d')}. Renew it soon — an expired iqama, work permit, or health certificate can mean fines and the worker being unable to keep working.\n\nManage team documents: " . rtrim((string) config('app.url'), '/') . "/app/team/{$member->id}/documents"
        );
        self::smsCompany($company, "{$member->name}'s {$document->name} expires on {$document->expiry_date?->format('Y-m-d')} — renew it soon.");
    }

    public static function supplierDocumentExpiring(Company $company, \App\Models\Supplier $supplier, \App\Models\SupplierDocument $document): void
    {
        self::record($company->id, 'supplier_document_expiring', "{$supplier->name}'s {$document->name} expires soon", "Due to expire on {$document->expiry_date?->format('Y-m-d')}.", "/app/suppliers/{$supplier->id}");
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        Mailer::send(
            $owner->email,
            $owner->name,
            "{$supplier->name}'s {$document->name} expires soon",
            "Hi {$owner->name},\n\n{$supplier->name}'s \"{$document->name}\" is due to expire on {$document->expiry_date?->format('Y-m-d')}. Renew it soon to keep this supplier's pre-qualification current.\n\nView the supplier: " . rtrim((string) config('app.url'), '/') . "/app/suppliers/{$supplier->id}"
        );
        self::smsCompany($company, "{$supplier->name}'s {$document->name} expires on {$document->expiry_date?->format('Y-m-d')} — renew it soon.");
    }

    public static function bankGuaranteeExpiring(Company $company, \App\Models\BankGuarantee $guarantee): void
    {
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        $typeLabel = \App\Models\BankGuarantee::TYPES[$guarantee->type] ?? 'bank guarantee';
        self::record($company->id, 'bank_guarantee_expiring', "{$typeLabel} expires soon", "Due to expire on {$guarantee->expiry_date?->format('Y-m-d')}.", "/app/projects/{$guarantee->project_id}");
        Mailer::send(
            $owner->email,
            $owner->name,
            "{$typeLabel} expires soon",
            "Hi {$owner->name},\n\nThe \"{$typeLabel}\"" . ($guarantee->bank_name ? " from {$guarantee->bank_name}" : '') . " is due to expire on {$guarantee->expiry_date?->format('Y-m-d')}. Renew it with the bank before it lapses, or it will no longer satisfy the client's contract requirement.\n\nView the project: " . rtrim((string) config('app.url'), '/') . "/app/projects/{$guarantee->project_id}"
        );
        self::smsCompany($company, "Your {$typeLabel} expires on {$guarantee->expiry_date?->format('Y-m-d')} — renew it with the bank before it lapses.");
    }

    public static function retentionReleaseDue(Company $company, Project $project, float $retentionHeld): void
    {
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        $dlpDate = $project->defects_liability_end_date?->format('Y-m-d');
        self::record($company->id, 'retention_release_due', "Retention on \"{$project->name}\" is due for release", number_format($retentionHeld, 2) . ' SAR still held — review and release what\'s due to the client.', "/app/projects/{$project->id}");
        Mailer::send(
            $owner->email,
            $owner->name,
            "Retention on \"{$project->name}\" is due for release",
            "Hi {$owner->name},\n\nThe defects liability period on \"{$project->name}\" " . ($dlpDate ? "ends on {$dlpDate}" : 'is ending soon') . ", and " . number_format($retentionHeld, 2) . " SAR in retention is still held back across its invoices. Review it and release what's due to the client.\n\nView the project: " . rtrim((string) config('app.url'), '/') . "/app/projects/{$project->id}"
        );
        self::smsCompany($company, "Retention on \"{$project->name}\": " . number_format($retentionHeld, 2) . " SAR still held — its defects liability period " . ($dlpDate ? "ends {$dlpDate}" : 'is ending soon') . ".");
    }

    /**
     * Distinct concern from retentionReleaseDue() above: that one is about money (retention
     * still held), this one is about defects — nudging the company to walk the punch list
     * and raise any outstanding warranty claims before the defects liability period closes.
     * Both reminders can fire independently for the same project, guarded by their own
     * separate *_reminder_sent_at columns (see RunDailyTasks).
     */
    public static function defectsLiabilityPeriodEnding(Company $company, Project $project, int $openPunchListCount): void
    {
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        $dlpDate = $project->defects_liability_end_date?->format('Y-m-d');
        $openNote = $openPunchListCount === 1 ? '1 unresolved punch-list item' : "{$openPunchListCount} unresolved punch-list items";
        self::record($company->id, 'defects_liability_period_ending', "Defects liability period on \"{$project->name}\" is ending soon", ($dlpDate ? "Ends on {$dlpDate}" : 'Ending soon') . " — {$openNote} still open.", "/app/projects/{$project->id}");
        Mailer::send(
            $owner->email,
            $owner->name,
            "Defects liability period on \"{$project->name}\" is ending soon",
            "Hi {$owner->name},\n\nThe defects liability period on \"{$project->name}\" " . ($dlpDate ? "ends on {$dlpDate}" : 'is ending soon') . ", and there " . ($openPunchListCount === 1 ? 'is' : 'are') . " {$openNote} still open. Walk the site and raise any outstanding defects as warranty claims before the window closes.\n\nView the project: " . rtrim((string) config('app.url'), '/') . "/app/projects/{$project->id}"
        );
        self::smsCompany($company, "\"{$project->name}\": defects liability period " . ($dlpDate ? "ends {$dlpDate}" : 'is ending soon') . " — {$openNote} still open.");
    }

    public static function equipmentMaintenanceDue(Company $company, \App\Models\Equipment $equipment, \App\Models\EquipmentMaintenanceLog $log): void
    {
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        self::record($company->id, 'equipment_maintenance_due', "{$equipment->name}'s maintenance is due soon", "Maintenance due on {$log->next_due_date?->format('Y-m-d')}.", "/app/equipment/{$equipment->id}");
        Mailer::send(
            $owner->email,
            $owner->name,
            "{$equipment->name}'s maintenance is due soon",
            "Hi {$owner->name},\n\n\"{$equipment->name}\" has maintenance due on {$log->next_due_date?->format('Y-m-d')}. Schedule it before the asset is next needed on site.\n\nView the asset: " . rtrim((string) config('app.url'), '/') . "/app/equipment/{$equipment->id}"
        );
        self::smsCompany($company, "\"{$equipment->name}\" has maintenance due on {$log->next_due_date?->format('Y-m-d')} — schedule it soon.");
    }

    private static function companyOwner(int $companyId): ?User
    {
        return User::where('company_id', $companyId)->where('role', 'owner')->first();
    }

    /**
     * Low-level in-app notification writer: just creates one notifications row for the
     * bell-icon notification center. Company-wide (not per-user), matching how the
     * notifications table itself is scoped — see App\Models\Notification's docblock.
     * Every public method above calls this once, purely additive alongside its existing
     * email/SMS sends.
     */
    public static function record(int $companyId, string $type, string $title, ?string $message = null, ?string $link = null): void
    {
        Notification::create([
            'company_id' => $companyId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
        ]);
    }

    /**
     * Best-effort SMS to the company's own contact number (Company::phone — there's no
     * per-user phone column), sent alongside (never instead of) the matching email above.
     * A missing/invalid phone number or an unreachable/misconfigured SMS gateway is
     * swallowed silently so it can never take down the email notification it accompanies.
     */
    private static function smsCompany(Company $company, string $message): void
    {
        if (!Sms::isConfigured() || empty($company->phone)) {
            return;
        }
        try {
            $result = Sms::sendMessage($company->phone, $message);
            if (empty($result['ok'])) {
                Log::warning('SMS notification failed to send', ['company_id' => $company->id, 'error' => $result['error'] ?? ($result['data'] ?? null)]);
            }
        } catch (Throwable $e) {
            Log::warning('SMS notification threw an exception', ['company_id' => $company->id, 'message' => $e->getMessage()]);
        }
    }
}
