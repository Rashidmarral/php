<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Setting;
use App\Support\Sms;
use App\Support\WebhookDispatcher;
use App\Support\WhatsApp;
use App\Support\Zatca\QrGenerator;
use App\Support\Zatca\ZatcaSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
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

    public function store(Request $request, ZatcaSyncService $zatcaSync): RedirectResponse
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

        $client = $this->ownedClient($request->input('client_id') ?: null, $companyId);
        $project = $this->ownedProject($request->input('project_id') ?: null, $companyId);

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'project_id' => $project?->id,
            'client_id' => $client?->id,
            'invoice_number' => trim((string) $request->input('invoice_number')) ?: ('INV-' . (1000 + Invoice::where('company_id', $companyId)->count() + 1)),
            'status' => 'unpaid',
            'total' => $total,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'due_date' => $request->input('due_date') ?: null,
            'retention_percent' => $retentionPercent,
            'retention_amount' => $retentionAmount,
            'share_token' => bin2hex(random_bytes(20)),
            ...$this->approvalFieldsForNewInvoice($companyId),
        ]);

        foreach ($items as $item) {
            InvoiceItem::create(['invoice_id' => $invoice->id, ...$item]);
        }

        $company = Company::find($companyId);
        $this->chainZatca($invoice->fresh(), $company, $client, $items, $zatcaSync);
        WebhookDispatcher::dispatch($companyId, 'invoice.created', $invoice->fresh()->toArray());

        $this->flash('success', 'Invoice created.');
        return redirect('/app/invoices/' . $invoice->id);
    }

    /**
     * Populates the ZATCA UUID/ICV/hash-chain fields for a newly created
     * invoice, eagerly (see App\Support\Zatca\InvoiceChainer's docblock for
     * why). Delegates to that shared helper so App\Models\RecurringInvoice's
     * auto-generated invoices go through the exact same chain, on the same
     * company-wide sequence, as a hand-entered one from this controller.
     */
    private function chainZatca(Invoice $invoice, ?Company $company, ?Client $client, array $items, ZatcaSyncService $zatcaSync): void
    {
        \App\Support\Zatca\InvoiceChainer::chain($invoice, $company, $client, $items, $zatcaSync);
    }

    /**
     * When the company has opted into requiring internal approval for
     * invoices, a newly created one starts out pending instead of the
     * column's 'not_required' default — otherwise this returns [] and the
     * invoice behaves exactly as it did before this feature existed. Never
     * touches ZATCA chaining, which always runs via chainZatca() above.
     */
    private function approvalFieldsForNewInvoice(int $companyId): array
    {
        $company = Company::find($companyId);
        if (!$company || !$company->requiresInvoiceApproval()) {
            return [];
        }
        return [
            'approval_status' => 'pending',
            'approval_requested_by' => Auth::id(),
            'approval_requested_at' => now(),
        ];
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
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);
        $project = $this->ownedProject($invoice->project_id, $invoice->company_id);
        $company = Company::find($invoice->company_id);

        if (empty($invoice->share_token)) {
            $invoice->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/i/' . $invoice->share_token;
        $approvalBlocked = $invoice->isApprovalBlocked();

        $whatsappLink = null;
        if (!$approvalBlocked && $client && !empty($client->phone)) {
            $message = "Hi {$client->name}, your invoice {$invoice->invoice_number} from {$company->name} is ready: {$shareUrl}";
            $whatsappLink = WhatsApp::shareLink($client->phone, $message);
        }

        $reminderWhatsappLink = null;
        if (!$approvalBlocked && $client && !empty($client->phone) && $invoice->status !== 'paid') {
            $reminderWhatsappLink = WhatsApp::shareLink($client->phone, $this->paymentReminderMessage($invoice, $client, $company, $shareUrl));
        }

        // Only the person who actually requested this approval sees the nudge — anyone else
        // can already see the pending banner and its approve/reject buttons if they have that
        // ability, so a nudge button for them would be pointless.
        $approverWhatsappLink = null;
        if ($invoice->approval_status === 'pending' && (int) $invoice->approval_requested_by === (int) Auth::id() && !empty($company->phone)) {
            $approverWhatsappLink = WhatsApp::shareLink($company->phone, $this->approverPingMessage($invoice, $company));
        }

        $creditNotes = CreditNote::where('invoice_id', $invoice->id)->orderByDesc('id')->get()->toArray();
        $debitNotes = DebitNote::where('invoice_id', $invoice->id)->orderByDesc('id')->get()->toArray();

        return view('app.invoices.show', [
            'invoice' => $invoice->toArray(),
            'items' => $items,
            'client' => $client,
            'project' => $project,
            'company' => $company,
            'zatcaQr' => $this->zatcaQrDataUri($invoice, $company),
            'whatsappLink' => $whatsappLink,
            'reminderWhatsappLink' => $reminderWhatsappLink,
            'approverWhatsappLink' => $approverWhatsappLink,
            'isOverdue' => $invoice->isOverdue(),
            'whatsappApiConfigured' => WhatsApp::isConfigured(),
            'smsApiConfigured' => Sms::isConfigured(),
            'shareUrl' => $shareUrl,
            'approvalBlocked' => $approvalBlocked,
            'creditNotes' => $creditNotes,
            'debitNotes' => $debitNotes,
            'remainingCreditable' => $invoice->remainingCreditableTotal(),
        ]);
    }

    public function sendWhatsApp(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);
        $company = Company::find($invoice->company_id);

        if ($invoice->isApprovalBlocked()) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice is awaiting internal approval before it can be sent.');
        }
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

    /** Overdue/unpaid-toned nudge, distinct wording from show()'s "your invoice is ready" first-notice message above. */
    public function sendPaymentReminder(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);
        $company = Company::find($invoice->company_id);

        if ($invoice->isApprovalBlocked()) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice is awaiting internal approval before it can be sent.');
        }
        if ($invoice->status === 'paid') {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice is already paid.');
        }
        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice has no client phone number on file.');
        }
        if (empty($invoice->share_token)) {
            $invoice->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/i/' . $invoice->share_token;

        $result = WhatsApp::sendMessage($client->phone, $this->paymentReminderMessage($invoice, $client, $company, $shareUrl));

        if (!empty($result['ok'])) {
            $this->flash('success', 'Payment reminder sent via WhatsApp.');
        } else {
            $this->flash('error', 'Could not send WhatsApp reminder: ' . ($result['error'] ?? json_encode($result['data'] ?? $result)));
        }
        return redirect('/app/invoices/' . $invoice->id);
    }

    /** "Hi {client}, your invoice ... is ready" wording doesn't fit a document sitting unpaid — this is a firmer, overdue-toned message reused by both the wa.me link built in show() and the real API send above. */
    private function paymentReminderMessage(Invoice $invoice, Client $client, ?Company $company, string $shareUrl): string
    {
        $amount = number_format((float) $invoice->total, 2) . ' SAR';
        $companyName = $company->name ?? '';
        if ($invoice->isOverdue()) {
            $dueNote = $invoice->due_date ? " (due {$invoice->due_date->format('Y-m-d')})" : '';
            return "Hi {$client->name}, this is a reminder that invoice {$invoice->invoice_number} from {$companyName} for {$amount}{$dueNote} is now overdue. Please arrange payment at your earliest convenience: {$shareUrl}";
        }
        return "Hi {$client->name}, a friendly reminder that invoice {$invoice->invoice_number} from {$companyName} for {$amount} is still outstanding: {$shareUrl}";
    }

    /**
     * Pings whoever can approve pending documents at this company. There is no per-user phone
     * column anywhere in this app (see App\Support\Notifications's own docblock on
     * smsCompany() for the existing precedent) — every phone-based notification here already
     * falls back to the company's own contact number, Company::phone, rather than a specific
     * team member's. This reuses that exact same convention instead of inventing a new
     * per-approver phone field: the message is addressed to "whoever can approve" generically
     * and sent to the company's phone, which an owner/admin (the only roles the
     * 'approve_documents' Gate grants) is expected to see.
     */
    private function approverPingMessage(Invoice $invoice, ?Company $company): string
    {
        $amount = number_format((float) $invoice->total, 2) . ' SAR';
        $requesterName = Auth::user()->name;
        $link = rtrim((string) config('app.url'), '/') . '/app/invoices/' . $invoice->id;
        return "Hi, {$requesterName} is waiting on your approval for invoice {$invoice->invoice_number} ({$amount}) at " . ($company->name ?? '') . ". Please review: {$link}";
    }

    /** Lets the person who requested approval nudge their own approver — see approverPingMessage()'s docblock for how "the approver's phone" is determined. */
    public function notifyApprover(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        $company = Company::find($invoice->company_id);

        if ($invoice->approval_status !== 'pending') {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice is not awaiting approval.');
        }
        if ((int) $invoice->approval_requested_by !== (int) Auth::id()) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'Only the person who requested approval can send this reminder.');
        }
        if (empty($company->phone)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'Your company has no contact phone number on file to notify the approver.');
        }

        $result = WhatsApp::sendMessage($company->phone, $this->approverPingMessage($invoice, $company));

        if (!empty($result['ok'])) {
            $this->flash('success', 'Approval reminder sent via WhatsApp.');
        } else {
            $this->flash('error', 'Could not send WhatsApp reminder: ' . ($result['error'] ?? json_encode($result['data'] ?? $result)));
        }
        return redirect('/app/invoices/' . $invoice->id);
    }

    public function sendSms(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);
        $company = Company::find($invoice->company_id);

        if ($invoice->isApprovalBlocked()) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice is awaiting internal approval before it can be sent.');
        }
        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice has no client phone number on file.');
        }
        if (empty($invoice->share_token)) {
            $invoice->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = rtrim((string) config('app.url'), '/') . '/i/' . $invoice->share_token;

        $message = "Hi {$client->name}, your invoice {$invoice->invoice_number} from {$company->name} for "
            . number_format((float) $invoice->total, 2) . " SAR is ready: {$shareUrl}";
        $result = Sms::sendMessage($client->phone, $message);

        if (!empty($result['ok'])) {
            $this->flash('success', 'SMS notification sent.');
        } else {
            $this->flash('error', 'Could not send SMS notification: ' . ($result['error'] ?? json_encode($result['data'] ?? $result)));
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
            $wasPaid = $invoice->status === 'paid';
            $invoice->update(['status' => $status]);
            if ($status === 'paid' && !$wasPaid) {
                WebhookDispatcher::dispatch($invoice->company_id, 'invoice.paid', $invoice->fresh()->toArray());
            }
            $this->flash('success', 'Invoice status updated.');
        }
        return redirect('/app/invoices/' . $invoice->id);
    }

    /** Owner/admin sign-off that clears a pending invoice to reach the client. A non-pending invoice is left untouched. */
    public function approve(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        if ($invoice->approval_status !== 'pending') {
            $this->flash('error', 'This invoice is not awaiting approval.');
            return redirect('/app/invoices/' . $invoice->id);
        }
        $invoice->update(['approval_status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()]);
        $this->flash('success', 'Invoice approved — it can now be sent to the client.');
        return redirect('/app/invoices/' . $invoice->id);
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        if ($invoice->approval_status !== 'pending') {
            $this->flash('error', 'This invoice is not awaiting approval.');
            return redirect('/app/invoices/' . $invoice->id);
        }
        $invoice->update([
            'approval_status' => 'rejected',
            'rejection_reason' => trim((string) $request->input('reason', '')) ?: null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
        $this->flash('success', 'Invoice rejected.');
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
        if ($invoice->isZatcaLocked()) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice has been cleared/reported to ZATCA and is now part of an immutable tax record — it can no longer be deleted. Issue a Credit Note instead to correct it.');
        }
        InvoiceItem::where('invoice_id', $invoice->id)->delete();
        $invoice->delete();
        $this->flash('success', 'Invoice deleted.');
        return redirect('/app/invoices');
    }

    public function pdf(Request $request, int $id): Response
    {
        $invoice = $this->findOwned($id);
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);
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
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة ' . Setting::siteName() : 'Generated by ' . Setting::siteName(),
        ], $invoice->invoice_number . '.pdf');
    }

    public function xml(int $id, ZatcaSyncService $zatcaSync): Response|RedirectResponse
    {
        $invoice = $this->findOwned($id);
        if (empty($invoice->zatca_uuid)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice has no ZATCA chain data (it may predate ZATCA integration).');
        }
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);
        $company = Company::find($invoice->company_id);

        // The plain (unsigned) UBL XML — the same document whose XAdES
        // content hash is this invoice's zatca_hash. The fully *signed*
        // XML (with the XAdES UBLExtensions/QR actually embedded) only
        // exists once the company is ZATCA-onboarded; see submitZatca().
        [$xmlContent] = $zatcaSync->buildUnsignedXml(
            $invoice, $company, $client, $this->itemsForXml($items),
            (string) $invoice->zatca_uuid, (int) $invoice->zatca_icv, (string) $invoice->zatca_previous_hash
        );

        if ($company && $company->isZatcaOnboarded()) {
            [$xmlContent] = $zatcaSync->buildSignedPayload(
                $company, $xmlContent, (string) $invoice->zatca_hash, (string) $company->zatcaCsidFor(),
                $invoice->created_at ?? now(), (float) $invoice->total, (float) $invoice->vat_amount
            );
        }

        return response($xmlContent, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $invoice->invoice_number . '.xml"',
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
        $invoice = $this->findOwned($id);
        $company = Company::find($invoice->company_id);

        if (!$company || !$company->isZatcaOnboarded()) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', "ZATCA Phase 2 isn't activated for your company yet. Ask your platform administrator to complete onboarding.");
        }
        if (empty($invoice->zatca_uuid)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', 'This invoice has no ZATCA chain data.');
        }

        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);

        $result = $zatcaSync->submitInvoice($invoice, $company, $client, $this->itemsForXml($items));

        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : 'ZATCA rejected the invoice: ' . $result['message']);

        return redirect('/app/invoices/' . $invoice->id);
    }

    /** Renders the ZATCA Phase 1 QR (SVG data URI) for an invoice, or null if the company has no VAT number set. */
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

    private function findOwned(int $id): Invoice
    {
        $invoice = Invoice::find($id);
        abort_if(!$invoice || $invoice->company_id !== Auth::user()->company_id, 404, 'Invoice not found.');
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

    private function ownedProject(?int $id, int $companyId): ?Project
    {
        if (!$id) {
            return null;
        }
        $project = Project::find($id);
        return ($project && $project->company_id === $companyId) ? $project : null;
    }
}
