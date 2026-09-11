<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Support\Zatca\QrGenerator;
use App\Support\Zatca\ZatcaSyncService;
use App\Support\Zatca\ZatcaXmlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Credit Notes (UBL InvoiceTypeCode 381) — the only ZATCA-compliant way to
 * reduce what's owed on an already-cleared/reported invoice. Mirrors
 * InvoiceController's own structure/patterns throughout (chainZatca-style
 * eager hash-chaining at creation, the same findOwned()/ownedClient()
 * cross-tenant-safe lookup pattern, the same streamPdf()-based PDF path)
 * rather than introducing a different shape for what is, structurally, a
 * cut-down invoice.
 */
class CreditNoteController extends Controller
{
    public function index(): View
    {
        $creditNotes = DB::table('credit_notes as cn')
            ->leftJoin('invoices as i', 'i.id', '=', 'cn.invoice_id')
            ->leftJoin('clients as c', 'c.id', '=', 'cn.client_id')
            ->where('cn.company_id', Auth::user()->company_id)
            ->orderByDesc('cn.created_at')
            ->select('cn.*', 'i.invoice_number', 'c.name as client_name', 'c.name_ar as client_name_ar')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.credit-notes.index', ['creditNotes' => $creditNotes]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $companyId = Auth::user()->company_id;
        $invoiceId = (int) $request->query('invoice_id', 0);

        if (!$invoiceId) {
            $rows = DB::table('invoices as i')
                ->leftJoin('clients as c', 'c.id', '=', 'i.client_id')
                ->where('i.company_id', $companyId)
                ->orderByDesc('i.created_at')
                ->select('i.*', 'c.name as client_name', 'c.name_ar as client_name_ar')
                ->get();

            // Filtered/annotated here (via the Invoice model's own
            // remainingCreditableTotal(), not a raw SQL aggregate) so
            // "how much has already been credited" stays in exactly one
            // place — Invoice::creditedTotal().
            $eligible = $rows->map(function ($row) {
                $invoice = Invoice::find($row->id);
                $remaining = $invoice ? $invoice->remainingCreditableTotal() : 0.0;
                return $remaining > 0.01 ? array_merge((array) $row, ['remaining_creditable' => $remaining]) : null;
            })->filter()->values()->all();

            return view('app.credit-notes.select-invoice', ['invoices' => $eligible]);
        }

        $invoice = $this->ownedInvoice($invoiceId, $companyId);
        if ($invoice->remainingCreditableTotal() <= 0.01) {
            return $this->redirectWithFlash('/app/credit-notes/create', 'error', 'This invoice has no remaining creditable amount — it may already be fully credited.');
        }

        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get()->toArray();
        $client = $this->ownedClient($invoice->client_id, $companyId);

        return view('app.credit-notes.form', [
            'invoice' => $invoice->toArray(),
            'items' => $items,
            'client' => $client,
            'remainingCreditable' => $invoice->remainingCreditableTotal(),
            'nextNumber' => $this->nextNoteNumber($companyId),
        ]);
    }

    public function store(Request $request, ZatcaSyncService $zatcaSync): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $invoice = $this->ownedInvoice((int) $request->input('invoice_id'), $companyId);

        [$items, $subtotal] = $this->parseItems($request);
        if (empty($items)) {
            return $this->redirectWithFlash('/app/credit-notes/create?invoice_id=' . $invoice->id, 'error', 'Add at least one line item with a positive quantity.');
        }

        $vatRate = (float) $invoice->vat_rate;
        $vatAmount = round($subtotal * $vatRate / 100, 2);
        $total = $subtotal + $vatAmount;

        // Never trust a client-computed total for this check — it's
        // re-derived here from the submitted line items, against the
        // invoice's live remainingCreditableTotal() (which already
        // accounts for every other issued credit note against it).
        if ($total - $invoice->remainingCreditableTotal() > 0.01) {
            return $this->redirectWithFlash('/app/credit-notes/create?invoice_id=' . $invoice->id, 'error', 'This credit note (' . number_format($total, 2) . ' SAR) exceeds the amount remaining on the invoice (' . number_format($invoice->remainingCreditableTotal(), 2) . ' SAR).');
        }

        $client = $this->ownedClient($invoice->client_id, $companyId);
        $company = Company::find($companyId);

        $creditNote = DB::transaction(function () use ($companyId, $invoice, $items, $subtotal, $vatRate, $vatAmount, $total, $request) {
            $creditNote = CreditNote::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'note_number' => $this->nextNoteNumber($companyId),
                'issue_date' => $request->input('issue_date') ?: now()->toDateString(),
                'reason' => trim((string) $request->input('reason')) ?: null,
                'status' => 'issued',
                'subtotal' => $subtotal,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'total' => $total,
            ]);

            foreach ($items as $item) {
                CreditNoteItem::create(['credit_note_id' => $creditNote->id, ...$item]);
            }

