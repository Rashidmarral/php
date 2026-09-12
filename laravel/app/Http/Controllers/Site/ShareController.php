<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\App\EstimateController;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Setting;
use App\Support\Moyasar;
use App\Support\Notifications;
use App\Support\Feature;
use App\Support\WebhookDispatcher;
use App\Support\Zatca\QrGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public, token-based (no login) pages so a link sent to a client — by WhatsApp, email, etc. —
 * actually opens for them. The token is a random 40-hex-char string (160 bits), unguessable and
 * never enumerable, so this is safe to expose without authentication: knowing the token is the
 * access control.
 */
class ShareController extends Controller
{
    public function estimate(string $token): View
    {
        $estimate = Estimate::where('share_token', $token)->first();
        abort_if(!$estimate, 404, 'This link is invalid or has expired.');
        if ($estimate->isApprovalBlocked()) {
            return view('site.document-not-available');
        }
        $client = $estimate->client_id ? Client::find($estimate->client_id) : null;
        $company = Company::find($estimate->company_id);

        // The client must see the sell price per line (cost scaled by markup), not
        // raw cost, and a subtotal that actually sums to those lines — see
        // EstimateController::sellPricedItems(). Raw items are fetched in the same
        // id order purely to carry each row's section_title into the merge below.
        $rawItems = EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get();
        $sellItems = EstimateController::sellPricedItems($estimate);
        $items = [];
        $lastSection = null;
        foreach ($rawItems as $i => $raw) {
            $sectionTitle = local($raw, 'section_title');
            $items[] = [
                ...$sellItems[$i],
                'showSection' => $sectionTitle !== '' && $sectionTitle !== $lastSection,
                'sectionTitle' => $sectionTitle,
            ];
            if ($sectionTitle !== '') {
                $lastSection = $sectionTitle;
            }
        }

        $subtotal = array_sum(array_column($sellItems, 'total'));
        $vatRate = (float) $estimate->tax_percent;
        $vatAmount = round($subtotal * $vatRate / 100, 2);
        $total = round($subtotal + $vatAmount, 2);

        return view('site.share-estimate', [
            'pageTitle' => $estimate->title,
            'estimate' => $estimate->toArray(),
            'items' => $items,
            'subtotal' => $subtotal,
            'vatRate' => $vatRate,
            'vatAmount' => $vatAmount,
            'total' => $total,
            'client' => $client,
            'company' => $company,
            'companyLogoUrl' => $company->logo_path ?: null,
        ]);
    }

    public function signEstimate(Request $request, string $token): RedirectResponse
    {
        $estimate = Estimate::where('share_token', $token)->first();
        abort_if(!$estimate, 404, 'This link is invalid or has expired.');
        abort_if($estimate->isApprovalBlocked(), 404, 'This estimate is not yet available.');
        if (in_array($estimate->status, ['accepted', 'declined'], true)) {
            return redirect('/e/' . $token);
        }

        $decision = $request->input('decision');
        if ($decision === 'accept') {
            $signedByName = trim((string) $request->input('signed_by_name'));
            $signatureData = (string) $request->input('signature_data');
            if ($signedByName === '' || !str_starts_with($signatureData, 'data:image/')) {
                return $this->redirectWithFlash('/e/' . $token, 'error', 'Please type your name and draw your signature before submitting.');
            }
            $estimate->update([
                'status' => 'accepted',
                'signed_at' => now(),
                'signed_by_name' => $signedByName,
                'signature_data' => $signatureData,
                'signed_ip' => $request->ip() ?? '',
            ]);
            Notifications::estimateSigned($estimate->id, $signedByName);
            WebhookDispatcher::dispatch($estimate->company_id, 'estimate.signed', $estimate->fresh()->toArray());
            $this->flash('success', 'Thank you — the estimate has been signed and accepted.');
        } else {
            $estimate->update(['status' => 'declined']);
            $this->flash('success', 'You have declined this estimate.');
        }
        return redirect('/e/' . $token);
    }

