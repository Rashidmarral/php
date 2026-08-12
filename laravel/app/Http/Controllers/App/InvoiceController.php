<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Setting;
use App\Support\WhatsApp;
use App\Support\Zatca\ApiClient;
use App\Support\Zatca\Phase1Qr;
use App\Support\Zatca\UblInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    private const ZATCA_GENESIS_HASH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function index(): View
    {
        $invoices = DB::table('invoices as i')
            ->leftJoin('clients as c', 'c.id', '=', 'i.client_id')
            ->where('i.company_id', Auth::user()->company_id)
            ->orderByDesc('i.created_at')
            ->select('i.*', 'c.name as client_name', 'c.name_ar as client_name_ar')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.invoices.index', ['invoices' => $invoices]);
    }

    public function create(): View
    {
        $companyId = Auth::user()->company_id;
        $nextNumber = 'INV-' . (1000 + Invoice::where('company_id', $companyId)->count() + 1);
        $materials = DB::table('materials as m')
            ->leftJoin('suppliers as s', 's.id', '=', 'm.supplier_id')
            ->where('m.company_id', $companyId)
            ->orderBy('m.category')->orderBy('m.name')
            ->select('m.*', 's.name as supplier_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
        $company = Company::find($companyId);

        return view('app.invoices.form', [
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'nextNumber' => $nextNumber,
            'vatRate' => (float) Setting::get('vat_rate', '15'),
            'materials' => $materials,
            'defaultRetentionPercent' => (float) ($company->default_retention_percent ?? 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);

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
            $items[] = ['description' => $desc, 'description_ar' => trim((string) ($descriptionsAr[$i] ?? '')), 'qty' => $qty, 'unit_price' => $price, 'total' => $lineTotal];
        }

        $applyVat = (bool) $request->input('apply_vat', true);
        $vatRate = $applyVat ? (float) Setting::get('vat_rate', '15') : 0;
        $vatAmount = $subtotal * $vatRate / 100;
        $total = $subtotal + $vatAmount;

        $retentionPercent = min(100, max(0, (float) $request->input('retention_percent', 0)));
        $retentionAmount = $subtotal * $retentionPercent / 100;

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'project_id' => $request->input('project_id') ?: null,
            'client_id' => $request->input('client_id') ?: null,
            'invoice_number' => trim((string) $request->input('invoice_number')) ?: ('INV-' . (1000 + Invoice::where('company_id', $companyId)->count() + 1)),
            'status' => 'unpaid',
            'total' => $total,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'due_date' => $request->input('due_date') ?: null,
            'retention_percent' => $retentionPercent,
            'retention_amount' => $retentionAmount,
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        foreach ($items as $item) {
            InvoiceItem::create(['invoice_id' => $invoice->id, ...$item]);
        }

        $company = Company::find($companyId);
        $client = $request->input('client_id') ? Client::find((int) $request->input('client_id')) : null;
        $this->chainZatca($invoice->fresh(), $company, $client, $items);

        $this->flash('success', 'Invoice created.');
        return redirect('/app/invoices/' . $invoice->id);
    }

    /** Populates the ZATCA UUID/ICV/hash-chain fields for a newly created invoice (Phase 2 groundwork). */
    private function chainZatca(Invoice $invoice, ?Company $company, ?Client $client, array $items): void
    {
        if (!$company) {
            return;
        }
        $uuid = self::uuidV4();
        $icv = (int) ($company->zatca_last_icv ?? 0) + 1;
        $previousHash = $company->zatca_last_invoice_hash ?: self::ZATCA_GENESIS_HASH;

        $xml = UblInvoice::build($invoice->toArray(), $company->toArray(), $client?->toArray(), $items, $uuid, $icv, $previousHash);
        $hash = UblInvoice::hash($xml);

        $invoice->update([
            'zatca_uuid' => $uuid,
            'zatca_icv' => $icv,
            'zatca_hash' => $hash,
            'zatca_previous_hash' => $previousHash,
        ]);
        $company->update([
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
    private function itemsForXml(\Illuminate\Support\Collection $invoiceItems): array
    {
        return $invoiceItems->map(fn ($i) => [
            'description' => $i->description,
            'qty' => $i->qty,
            'unit_price' => $i->unit_price,
            'total' => $i->total,
        ])->all();
    }

    public function show(int $id): View
    {
        $invoice = $this->findOwned($id);
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get()->toArray();
        $client = $invoice->client_id ? Client::find($invoice->client_id) : null;
        $project = $invoice->project_id ? Project::find($invoice->project_id) : null;
        $company = Company::find($invoice->company_id);

        if (empty($invoice->share_token)) {
            $invoice->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/i/' . $invoice->share_token;

        $whatsappLink = null;
        if ($client && !empty($client->phone)) {
            $message = "Hi {$client->name}, your invoice {$invoice->invoice_number} from {$company->name} is ready: {$shareUrl}";
            $whatsappLink = WhatsApp::shareLink($client->phone, $message);
        }

        return view('app.invoices.show', [
            'invoice' => $invoice->toArray(),
            'items' => $items,
            'client' => $client,
            'project' => $project,
            'company' => $company,
            'zatcaQr' => $this->zatcaQrDataUri($invoice, $company),
            'whatsappLink' => $whatsappLink,
            'whatsappApiConfigured' => WhatsApp::isConfigured(),
            'shareUrl' => $shareUrl,
        ]);
    }

    public function sendWhatsApp(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        $client = $invoice->client_id ? Client::find($invoice->client_id) : null;
        $company = Company::find($invoice->company_id);

        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice has no client phone number on file.');
        }
        if (empty($invoice->share_token)) {
            $invoice->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/i/' . $invoice->share_token;

        $message = "Hi {$client->name}, your invoice {$invoice->invoice_number} from {$company->name} for "
            . number_format((float) $invoice->total, 2) . " SAR is ready: {$shareUrl}";
        $result = WhatsApp::sendMessage($client->phone, $message);

        if (!empty($result['ok'])) {
            $this->flash('success', 'WhatsApp notification sent.');
        } else {
            $this->flash('error', 'Could not send WhatsApp notification: ' . ($result['error'] ?? json_encode($result['data'] ?? $result)));
        }
        return redirect('/app/invoices/' . $invoice->id);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        $status = (string) $request->input('status', 'unpaid');
        if (in_array($status, ['unpaid', 'paid', 'overdue'], true)) {
            $invoice->update(['status' => $status]);
            $this->flash('success', 'Invoice status updated.');
        }
        return redirect('/app/invoices/' . $invoice->id);
    }

    public function releaseRetention(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        if ((float) $invoice->retention_amount > 0 && !$invoice->retention_released) {
            $invoice->update(['retention_released' => true, 'retention_released_at' => now()]);
            $this->flash('success', 'Retention marked as released.');
        }
        return redirect('/app/invoices/' . $invoice->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        InvoiceItem::where('invoice_id', $invoice->id)->delete();
        $invoice->delete();
        $this->flash('success', 'Invoice deleted.');
        return redirect('/app/invoices');
    }

    public function pdf(Request $request, int $id): Response
    {
        $invoice = $this->findOwned($id);
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $invoice->client_id ? Client::find($invoice->client_id) : null;
        $company = Company::find($invoice->company_id);
        $template = in_array($request->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $request->input('template') : 'modern';
        $lang = $request->input('lang') === 'ar' ? 'ar' : app()->getLocale();

        return $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'فاتورة' : 'Invoice',
            'docNumber' => $invoice->invoice_number,
            'docDate' => $invoice->created_at,
            'validUntil' => $invoice->due_date,
            'status' => ucfirst($invoice->status),
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, ($company->vat_number ?? null) ? 'VAT: ' . $company->vat_number : null, ($company->cr_number ?? null) ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'billTo' => $client ? ['name' => ($lang === 'ar' && !empty($client->name_ar)) ? $client->name_ar : $client->name, 'meta' => array_filter([$client->email ?? null, $client->phone ?? null, $client->address ?? null])] : null,
            'items' => $items->map(fn ($i) => ['description' => ($lang === 'ar' && !empty($i->description_ar)) ? $i->description_ar : $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'total' => $i->total])->all(),
            'subtotal' => (float) $invoice->total - (float) $invoice->vat_amount,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => $invoice->vat_rate,
            'vatAmount' => (float) $invoice->vat_amount,
            'total' => (float) $invoice->total,
            'qrCode' => $this->zatcaQrDataUri($invoice, $company),
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة BuildXact Saudi' : 'Generated by BuildXact Saudi',
        ], $invoice->invoice_number . '.pdf');
    }

    public function xml(int $id): Response|RedirectResponse
    {
        $invoice = $this->findOwned($id);
        if (empty($invoice->zatca_uuid)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice has no ZATCA chain data (it may predate ZATCA integration).');
        }
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $invoice->client_id ? Client::find($invoice->client_id) : null;
        $company = Company::find($invoice->company_id);

        $xmlContent = UblInvoice::build(
            $invoice->toArray(),
            $company->toArray(),
            $client?->toArray(),
            $this->itemsForXml($items),
            (string) $invoice->zatca_uuid,
            (int) $invoice->zatca_icv,
            (string) $invoice->zatca_previous_hash
        );

        return response($xmlContent, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $invoice->invoice_number . '.xml"',
        ]);
    }

    public function submitZatca(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('zatca_phase2')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        $company = Company::find($invoice->company_id);

        if (($company->zatca_status ?? '') !== 'active') {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', "ZATCA Phase 2 isn't activated for your company yet. Ask your platform administrator to complete onboarding.");
        }
        if (empty($invoice->zatca_uuid)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice has no ZATCA chain data.');
        }

        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $invoice->client_id ? Client::find($invoice->client_id) : null;
        $xmlContent = UblInvoice::build(
            $invoice->toArray(),
            $company->toArray(),
            $client?->toArray(),
            $this->itemsForXml($items),
            (string) $invoice->zatca_uuid,
            (int) $invoice->zatca_icv,
            (string) $invoice->zatca_previous_hash
        );

        $apiClient = new ApiClient($company->zatca_environment ?: 'sandbox');
        $result = $apiClient->reportInvoice(
            base64_encode($xmlContent),
            (string) $invoice->zatca_uuid,
            (string) $invoice->zatca_hash,
            (string) $company->zatca_production_csid,
            (string) $company->zatca_production_secret
        );

        if (!empty($result['ok'])) {
            $invoice->update([
                'zatca_status' => 'reported',
                'zatca_submitted_at' => now(),
                'zatca_response' => json_encode($result['data'] ?? $result),
            ]);
            $this->flash('success', 'Invoice reported to ZATCA.');
        } else {
            $error = $result['error'] ?? json_encode($result['data'] ?? $result);
            $invoice->update([
                'zatca_status' => 'failed',
                'zatca_response' => (string) $error,
            ]);
            $this->flash('error', 'ZATCA rejected the invoice: ' . $error);
        }
        return redirect('/app/invoices/' . $invoice->id);
    }

    /** Renders the ZATCA Phase 1 QR (SVG data URI) for an invoice, or null if the company has no VAT number set. */
    private function zatcaQrDataUri(Invoice $invoice, ?Company $company): ?string
    {
        $vatNumber = trim((string) ($company->vat_number ?? ''));
        if ($vatNumber === '') {
            return null;
        }
        $payload = Phase1Qr::payload(
            $company->name ?? '',
            $vatNumber,
            $invoice->created_at->toAtomString(),
            number_format((float) $invoice->total, 2, '.', ''),
            number_format((float) $invoice->vat_amount, 2, '.', '')
        );
        return Phase1Qr::renderSvgDataUri($payload);
    }

    private function findOwned(int $id): Invoice
    {
        $invoice = Invoice::find($id);
        abort_if(!$invoice || $invoice->company_id !== Auth::user()->company_id, 404, 'Invoice not found.');
        return $invoice;
    }
}
