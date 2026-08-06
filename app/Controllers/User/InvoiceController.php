<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Env;
use App\Core\Feature;
use App\Core\Lang;
use App\Core\Settings;
use App\Core\WhatsApp;
use App\Core\Zatca\ApiClient;
use App\Core\Zatca\Phase1Qr;
use App\Core\Zatca\UblInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Material;
use App\Models\Project;

class InvoiceController extends Controller
{
    /** SHA-256("0") base64-encoded — ZATCA's documented fixed PIH value for a company's very first reported invoice. */
    private const ZATCA_GENESIS_HASH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function index(): void
    {
        $invoices = Invoice::query(
            'SELECT i.*, c.name AS client_name FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.company_id = ? ORDER BY i.created_at DESC',
            [Auth::companyId()]
        )->fetchAll();
        $this->view('user/invoices/index', ['pageTitle' => 'Invoices', 'invoices' => $invoices], 'layouts/app');
    }

    public function create(): void
    {
        $companyId = Auth::companyId();
        $clients = Client::where('company_id', $companyId, 'name ASC');
        $projects = Project::where('company_id', $companyId, 'name ASC');
        $nextNumber = 'INV-' . (1000 + Invoice::count('company_id = ?', [$companyId]) + 1);
        $materials = Material::query(
            'SELECT m.*, s.name AS supplier_name FROM materials m LEFT JOIN suppliers s ON s.id = m.supplier_id WHERE m.company_id = ? ORDER BY m.category ASC, m.name ASC',
            [$companyId]
        )->fetchAll();
        $company = Company::find($companyId);
        $this->view('user/invoices/form', [
            'pageTitle' => 'New Invoice',
            'clients' => $clients,
            'projects' => $projects,
            'nextNumber' => $nextNumber,
            'vatRate' => (float) Settings::get('vat_rate', 15),
            'materials' => $materials,
            'defaultRetentionPercent' => (float) ($company['default_retention_percent'] ?? 0),
        ], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $companyId = Auth::companyId();

        $descriptions = $_POST['item_description'] ?? [];
        $qtys = $_POST['item_qty'] ?? [];
        $prices = $_POST['item_price'] ?? [];

        $subtotal = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1);
            $price = (float) ($prices[$i] ?? 0);
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;
            $items[] = ['description' => $desc, 'qty' => $qty, 'unit_price' => $price, 'total' => $lineTotal];
        }

        $applyVat = (bool) $this->input('apply_vat', true);
        $vatRate = $applyVat ? (float) Settings::get('vat_rate', 15) : 0;
        $vatAmount = $subtotal * $vatRate / 100;
        $total = $subtotal + $vatAmount;

        $retentionPercent = min(100, max(0, (float) $this->input('retention_percent', 0)));
        $retentionAmount = $subtotal * $retentionPercent / 100;