    public function estimatePdf(Request $request, string $token): Response
    {
        $estimate = Estimate::where('share_token', $token)->first();
        abort_if(!$estimate, 404, 'This link is invalid or has expired.');
        abort_if($estimate->isApprovalBlocked(), 404, 'This estimate is not yet available.');
        $client = $estimate->client_id ? Client::find($estimate->client_id) : null;
        $company = Company::find($estimate->company_id);
        $template = in_array($request->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $request->input('template') : 'modern';

        // Same reconciliation fix as EstimateController::pdf(): sell-priced lines,
        // subtotal/VAT/total derived from those lines.
        $sellItems = EstimateController::sellPricedItems($estimate);
        $items = array_map(fn ($i) => ['description' => $i['description'], 'qty' => $i['qty'], 'unit_price' => $i['unit_price'], 'total' => $i['total']], $sellItems);
        $subtotal = array_sum(array_column($sellItems, 'total'));
        $vatAmount = round($subtotal * (float) $estimate->tax_percent / 100, 2);
        $total = round($subtotal + $vatAmount, 2);

        return $this->streamPdf([
            'template' => $template,
            'lang' => 'en',
            'currency' => 'SAR',
            'docType' => 'Estimate',
            'docNumber' => (string) $estimate->id,
            'docDate' => $estimate->created_at,
            'status' => ucfirst($estimate->status),
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, ($company->vat_number ?? null) ? 'VAT: ' . $company->vat_number : null, ($company->cr_number ?? null) ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'billTo' => $client ? ['name' => $client->name, 'meta' => array_filter([$client->email ?? null, $client->phone ?? null, $client->address ?? null])] : null,
            'items' => $items,
            'subtotal' => $subtotal,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => (float) $estimate->tax_percent,
            'vatAmount' => $vatAmount,
            'total' => $total,
            'footerNote' => 'Generated by ' . Setting::siteName(),
        ], 'Estimate-' . $estimate->id . '.pdf');
    }

    public function invoice(string $token): View
    {
        $invoice = Invoice::where('share_token', $token)->first();
        abort_if(!$invoice, 404, 'This link is invalid or has expired.');
        if ($invoice->isApprovalBlocked()) {
            return view('site.document-not-available');
        }
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get()->toArray();
        $client = $invoice->client_id ? Client::find($invoice->client_id) : null;
        $company = Company::find($invoice->company_id);

        return view('site.share-invoice', [
            'pageTitle' => $invoice->invoice_number,
            'invoice' => $invoice->toArray(),
            'items' => $items,
            'client' => $client,
            'company' => $company?->toArray(),
            'zatcaQr' => $this->zatcaQrDataUri($invoice, $company),
        ]);
    }

    public function invoicePdf(Request $request, string $token): Response
    {
        $invoice = Invoice::where('share_token', $token)->first();
        abort_if(!$invoice, 404, 'This link is invalid or has expired.');
        abort_if($invoice->isApprovalBlocked(), 404, 'This invoice is not yet available.');
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $invoice->client_id ? Client::find($invoice->client_id) : null;
        $company = Company::find($invoice->company_id);
        $template = in_array($request->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $request->input('template') : 'modern';

        return $this->streamPdf([
            'template' => $template,
            'lang' => 'en',
            'currency' => 'SAR',
            'docType' => 'Invoice',
            'docNumber' => $invoice->invoice_number,
            'docDate' => $invoice->created_at,
            'validUntil' => $invoice->due_date,
            'status' => ucfirst($invoice->status),
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, ($company->vat_number ?? null) ? 'VAT: ' . $company->vat_number : null, ($company->cr_number ?? null) ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'billTo' => $client ? ['name' => $client->name, 'meta' => array_filter([$client->email ?? null, $client->phone ?? null, $client->address ?? null])] : null,
            'items' => $items->map(fn ($i) => ['description' => $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'total' => $i->total])->all(),
            'subtotal' => (float) $invoice->total - (float) $invoice->vat_amount,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => $invoice->vat_rate,
            'vatAmount' => (float) $invoice->vat_amount,
            'total' => (float) $invoice->total,
            'qrCode' => $this->zatcaQrDataUri($invoice, $company),
            'footerNote' => 'Generated by ' . Setting::siteName(),
        ], $invoice->invoice_number . '.pdf');
    }

    public function payInvoice(string $token): View|RedirectResponse
    {
        $invoice = Invoice::where('share_token', $token)->first();
        abort_if(!$invoice, 404, 'This link is invalid or has expired.');
        $company = Company::find($invoice->company_id);

        if ($invoice->status === 'paid') {
            return redirect('/i/' . $token);
        }
        if (!Moyasar::isConfiguredForCompany($company?->toArray()) || !Feature::allowsForCompany('online_invoice_payments', $company)) {
            return $this->redirectWithFlash('/i/' . $token, 'error', "Online payment isn't available for this invoice yet — please contact " . ($company->name ?? 'the company') . ' directly.');
        }

        return view('site.pay-invoice', [
            'pageTitle' => 'Pay ' . $invoice->invoice_number,
            'invoice' => $invoice,
            'company' => $company,
            'token' => $token,
            'moyasarPublishableKey' => (string) $company->moyasar_publishable_key,
        ]);
    }

    public function invoicePaymentCallback(Request $request, string $token): RedirectResponse
    {
        $invoice = Invoice::where('share_token', $token)->first();
        abort_if(!$invoice, 404, 'This link is invalid or has expired.');
        $company = Company::find($invoice->company_id);
        $paymentId = (string) $request->input('id', '');

        $moyasarPayment = ($paymentId !== '' && Moyasar::isConfiguredForCompany($company?->toArray()))
            ? Moyasar::fetchPaymentForCompany($paymentId, $company->toArray())
            : null;

        if (!$moyasarPayment || ($moyasarPayment['status'] ?? '') !== 'paid') {
            return $this->redirectWithFlash('/i/' . $token, 'error', 'Payment was not completed. Please try again.');
        }

        if ($invoice->status !== 'paid') {
            $invoice->update(['status' => 'paid']);
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'amount' => ((float) ($moyasarPayment['amount'] ?? 0)) / 100,
                'currency' => 'SAR',
                'method' => 'moyasar',
                'reference' => (string) ($moyasarPayment['id'] ?? $paymentId),
                'status' => 'paid',
                'payer_name' => (string) ($moyasarPayment['source']['name'] ?? ''),
            ]);
            Notifications::invoicePaid($invoice->id);
            WebhookDispatcher::dispatch($invoice->company_id, 'invoice.paid', $invoice->fresh()->toArray());
        }

        return $this->redirectWithFlash('/i/' . $token, 'success', 'Payment received — thank you!');
    }

    private function zatcaQrDataUri(Invoice $invoice, ?Company $company): ?string
    {
        $vatNumber = trim((string) ($company->vat_number ?? ''));
        if ($vatNumber === '') {
            return null;
        }
        $payload = QrGenerator::payload(
            $company->name ?? '',
            $vatNumber,
            $invoice->created_at->toAtomString(),
            number_format((float) $invoice->total, 2, '.', ''),
            number_format((float) $invoice->vat_amount, 2, '.', '')
        );
        return QrGenerator::renderSvgDataUri($payload);
    }
}
