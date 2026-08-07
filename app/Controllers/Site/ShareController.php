<?php

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Moyasar;
use App\Core\Notifications;
use App\Core\Zatca\Phase1Qr;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;

/**
 * Public, token-based (no login) pages so a link sent to a client — by WhatsApp, email, etc. —
 * actually opens for them. The token is a random 40-hex-char string (160 bits), unguessable and
 * never enumerable, so this is safe to expose without authentication: knowing the token is the
 * access control.
 */
class ShareController extends Controller
{
    public function estimate(string $token): void
    {
        $estimate = Estimate::first('share_token', $token);
        if (!$estimate) {
            http_response_code(404);
            die('This link is invalid or has expired.');
        }
        $items = EstimateItem::where('estimate_id', $estimate['id'], 'id ASC');
        $client = $estimate['client_id'] ? Client::find((int) $estimate['client_id']) : null;
        $company = Company::find((int) $estimate['company_id']);

        $this->view('site/share-estimate', [
            'pageTitle' => $estimate['title'],
            'estimate' => $estimate,
            'items' => $items,
            'client' => $client,
            'company' => $company,
        ], 'layouts/site');
    }

    public function signEstimate(string $token): void
    {
        $this->verifyCsrf();
        $estimate = Estimate::first('share_token', $token);
        if (!$estimate) {
            http_response_code(404);
            die('This link is invalid or has expired.');
        }
        if (in_array($estimate['status'], ['accepted', 'declined'], true)) {
            self::redirect('/e/' . $token);
        }

        $decision = $this->input('decision');
        if ($decision === 'accept') {
            $signedByName = trim((string) $this->input('signed_by_name'));
            $signatureData = (string) $this->input('signature_data');
            if ($signedByName === '' || !str_starts_with($signatureData, 'data:image/')) {
                $this->flash('error', 'Please type your name and draw your signature before submitting.');
                self::redirect('/e/' . $token);
            }
            Estimate::update($estimate['id'], [
                'status' => 'accepted',
                'signed_at' => date('Y-m-d H:i:s'),
                'signed_by_name' => $signedByName,
                'signature_data' => $signatureData,
                'signed_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            Notifications::estimateSigned((int) $estimate['id'], $signedByName);
            $this->flash('success', 'Thank you — the estimate has been signed and accepted.');
        } else {
            Estimate::update($estimate['id'], ['status' => 'declined']);
            $this->flash('success', 'You have declined this estimate.');
        }
        self::redirect('/e/' . $token);
    }

    public function estimatePdf(string $token): void
    {
        $estimate = Estimate::first('share_token', $token);
        if (!$estimate) {
            http_response_code(404);
            die('This link is invalid or has expired.');
        }
        $items = EstimateItem::where('estimate_id', $estimate['id'], 'id ASC');
        $client = $estimate['client_id'] ? Client::find((int) $estimate['client_id']) : null;
        $company = Company::find((int) $estimate['company_id']);
        $template = in_array($this->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $this->input('template') : 'modern';

        $this->streamPdf([
            'template' => $template,
            'lang' => 'en',
            'currency' => 'SAR',
            'docType' => 'Estimate',
            'docNumber' => (string) $estimate['id'],
            'docDate' => $estimate['created_at'],
            'status' => ucfirst($estimate['status']),
            'issuer' => ['name' => $company['name'] ?? '', 'meta' => array_filter([$company['phone'] ?? null, ($company['vat_number'] ?? null) ? 'VAT: ' . $company['vat_number'] : null, ($company['cr_number'] ?? null) ? 'CR: ' . $company['cr_number'] : null])],
            'companyNameAr' => $company['name_ar'] ?? '',
            'companyLogo' => !empty($company['logo_path']) ? ('file://' . BASE_PATH . '/public' . $company['logo_path']) : null,
            'billTo' => $client ? ['name' => $client['name'], 'meta' => array_filter([$client['email'] ?? null, $client['phone'] ?? null, $client['address'] ?? null])] : null,
            'items' => array_map(fn($i) => ['description' => $i['description'], 'qty' => $i['qty'], 'unit_price' => $i['unit_cost'], 'total' => $i['total']], $items),
            'subtotal' => (float) $estimate['total'],
            'discountPercent' => 0,
            'discountAmount' => 0,
            'total' => (float) $estimate['total'],
            'footerNote' => 'Generated by BuildXact Saudi',
        ], 'Estimate-' . $estimate['id'] . '.pdf');
    }

    public function invoice(string $token): void
    {
        $invoice = Invoice::first('share_token', $token);
        if (!$invoice) {
            http_response_code(404);
            die('This link is invalid or has expired.');
        }
        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $company = Company::find((int) $invoice['company_id']);

        $this->view('site/share-invoice', [
            'pageTitle' => $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => $items,
            'client' => $client,
            'company' => $company,
            'zatcaQr' => $this->zatcaQrDataUri($invoice, $company),
        ], 'layouts/site');
    }

    public function invoicePdf(string $token): void
    {
        $invoice = Invoice::first('share_token', $token);
        if (!$invoice) {
            http_response_code(404);
            die('This link is invalid or has expired.');
        }
        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $company = Company::find((int) $invoice['company_id']);
        $template = in_array($this->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $this->input('template') : 'modern';

        $this->streamPdf([
            'template' => $template,
            'lang' => 'en',
            'currency' => 'SAR',
            'docType' => 'Invoice',
            'docNumber' => $invoice['invoice_number'],
            'docDate' => $invoice['created_at'],
            'validUntil' => $invoice['due_date'],
            'status' => ucfirst($invoice['status']),
            'issuer' => ['name' => $company['name'] ?? '', 'meta' => array_filter([$company['phone'] ?? null, ($company['vat_number'] ?? null) ? 'VAT: ' . $company['vat_number'] : null, ($company['cr_number'] ?? null) ? 'CR: ' . $company['cr_number'] : null])],
            'companyNameAr' => $company['name_ar'] ?? '',
            'companyLogo' => !empty($company['logo_path']) ? ('file://' . BASE_PATH . '/public' . $company['logo_path']) : null,
            'billTo' => $client ? ['name' => $client['name'], 'meta' => array_filter([$client['email'] ?? null, $client['phone'] ?? null, $client['address'] ?? null])] : null,
            'items' => array_map(fn($i) => ['description' => $i['description'], 'qty' => $i['qty'], 'unit_price' => $i['unit_price'], 'total' => $i['total']], $items),
            'subtotal' => (float) $invoice['total'] - (float) $invoice['vat_amount'],
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => $invoice['vat_rate'],
            'vatAmount' => (float) $invoice['vat_amount'],
            'total' => (float) $invoice['total'],
            'qrCode' => $this->zatcaQrDataUri($invoice, $company),
            'footerNote' => 'Generated by BuildXact Saudi',
        ], $invoice['invoice_number'] . '.pdf');
    }

    public function payInvoice(string $token): void
    {
        $invoice = Invoice::first('share_token', $token);
        if (!$invoice) {
            http_response_code(404);
            die('This link is invalid or has expired.');
        }
        $company = Company::find((int) $invoice['company_id']);

        if ($invoice['status'] === 'paid') {
            self::redirect('/i/' . $token);
        }
        if (!Moyasar::isConfiguredForCompany($company)) {
            $this->flash('error', 'Online payment isn\'t available for this invoice yet — please contact ' . ($company['name'] ?? 'the company') . ' directly.');
            self::redirect('/i/' . $token);
        }

        $this->view('site/pay-invoice', [
            'pageTitle' => 'Pay ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'company' => $company,
            'token' => $token,
            'moyasarPublishableKey' => (string) $company['moyasar_publishable_key'],
        ], 'layouts/site');
    }

    public function invoicePaymentCallback(string $token): void
    {
        $invoice = Invoice::first('share_token', $token);
        if (!$invoice) {
            http_response_code(404);
            die('This link is invalid or has expired.');
        }
        $company = Company::find((int) $invoice['company_id']);
        $paymentId = (string) $this->input('id', '');

        $moyasarPayment = ($paymentId !== '' && Moyasar::isConfiguredForCompany($company))
            ? Moyasar::fetchPaymentForCompany($paymentId, $company)
            : null;

        if (!$moyasarPayment || ($moyasarPayment['status'] ?? '') !== 'paid') {
            $this->flash('error', 'Payment was not completed. Please try again.');
            self::redirect('/i/' . $token);
        }

        if ($invoice['status'] !== 'paid') {
            Invoice::update($invoice['id'], ['status' => 'paid']);
            InvoicePayment::create([
                'invoice_id' => $invoice['id'],
                'company_id' => $invoice['company_id'],
                'amount' => ((float) ($moyasarPayment['amount'] ?? 0)) / 100,
                'currency' => 'SAR',
                'method' => 'moyasar',
                'reference' => (string) ($moyasarPayment['id'] ?? $paymentId),
                'status' => 'paid',
                'payer_name' => (string) ($moyasarPayment['source']['name'] ?? ''),
            ]);
            Notifications::invoicePaid((int) $invoice['id']);
        }

        $this->flash('success', 'Payment received — thank you!');
        self::redirect('/i/' . $token);
    }

    private function zatcaQrDataUri(array $invoice, ?array $company): ?string
    {
        $vatNumber = trim((string) ($company['vat_number'] ?? ''));
        if ($vatNumber === '') {
            return null;
        }
        $payload = Phase1Qr::payload(
            $company['name'] ?? '',
            $vatNumber,
            date('c', strtotime((string) $invoice['created_at'])),
            number_format((float) $invoice['total'], 2, '.', ''),
            number_format((float) $invoice['vat_amount'], 2, '.', '')
        );
        return Phase1Qr::renderSvgDataUri($payload);
    }
}