            return $creditNote;
        });

        $this->chainZatcaCreditNote($creditNote->fresh(), $company, $invoice, $client, $items, $zatcaSync);

        $this->flash('success', 'Credit note issued.');
        return redirect('/app/credit-notes/' . $creditNote->id);
    }

    /**
     * Populates the ZATCA UUID/ICV/hash-chain fields for a newly created
     * credit note — eager chaining at creation, exactly like
     * InvoiceController::chainZatca(). A credit note is not a separate
     * chain: it continues the SAME company-wide ICV/PIH sequence as
     * regular invoices, so it reads/advances the identical
     * company.zatca_last_icv/zatca_last_invoice_hash columns invoices do.
     */
    private function chainZatcaCreditNote(CreditNote $creditNote, ?Company $company, Invoice $originalInvoice, ?Client $client, array $items, ZatcaSyncService $zatcaSync): void
    {
        if (!$company) {
            return;
        }
        $uuid = (new ZatcaXmlGenerator())->newUuid();
        $icv = (int) ($company->zatca_last_icv ?? 0) + 1;
        $previousHash = $company->zatca_last_invoice_hash ?: $zatcaSync->genesisHash();

        [, $hash] = $zatcaSync->buildUnsignedCreditNoteXml($creditNote, $company, $client, $items, $originalInvoice, $uuid, $icv, $previousHash);

        $creditNote->update([
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

    public function show(int $id): View
    {
        $creditNote = $this->findOwned($id);
        $items = CreditNoteItem::where('credit_note_id', $creditNote->id)->orderBy('id')->get()->toArray();
        $invoice = $this->ownedInvoice($creditNote->invoice_id, $creditNote->company_id);
        $client = $this->ownedClient($creditNote->client_id, $creditNote->company_id);

        return view('app.credit-notes.show', [
            'creditNote' => $creditNote->toArray(),
            'items' => $items,
            'invoice' => $invoice,
            'client' => $client,
        ]);
    }

    public function pdf(Request $request, int $id): Response
    {
        $creditNote = $this->findOwned($id);
        $items = CreditNoteItem::where('credit_note_id', $creditNote->id)->orderBy('id')->get();
        $invoice = $this->ownedInvoice($creditNote->invoice_id, $creditNote->company_id);
        $client = $this->ownedClient($creditNote->client_id, $creditNote->company_id);
        $company = Company::find($creditNote->company_id);
        $template = in_array($request->input('template'), ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $request->input('template') : 'modern';
        $lang = $request->input('lang') === 'ar' ? 'ar' : app()->getLocale();

        $reference = ($lang === 'ar' ? 'مرجع الفاتورة: ' : 'Reference invoice: ') . $invoice->invoice_number . ($creditNote->reason ? ' — ' . $creditNote->reason : '');

        return $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'إشعار دائن' : 'Credit Note',
            'docNumber' => $creditNote->note_number,
            'docDate' => $creditNote->created_at,
            'status' => ucfirst($creditNote->status),
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, ($company->vat_number ?? null) ? 'VAT: ' . $company->vat_number : null, ($company->cr_number ?? null) ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'billTo' => $client ? ['name' => ($lang === 'ar' && !empty($client->name_ar)) ? $client->name_ar : $client->name, 'meta' => array_filter([$client->email ?? null, $client->phone ?? null, $client->address ?? null])] : null,
            'items' => $items->map(fn ($i) => ['description' => ($lang === 'ar' && !empty($i->description_ar)) ? $i->description_ar : $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'total' => $i->total])->all(),
            'subtotal' => (float) $creditNote->subtotal,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => $creditNote->vat_rate,
            'vatAmount' => (float) $creditNote->vat_amount,
            'total' => (float) $creditNote->total,
            'notes' => $reference,
            'qrCode' => $this->zatcaQrDataUri($creditNote, $company),
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة ' . Setting::siteName() : 'Generated by ' . Setting::siteName(),
        ], $creditNote->note_number . '.pdf');
    }

    public function xml(int $id, ZatcaSyncService $zatcaSync): Response|RedirectResponse
    {
        $creditNote = $this->findOwned($id);
        if (empty($creditNote->zatca_uuid)) {
            return $this->redirectWithFlash('/app/credit-notes/' . $creditNote->id, 'error', 'This credit note has no ZATCA chain data.');
        }
        $items = CreditNoteItem::where('credit_note_id', $creditNote->id)->orderBy('id')->get();
        $invoice = $this->ownedInvoice($creditNote->invoice_id, $creditNote->company_id);
        $client = $this->ownedClient($creditNote->client_id, $creditNote->company_id);
        $company = Company::find($creditNote->company_id);

        [$xmlContent] = $zatcaSync->buildUnsignedCreditNoteXml(
            $creditNote, $company, $client, $this->itemsForXml($items), $invoice,
            (string) $creditNote->zatca_uuid, (int) $creditNote->zatca_icv, (string) $creditNote->zatca_previous_hash
        );

        if ($company && $company->isZatcaOnboarded()) {
            [$xmlContent] = $zatcaSync->buildSignedPayload(
                $company, $xmlContent, (string) $creditNote->zatca_hash, (string) $company->zatcaCsidFor(),
                $creditNote->created_at ?? now(), (float) $creditNote->total, (float) $creditNote->vat_amount
            );
        }

        return response($xmlContent, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $creditNote->note_number . '.xml"',
        ]);
    }

    public function submitZatca(int $id, ZatcaSyncService $zatcaSync): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('zatca_phase2')) {
            return $redirect;
        }
        $creditNote = $this->findOwned($id);
        $company = Company::find($creditNote->company_id);

        if (!$company || !$company->isZatcaOnboarded()) {
            return $this->redirectWithFlash('/app/credit-notes/' . $creditNote->id, 'error', "ZATCA Phase 2 isn't activated for your company yet. Ask your platform administrator to complete onboarding.");
        }
        if (empty($creditNote->zatca_uuid)) {
            return $this->redirectWithFlash('/app/credit-notes/' . $creditNote->id, 'error', 'This credit note has no ZATCA chain data.');
        }

        $items = CreditNoteItem::where('credit_note_id', $creditNote->id)->orderBy('id')->get();
        $invoice = $this->ownedInvoice($creditNote->invoice_id, $creditNote->company_id);
        $client = $this->ownedClient($creditNote->client_id, $creditNote->company_id);

        $result = $zatcaSync->submitCreditNote($creditNote, $company, $client, $this->itemsForXml($items), $invoice);

        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : 'ZATCA rejected the credit note: ' . $result['message']);

        return redirect('/app/credit-notes/' . $creditNote->id);
    }

    public function void(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $creditNote = $this->findOwned($id);
        if ($creditNote->status === 'void') {
            return redirect('/app/credit-notes/' . $creditNote->id);
        }
        if ($creditNote->isZatcaLocked()) {
            return $this->redirectWithFlash('/app/credit-notes/' . $creditNote->id, 'error', 'This credit note has been cleared/reported to ZATCA and is now part of an immutable tax record — it can no longer be voided.');
        }
        $creditNote->update(['status' => 'void']);
        $this->flash('success', 'Credit note voided.');
        return redirect('/app/credit-notes/' . $creditNote->id);
    }

    private function nextNoteNumber(int $companyId): string
    {
        return 'CN-' . (1000 + CreditNote::where('company_id', $companyId)->count() + 1);
    }

    /** @return array{0: array<int, array{description:string,description_ar:string,qty:float,unit_price:float,total:float}>, 1: float} */
    private function parseItems(Request $request): array
    {
        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);

        $subtotal = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            $qty = (float) ($qtys[$i] ?? 0);
            if ($desc === '' || $qty <= 0) {
                continue;
            }
            $price = (float) ($prices[$i] ?? 0);
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;
            $items[] = ['description' => $desc, 'description_ar' => trim((string) ($descriptionsAr[$i] ?? '')), 'qty' => $qty, 'unit_price' => $price, 'total' => $lineTotal];
        }

        return [$items, $subtotal];
    }

    /** @return array<int, array{description:string,qty:float,unit_price:float,total:float}> */
    private function itemsForXml(\Illuminate\Support\Collection $noteItems): array
    {
        return $noteItems->map(fn ($i) => [
            'description' => $i->description,
            'qty' => $i->qty,
            'unit_price' => $i->unit_price,
            'total' => $i->total,
        ])->all();
    }

    /** Renders the ZATCA Phase 1 QR (SVG data URI) for a credit note, or null if the company has no VAT number set. */
    private function zatcaQrDataUri(CreditNote $creditNote, ?Company $company): ?string
    {
        $vatNumber = trim((string) ($company->vat_number ?? ''));
        if ($vatNumber === '') {
            return null;
        }
        $payload = QrGenerator::payload(
            $company->name ?? '',
            $vatNumber,
            $creditNote->created_at->toAtomString(),
            number_format((float) $creditNote->total, 2, '.', ''),
            number_format((float) $creditNote->vat_amount, 2, '.', '')
        );
        return QrGenerator::renderSvgDataUri($payload);
    }

    private function findOwned(int $id): CreditNote
    {
        $creditNote = CreditNote::find($id);
        abort_if(!$creditNote || $creditNote->company_id !== Auth::user()->company_id, 404, 'Credit note not found.');
        return $creditNote;
    }

    /** Only returns the invoice if it belongs to $companyId — never let a note be issued against another company's invoice. */
    private function ownedInvoice(int $id, int $companyId): Invoice
    {
        $invoice = Invoice::find($id);
        abort_if(!$invoice || $invoice->company_id !== $companyId, 404, 'Invoice not found.');
        return $invoice;
    }

    /** Only returns the client if it belongs to $companyId — never leak another company's contact data via a foreign key. */
    private function ownedClient(?int $id, int $companyId): ?Client
    {
        if (!$id) {
            return null;
        }
        $client = Client::find($id);
        return ($client && $client->company_id === $companyId) ? $client : null;
    }
}