        $invoiceId = Invoice::create([
            'company_id' => $companyId,
            'project_id' => $this->input('project_id') ?: null,
            'client_id' => $this->input('client_id') ?: null,
            'invoice_number' => trim((string) $this->input('invoice_number')) ?: ('INV-' . (1000 + Invoice::count('company_id = ?', [$companyId]) + 1)),
            'status' => 'unpaid',
            'total' => $total,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'due_date' => $this->input('due_date') ?: null,
            'retention_percent' => $retentionPercent,
            'retention_amount' => $retentionAmount,
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        foreach ($items as $item) {
            InvoiceItem::create(['invoice_id' => $invoiceId, ...$item]);
        }

        $company = Company::find($companyId);
        $client = $this->input('client_id') ? Client::find((int) $this->input('client_id')) : null;
        $this->chainZatca(Invoice::find($invoiceId), $company, $client, $items);

        $this->flash('success', 'Invoice created.');
        self::redirect('/app/invoices/' . $invoiceId);
    }

    /** Populates the ZATCA UUID/ICV/hash-chain fields for a newly created invoice (Phase 2 groundwork). */
    private function chainZatca(array $invoice, ?array $company, ?array $client, array $items): void
    {
        if (!$company) {
            return;
        }
        $uuid = self::uuidV4();
        $icv = (int) ($company['zatca_last_icv'] ?? 0) + 1;
        $previousHash = $company['zatca_last_invoice_hash'] ?: self::ZATCA_GENESIS_HASH;

        $xml = UblInvoice::build($invoice, $company, $client, $items, $uuid, $icv, $previousHash);
        $hash = UblInvoice::hash($xml);

        Invoice::update($invoice['id'], [
            'zatca_uuid' => $uuid,
            'zatca_icv' => $icv,
            'zatca_hash' => $hash,
            'zatca_previous_hash' => $previousHash,
        ]);
        Company::update($company['id'], [
            'zatca_last_icv' => $icv,
            'zatca_last_invoice_hash' => $hash,
        ]);
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    /** @return array<int, array{description:string,qty:float,unit_price:float,total:float}> */
    private function itemsForXml(array $invoiceItems): array
    {
        return array_map(fn($i) => [
            'description' => $i['description'],
            'qty' => $i['qty'],
            'unit_price' => $i['unit_price'],
            'total' => $i['total'],
        ], $invoiceItems);
    }

    public function show(string $id): void
    {
        $invoice = $this->findOwned((int) $id);
        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $project = $invoice['project_id'] ? Project::find((int) $invoice['project_id']) : null;
        $company = Company::find((int) $invoice['company_id']);

        if (empty($invoice['share_token'])) {
            Invoice::update($invoice['id'], ['share_token' => bin2hex(random_bytes(20))]);
            $invoice['share_token'] = Invoice::find($invoice['id'])['share_token'];
        }
        $shareUrl = rtrim(Env::get('APP_URL', ''), '/') . '/i/' . $invoice['share_token'];

        $whatsappLink = null;
        if ($client && !empty($client['phone'])) {
            $message = "Hi {$client['name']}, your invoice {$invoice['invoice_number']} from {$company['name']} is ready: {$shareUrl}";
            $whatsappLink = WhatsApp::shareLink($client['phone'], $message);
        }

        $this->view('user/invoices/show', [
            'pageTitle' => $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => $items,
            'client' => $client,
            'project' => $project,
            'company' => $company,
            'zatcaQr' => $this->zatcaQrDataUri($invoice, $company),
            'whatsappLink' => $whatsappLink,
            'whatsappApiConfigured' => WhatsApp::isConfigured(),
            'shareUrl' => $shareUrl,
        ], 'layouts/app');
    }

    public function sendWhatsApp(string $id): void
    {
        $this->verifyCsrf();
        $invoice = $this->findOwned((int) $id);
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $company = Company::find((int) $invoice['company_id']);

        if (!$client || empty($client['phone'])) {
            $this->flash('error', 'This invoice has no client phone number on file.');
            self::redirect('/app/invoices/' . $invoice['id']);
        }
        if (empty($invoice['share_token'])) {
            Invoice::update($invoice['id'], ['share_token' => bin2hex(random_bytes(20))]);
            $invoice['share_token'] = Invoice::find($invoice['id'])['share_token'];
        }
        $shareUrl = rtrim(Env::get('APP_URL', ''), '/') . '/i/' . $invoice['share_token'];

        $message = "Hi {$client['name']}, your invoice {$invoice['invoice_number']} from {$company['name']} for "
            . number_format((float) $invoice['total'], 2) . " SAR is ready: {$shareUrl}";
        $result = WhatsApp::sendMessage($client['phone'], $message);

        if (!empty($result['ok'])) {
            $this->flash('success', 'WhatsApp notification sent.');
        } else {
            $this->flash('error', 'Could not send WhatsApp notification: ' . ($result['error'] ?? json_encode($result['data'] ?? $result)));
        }
        self::redirect('/app/invoices/' . $invoice['id']);
    }

    /** Renders the ZATCA Phase 1 QR (SVG data URI) for an invoice, or null if the company has no VAT number set. */
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

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        $invoice = $this->findOwned((int) $id);
        $status = (string) $this->input('status', 'unpaid');
        if (in_array($status, ['unpaid', 'paid', 'overdue'], true)) {
            Invoice::update($invoice['id'], ['status' => $status]);
            $this->flash('success', 'Invoice status updated.');
        }
        self::redirect('/app/invoices/' . $invoice['id']);
    }

    public function releaseRetention(string $id): void
    {
        $this->verifyCsrf();
        $invoice = $this->findOwned((int) $id);
        if ((float) $invoice['retention_amount'] > 0 && !$invoice['retention_released']) {
            Invoice::update($invoice['id'], ['retention_released' => 1, 'retention_released_at' => date('Y-m-d H:i:s')]);
            $this->flash('success', 'Retention marked as released.');
        }
        self::redirect('/app/invoices/' . $invoice['id']);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $invoice = $this->findOwned((int) $id);
        Invoice::query('DELETE FROM invoice_items WHERE invoice_id = ?', [$invoice['id']]);
        Invoice::delete($invoice['id']);
        $this->flash('success', 'Invoice deleted.');
        self::redirect('/app/invoices');
    }

    public function pdf(string $id): void
    {
        $invoice = $this->findOwned((int) $id);
        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $company = Company::find((int) $invoice['company_id']);
        $template = in_array($this->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant'], true) ? $this->input('template') : 'modern';
        $lang = $this->input('lang') === 'ar' ? 'ar' : Lang::locale();

        $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'فاتورة' : 'Invoice',
            'docNumber' => $invoice['invoice_number'],
            'docDate' => $invoice['created_at'],
            'validUntil' => $invoice['due_date'],
            'status' => ucfirst($invoice['status']),
            'issuer' => ['name' => $company['name'] ?? '', 'meta' => array_filter([$company['phone'] ?? null, ($company['vat_number'] ?? null) ? 'VAT: ' . $company['vat_number'] : null])],
            'billTo' => $client ? ['name' => $client['name'], 'meta' => array_filter([$client['email'] ?? null, $client['phone'] ?? null, $client['address'] ?? null])] : null,
            'items' => array_map(fn($i) => ['description' => $i['description'], 'qty' => $i['qty'], 'unit_price' => $i['unit_price'], 'total' => $i['total']], $items),
            'subtotal' => (float) $invoice['total'] - (float) $invoice['vat_amount'],
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => $invoice['vat_rate'],
            'vatAmount' => (float) $invoice['vat_amount'],
            'total' => (float) $invoice['total'],
            'qrCode' => $this->zatcaQrDataUri($invoice, $company),
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة BuildXact Saudi' : 'Generated by BuildXact Saudi',
        ], $invoice['invoice_number'] . '.pdf');
    }

