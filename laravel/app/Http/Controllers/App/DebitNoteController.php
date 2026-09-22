<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
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
 * Debit Notes (UBL InvoiceTypeCode 383) — the mirror image of a Credit
 * Note: raises what's owed on top of an already-cleared/reported invoice
 * rather than reducing it (see App\Models\DebitNote's docblock). Structure
 * mirrors CreditNoteController's throughout, with one deliberate
 * difference: there is no "remaining amount" cap to enforce here — any
 * invoice can receive a debit note, since it only ever adds a charge.
 */
class DebitNoteController extends Controller
{
    public function index(): View
    {
        $debitNotes = DB::table('debit_notes as dn')
            ->leftJoin('invoices as i', 'i.id', '=', 'dn.invoice_id')
            ->leftJoin('clients as c', 'c.id', '=', 'dn.client_id')
            ->where('dn.company_id', Auth::user()->company_id)
            ->orderByDesc('dn.created_at')
            ->select('dn.*', 'i.invoice_number', 'c.name as client_name', 'c.name_ar as client_name_ar')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.debit-notes.index', ['debitNotes' => $debitNotes]);
    }

    public function create(Request $request): View
    {
        $companyId = Auth::user()->company_id;
        $invoiceId = (int) $request->query('invoice_id', 0);

        if (!$invoiceId) {
            $invoices = DB::table('invoices as i')
                ->leftJoin('clients as c', 'c.id', '=', 'i.client_id')
                ->where('i.company_id', $companyId)
                ->orderByDesc('i.created_at')
                ->select('i.*', 'c.name as client_name', 'c.name_ar as client_name_ar')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            return view('app.debit-notes.select-invoice', ['invoices' => $invoices]);
        }

        $invoice = $this->ownedInvoice($invoiceId, $companyId);
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get()->toArray();
        $client = $this->ownedClient($invoice->client_id, $companyId);

        return view('app.debit-notes.form', [
            'invoice' => $invoice->toArray(),
            'items' => $items,
            'client' => $client,
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
            return $this->redirectWithFlash('/app/debit-notes/create?invoice_id=' . $invoice->id, 'error', t('common.line_item_required'));
        }

        $vatRate = (float) $invoice->vat_rate;
        $vatAmount = round($subtotal * $vatRate / 100, 2);
        $total = $subtotal + $vatAmount;

        $client = $this->ownedClient($invoice->client_id, $companyId);
        $company = Company::find($companyId);

        $debitNote = DB::transaction(function () use ($companyId, $invoice, $items, $subtotal, $vatRate, $vatAmount, $total, $request) {
            $debitNote = DebitNote::create([
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
                DebitNoteItem::create(['debit_note_id' => $debitNote->id, ...$item]);
            }

            return $debitNote;
        });

        $this->chainZatcaDebitNote($debitNote->fresh(), $company, $invoice, $client, $items, $zatcaSync);

        $this->flash('success', t('user.debit_notes.issued'));
        return redirect('/app/debit-notes/' . $debitNote->id);
    }

    /**
     * Same eager hash-chaining shape as
     * CreditNoteController::chainZatcaCreditNote() — a debit note also
     * continues the SAME company-wide ICV/PIH sequence as invoices and
     * credit notes, not a separate one.
     */
    private function chainZatcaDebitNote(DebitNote $debitNote, ?Company $company, Invoice $originalInvoice, ?Client $client, array $items, ZatcaSyncService $zatcaSync): void
    {
        if (!$company) {
            return;
        }
        $uuid = (new ZatcaXmlGenerator())->newUuid();
        $icv = (int) ($company->zatca_last_icv ?? 0) + 1;
        $previousHash = $company->zatca_last_invoice_hash ?: $zatcaSync->genesisHash();

        [, $hash] = $zatcaSync->buildUnsignedDebitNoteXml($debitNote, $company, $client, $items, $originalInvoice, $uuid, $icv, $previousHash);

        $debitNote->update([
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
        $debitNote = $this->findOwned($id);
        $items = DebitNoteItem::where('debit_note_id', $debitNote->id)->orderBy('id')->get()->toArray();
        $invoice = $this->ownedInvoice($debitNote->invoice_id, $debitNote->company_id);
        $client = $this->ownedClient($debitNote->client_id, $debitNote->company_id);
        $company = Company::find($debitNote->company_id);

        return view('app.debit-notes.show', [
            'debitNote' => $debitNote->toArray(),
            'items' => $items,
            'invoice' => $invoice,
            'client' => $client,
            'activeTemplate' => $company->activeInvoiceTemplate(),
            // Stage 4 fix: see InvoiceController::show()'s identical comment —
            // hides the OLD template picker once it would be a no-op.
            'hasCustomTemplate' => (bool) $company->activeInvoiceTemplateFor('debit_note'),
        ]);
    }

    public function pdf(Request $request, int $id): Response
    {
        $debitNote = $this->findOwned($id);
        $items = DebitNoteItem::where('debit_note_id', $debitNote->id)->orderBy('id')->get();
        $invoice = $this->ownedInvoice($debitNote->invoice_id, $debitNote->company_id);
        $client = $this->ownedClient($debitNote->client_id, $debitNote->company_id);
        $company = Company::find($debitNote->company_id);
        $template = in_array($request->input('template'), Company::INVOICE_TEMPLATES, true) ? $request->input('template') : $company->activeInvoiceTemplate();
        $lang = $request->input('lang') === 'ar' ? 'ar' : app()->getLocale();
        $invoiceTemplate = $company->activeInvoiceTemplateFor('debit_note');

        $reference = ($lang === 'ar' ? 'مرجع الفاتورة: ' : 'Reference invoice: ') . $invoice->invoice_number . ($debitNote->reason ? ' — ' . $debitNote->reason : '');

        return $this->streamPdf([
            'template' => $template,
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'إشعار مدين' : 'Debit Note',
            'docNumber' => $debitNote->note_number,
            'docDate' => $debitNote->created_at,
            'status' => ucfirst($debitNote->status),
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, ($company->vat_number ?? null) ? 'VAT: ' . $company->vat_number : null, ($company->cr_number ?? null) ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'billTo' => $client ? ['name' => ($lang === 'ar' && !empty($client->name_ar)) ? $client->name_ar : $client->name, 'meta' => array_filter([$client->email ?? null, $client->phone ?? null, $client->address ?? null])] : null,
            'items' => $items->map(fn ($i) => ['description' => ($lang === 'ar' && !empty($i->description_ar)) ? $i->description_ar : $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'total' => $i->total])->all(),
            'subtotal' => (float) $debitNote->subtotal,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'vatRate' => $debitNote->vat_rate,
            'vatAmount' => (float) $debitNote->vat_amount,
            'total' => (float) $debitNote->total,
            'notes' => $reference,
            'qrCode' => $this->zatcaQrDataUri($debitNote, $company),
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة ' . Setting::siteName() : 'Generated by ' . Setting::siteName(),
            'invoiceTemplate' => $invoiceTemplate,
            'company' => $company,
            'documentType' => 'debit_note',
            'pageSize' => $invoiceTemplate ? ($invoiceTemplate->page_size ?: 'a4') : 'a4',
            'partyLabelKey' => 'pdf.bill_to',
            'partyNameAr' => $client?->name_ar,
            'partyVat' => $client?->vat_number,
            'zatcaStatus' => in_array($debitNote->zatca_status, ['cleared', 'reported'], true) ? $debitNote->zatca_status : null,
        ], $debitNote->note_number . '.pdf');
    }

    public function xml(int $id, ZatcaSyncService $zatcaSync): Response|RedirectResponse
    {
        $debitNote = $this->findOwned($id);
        if (empty($debitNote->zatca_uuid)) {
            return $this->redirectWithFlash('/app/debit-notes/' . $debitNote->id, 'error', t('user.debit_notes.no_zatca_chain_data'));
        }
        $items = DebitNoteItem::where('debit_note_id', $debitNote->id)->orderBy('id')->get();
        $invoice = $this->ownedInvoice($debitNote->invoice_id, $debitNote->company_id);
        $client = $this->ownedClient($debitNote->client_id, $debitNote->company_id);
        $company = Company::find($debitNote->company_id);

        [$xmlContent] = $zatcaSync->buildUnsignedDebitNoteXml(
            $debitNote, $company, $client, $this->itemsForXml($items), $invoice,
            (string) $debitNote->zatca_uuid, (int) $debitNote->zatca_icv, (string) $debitNote->zatca_previous_hash
        );

        if ($company && $company->isZatcaOnboarded()) {
            [$xmlContent] = $zatcaSync->buildSignedPayload(
                $company, $xmlContent, (string) $debitNote->zatca_hash, (string) $company->zatcaCsidFor(),
                $debitNote->created_at ?? now(), (float) $debitNote->total, (float) $debitNote->vat_amount
            );
        }

        return response($xmlContent, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $debitNote->note_number . '.xml"',
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
        $debitNote = $this->findOwned($id);
        $company = Company::find($debitNote->company_id);

        if (!$company || !$company->isZatcaOnboarded()) {
            return $this->redirectWithFlash('/app/debit-notes/' . $debitNote->id, 'error', t('common.zatca_phase2_not_activated'));
        }
        if (empty($debitNote->zatca_uuid)) {
            return $this->redirectWithFlash('/app/debit-notes/' . $debitNote->id, 'error', t('user.debit_notes.no_zatca_chain_data'));
        }

        $items = DebitNoteItem::where('debit_note_id', $debitNote->id)->orderBy('id')->get();
        $invoice = $this->ownedInvoice($debitNote->invoice_id, $debitNote->company_id);
        $client = $this->ownedClient($debitNote->client_id, $debitNote->company_id);

        $result = $zatcaSync->submitDebitNote($debitNote, $company, $client, $this->itemsForXml($items), $invoice);

        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : t('user.debit_notes.zatca_rejected', ['reason' => $result['message']]));

        return redirect('/app/debit-notes/' . $debitNote->id);
    }

    public function void(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $debitNote = $this->findOwned($id);
        if ($debitNote->status === 'void') {
            return redirect('/app/debit-notes/' . $debitNote->id);
        }
        if ($debitNote->isZatcaLocked()) {
            return $this->redirectWithFlash('/app/debit-notes/' . $debitNote->id, 'error', t('user.debit_notes.zatca_locked_cannot_void'));
        }
        $debitNote->update(['status' => 'void']);
        $this->flash('success', t('user.debit_notes.voided'));
        return redirect('/app/debit-notes/' . $debitNote->id);
    }

    private function nextNoteNumber(int $companyId): string
    {
        return 'DN-' . (1000 + DebitNote::where('company_id', $companyId)->count() + 1);
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

    /** Renders the ZATCA Phase 1 QR (SVG data URI) for a debit note, or null if the company has no VAT number set. */
    private function zatcaQrDataUri(DebitNote $debitNote, ?Company $company): ?string
    {
        $vatNumber = trim((string) ($company->vat_number ?? ''));
        if ($vatNumber === '') {
            return null;
        }
        $payload = QrGenerator::payload(
            $company->name ?? '',
            $vatNumber,
            $debitNote->created_at->toAtomString(),
            number_format((float) $debitNote->total, 2, '.', ''),
            number_format((float) $debitNote->vat_amount, 2, '.', '')
        );
        return QrGenerator::renderSvgDataUri($payload);
    }

    private function findOwned(int $id): DebitNote
    {
        $debitNote = DebitNote::find($id);
        abort_if(!$debitNote || $debitNote->company_id !== Auth::user()->company_id, 404, 'Debit note not found.');
        return $debitNote;
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
