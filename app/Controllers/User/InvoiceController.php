<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Settings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;

class InvoiceController extends Controller
{
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
        $this->view('user/invoices/form', [
            'pageTitle' => 'New Invoice',
            'clients' => $clients,
            'projects' => $projects,
            'nextNumber' => $nextNumber,
            'vatRate' => (float) Settings::get('vat_rate', 15),
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
        ]);

        foreach ($items as $item) {
            InvoiceItem::create(['invoice_id' => $invoiceId, ...$item]);
        }

        $this->flash('success', 'Invoice created.');
        self::redirect('/app/invoices/' . $invoiceId);
    }

    public function show(string $id): void
    {
        $invoice = $this->findOwned((int) $id);
        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $project = $invoice['project_id'] ? Project::find((int) $invoice['project_id']) : null;

        $this->view('user/invoices/show', [
            'pageTitle' => $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => $items,
            'client' => $client,
            'project' => $project,
        ], 'layouts/app');
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
        $template = in_array($this->input('template'), ['modern', 'classic', 'minimal'], true) ? $this->input('template') : 'modern';
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
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة BuildXact Saudi' : 'Generated by BuildXact Saudi',
        ], $invoice['invoice_number'] . '.pdf');
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
