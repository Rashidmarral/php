<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\User;

/**
 * Transactional email triggers. All sends go through Mailer, which is a no-op (returns
 * false, logs nothing) when the platform admin hasn't configured SMTP yet — every method
 * here is safe to call unconditionally regardless of whether email is set up.
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
        Mailer::send(
            $owner->email,
            $owner->name,
            "Your BuildXact Saudi trial ends in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's'),
            "Hi {$owner->name},\n\nYour free trial ends in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's') . ". Choose a plan to keep using BuildXact Saudi without interruption.\n\nSubscribe: " . rtrim((string) config('app.url'), '/') . '/app/billing'
        );
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
            'Your BuildXact Saudi subscription was renewed',
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
            "We couldn't renew your BuildXact Saudi subscription",
            "Hi {$owner->name},\n\nWe tried to charge your card on file for your subscription renewal, but the payment didn't go through (attempt {$attempt}). Please update your payment method to avoid losing access.\n\nUpdate billing: " . rtrim((string) config('app.url'), '/') . '/app/billing'
        );
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
    }

    private static function companyOwner(int $companyId): ?User
    {
        return User::where('company_id', $companyId)->where('role', 'owner')->first();
    }
}
