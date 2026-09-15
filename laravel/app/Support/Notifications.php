<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
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

    public static function trialEndingSoon(Company $company, int $daysLeft): void
    {
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

    public static function bankGuaranteeExpiring(Company $company, \App\Models\BankGuarantee $guarantee): void
    {
        $owner = self::companyOwner($company->id);
        if (!$owner) {
            return;
        }
        $typeLabel = \App\Models\BankGuarantee::TYPES[$guarantee->type] ?? 'bank guarantee';
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
        Mailer::send(
            $owner->email,
            $owner->name,
            "Retention on \"{$project->name}\" is due for release",
            "Hi {$owner->name},\n\nThe defects liability period on \"{$project->name}\" " . ($dlpDate ? "ends on {$dlpDate}" : 'is ending soon') . ", and " . number_format($retentionHeld, 2) . " SAR in retention is still held back across its invoices. Review it and release what's due to the client.\n\nView the project: " . rtrim((string) config('app.url'), '/') . "/app/projects/{$project->id}"
        );
        self::smsCompany($company, "Retention on \"{$project->name}\": " . number_format($retentionHeld, 2) . " SAR still held — its defects liability period " . ($dlpDate ? "ends {$dlpDate}" : 'is ending soon') . ".");
    }

    private static function companyOwner(int $companyId): ?User
    {
        return User::where('company_id', $companyId)->where('role', 'owner')->first();
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