    public function xml(string $id): void
    {
        $invoice = $this->findOwned((int) $id);
        if (empty($invoice['zatca_uuid'])) {
            http_response_code(404);
            die('This invoice has no ZATCA chain data (it may predate ZATCA integration).');
        }
        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $company = Company::find((int) $invoice['company_id']);

        $xmlContent = UblInvoice::build(
            $invoice,
            $company,
            $client,
            $this->itemsForXml($items),
            (string) $invoice['zatca_uuid'],
            (int) $invoice['zatca_icv'],
            (string) $invoice['zatca_previous_hash']
        );

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $invoice['invoice_number'] . '.xml"');
        echo $xmlContent;
        exit;
    }

    public function submitZatca(string $id): void
    {
        $this->verifyCsrf();
        Feature::requireOrRedirect('zatca_phase2');
        $invoice = $this->findOwned((int) $id);
        $company = Company::find((int) $invoice['company_id']);

        if (($company['zatca_status'] ?? '') !== 'active') {
            $this->flash('error', "ZATCA Phase 2 isn't activated for your company yet. Ask your platform administrator to complete onboarding.");
            self::redirect('/app/invoices/' . $invoice['id']);
        }
        if (empty($invoice['zatca_uuid'])) {
            $this->flash('error', 'This invoice has no ZATCA chain data.');
            self::redirect('/app/invoices/' . $invoice['id']);
        }

        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $xmlContent = UblInvoice::build(
            $invoice,
            $company,
            $client,
            $this->itemsForXml($items),
            (string) $invoice['zatca_uuid'],
            (int) $invoice['zatca_icv'],
            (string) $invoice['zatca_previous_hash']
        );

        $apiClient = new ApiClient($company['zatca_environment'] ?: 'sandbox');
        $result = $apiClient->reportInvoice(
            base64_encode($xmlContent),
            (string) $invoice['zatca_uuid'],
            (string) $invoice['zatca_hash'],
            (string) $company['zatca_production_csid'],
            (string) $company['zatca_production_secret']
        );

        if (!empty($result['ok'])) {
            Invoice::update($invoice['id'], [
                'zatca_status' => 'reported',
                'zatca_submitted_at' => date('Y-m-d H:i:s'),
                'zatca_response' => json_encode($result['data'] ?? $result),
            ]);
            $this->flash('success', 'Invoice reported to ZATCA.');
        } else {
            $error = $result['error'] ?? json_encode($result['data'] ?? $result);
            Invoice::update($invoice['id'], [
                'zatca_status' => 'failed',
                'zatca_response' => (string) $error,
            ]);
            $this->flash('error', 'ZATCA rejected the invoice: ' . $error);
        }
        self::redirect('/app/invoices/' . $invoice['id']);
    }

    private function findOwned(int $id): array
    {
        $invoice = Invoice::find($id);
        if (!$invoice || (int) $invoice['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Invoice not found.');
        }
        return $invoice;
    }
}
