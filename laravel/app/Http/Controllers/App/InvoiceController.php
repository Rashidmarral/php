<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Setting;
use App\Support\ApprovalChain;
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
            'paymentMethods' => Invoice::PAYMENT_METHODS,
        ]);
    }

    public function store(Request $request, ZatcaSyncService $zatcaSync): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $invoiceNumber = trim((string) $request->input('invoice_number'));
        if ($invoiceNumber !== '' && mb_strlen($invoiceNumber) > 30) {
            return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.invoice_number_invalid'));
        }

        $dueDateRaw = trim((string) $request->input('due_date', ''));
        if (!$this->isValidOptionalDate($dueDateRaw)) {
            return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.due_date_invalid'));
        }
        $dateOfSupplyRaw = trim((string) $request->input('date_of_supply', ''));
        if (!$this->isValidOptionalDate($dateOfSupplyRaw)) {
            return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.date_of_supply_invalid'));
        }

        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $itemCodes = $request->input('item_code', []);
        $units = $request->input('item_unit', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);
        $lineDiscounts = $request->input('item_discount', []);

        $subtotal = 0;
        $lineDiscountsTotal = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = mb_substr(trim((string) $desc), 0, 255);
            if ($desc === '') {
                continue;
            }
            $qty = $this->validatedNumber($qtys[$i] ?? 1, 0.01, 99999999.99);
            if ($qty === null) {
                return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.qty_invalid'));
            }
            $price = $this->validatedNumberOrDefault($prices[$i] ?? 0, 0, 99999999.99, 0.0);
            if ($price === null) {
                return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.unit_price_invalid'));
            }
            $lineDiscount = $this->validatedNumberOrDefault($lineDiscounts[$i] ?? 0, 0, 9999999999.99, 0.0);
            if ($lineDiscount === null) {
                return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.line_discount_invalid'));
            }
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;
            $lineDiscountsTotal += $lineDiscount;
            $items[] = [
                'description' => $desc,
                'description_ar' => mb_substr(trim((string) ($descriptionsAr[$i] ?? '')), 0, 255),
                'item_code' => mb_substr(trim((string) ($itemCodes[$i] ?? '')), 0, 50) ?: null,
                'unit' => mb_substr(trim((string) ($units[$i] ?? '')), 0, 20) ?: null,
                'qty' => $qty,
                'unit_price' => $price,
                'discount_amount' => $lineDiscount,
                'total' => $lineTotal,
                // Always Standard-rated — this app has deliberately not built
                // multi-rate/zero-rated/exempt VAT support (see the
                // invoice_items migration's docblock), and no form exposes a
                // picker for this column.
                'tax_code' => 'S',
            ];
        }

        $applyVat = (bool) $request->input('apply_vat', true);
        $vatRate = $applyVat ? (float) Setting::get('vat_rate', '15') : 0;

        // Header discount/other charges, on top of each line's own discount
        // above — see InvoiceController::pdf()'s docblock-level comment for
        // why the taxable base (and therefore vat_amount/total) subtracts
        // ALL discounts (line + header) and adds other charges, while
        // `discount_amount` on the invoices table itself stores only the
        // header figure a user actually typed (per-line discounts live on
        // their own invoice_items row).
        $headerDiscount = $this->validatedNumberOrDefault($request->input('discount_amount', 0), 0, 9999999999.99, 0.0);
        if ($headerDiscount === null) {
            return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.discount_invalid'));
        }
        $otherCharges = $this->validatedNumberOrDefault($request->input('other_charges', 0), 0, 9999999999.99, 0.0);
        if ($otherCharges === null) {
            return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.other_charges_invalid'));
        }
        $taxableAmount = max(0, $subtotal - $lineDiscountsTotal - $headerDiscount) + $otherCharges;
        $vatAmount = $taxableAmount * $vatRate / 100;
        $total = $taxableAmount + $vatAmount;

        $retentionPercent = $this->validatedNumberOrDefault($request->input('retention_percent', 0), 0, 100, 0.0);
        if ($retentionPercent === null) {
            return $this->redirectWithFlash('/app/invoices/create', 'error', t('user.invoices.retention_percent_invalid'));
        }
        $retentionAmount = $subtotal * $retentionPercent / 100;

        $client = $this->ownedClient($request->input('client_id') ?: null, $companyId);
        $project = $this->ownedProject($request->input('project_id') ?: null, $companyId);

        $paymentMethod = $request->input('payment_method');
        $paymentMethod = in_array($paymentMethod, Invoice::PAYMENT_METHODS, true) ? $paymentMethod : null;
        // ZATCA's "Date of Supply" is never left unset on a newly created
        // invoice — a blank form field defaults to today, decided here
        // server-side, rather than only faking a default in the form's HTML
        // (which would silently diverge from what's actually saved).
        $dateOfSupply = $dateOfSupplyRaw !== '' ? $dateOfSupplyRaw : now()->toDateString();

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'project_id' => $project?->id,
            'client_id' => $client?->id,
            'invoice_number' => $invoiceNumber ?: ('INV-' . (1000 + Invoice::where('company_id', $companyId)->count() + 1)),
            'status' => 'unpaid',
            'payment_method' => $paymentMethod,
            'total' => $total,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'discount_amount' => $headerDiscount,
            'other_charges' => $otherCharges,
            'due_date' => $dueDateRaw !== '' ? $dueDateRaw : null,
            'date_of_supply' => $dateOfSupply,
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
        // Queued, asynchronous ZATCA submission — fires in the background
        // for a company that's already onboarded; a no-op otherwise. The
        // explicit "Submit to ZATCA" button (submitZatca() below) remains
        // the synchronous manual retry/"Sync now" action — see
        // App\Jobs\SyncInvoiceToZatca's docblock.
        \App\Jobs\SyncInvoiceToZatca::dispatch($invoice->id);
        WebhookDispatcher::dispatch($companyId, 'invoice.created', $invoice->fresh()->toArray());
        AuditLog::recordForCompany(Auth::user(), 'invoice_create', 'invoice', $invoice->id, "Invoice {$invoice->invoice_number} created — " . number_format((float) $invoice->total, 2) . ' SAR');

        $this->flash('success', t('user.invoices.created'));
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
        $shareUrl = url('/i/' . $invoice->share_token);
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

        // Null when this invoice has no configured multi-step chain — the view falls
        // back to the original flat "awaiting approval" badge in that (common) case.
        $approvalChain = ApprovalChain::progressFor($company, 'invoice', $invoice->id);
        $canActOnApproval = $approvalChain
            ? ApprovalChain::canActOnCurrentStep(Auth::user(), 'invoice', $invoice->id)
            : Auth::user()->can('approve_documents');

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
            'approvalChain' => $approvalChain,
            'canActOnApproval' => $canActOnApproval,
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
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.awaiting_approval_cannot_send'));
        }
        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.no_client_phone'));
        }
        if (empty($invoice->share_token)) {
            $invoice->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = url('/i/' . $invoice->share_token);

        $message = "Hi {$client->name}, your invoice {$invoice->invoice_number} from {$company->name} for "
            . number_format((float) $invoice->total, 2) . " SAR is ready: {$shareUrl}";
        $result = WhatsApp::sendMessage($client->phone, $message);

        if (!empty($result['ok'])) {
            AuditLog::recordForCompany(Auth::user(), 'invoice_sent', 'invoice', $invoice->id, "Invoice {$invoice->invoice_number} sent to client via WhatsApp");
            $this->flash('success', t('common.whatsapp_notification_sent'));
        } else {
            $this->flash('error', t('common.whatsapp_notification_failed', ['error' => $result['error'] ?? json_encode($result['data'] ?? $result)]));
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
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.awaiting_approval_cannot_send'));
        }
        if ($invoice->status === 'paid') {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.already_paid'));
        }
        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.no_client_phone'));
        }
        if (empty($invoice->share_token)) {
            $invoice->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = url('/i/' . $invoice->share_token);

        $result = WhatsApp::sendMessage($client->phone, $this->paymentReminderMessage($invoice, $client, $company, $shareUrl));

        if (!empty($result['ok'])) {
            $this->flash('success', t('user.invoices.whatsapp_payment_reminder_sent'));
        } else {
            $this->flash('error', t('common.whatsapp_send_failed', ['error' => $result['error'] ?? json_encode($result['data'] ?? $result)]));
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
        $link = url('/app/invoices/' . $invoice->id);
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
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.not_awaiting_approval'));
        }
        if ((int) $invoice->approval_requested_by !== (int) Auth::id()) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.only_requester_can_remind'));
        }
        if (empty($company->phone)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.no_company_phone'));
        }

        $result = WhatsApp::sendMessage($company->phone, $this->approverPingMessage($invoice, $company));

        if (!empty($result['ok'])) {
            $this->flash('success', t('common.whatsapp_approval_sent'));
        } else {
            $this->flash('error', t('common.whatsapp_send_failed', ['error' => $result['error'] ?? json_encode($result['data'] ?? $result)]));
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
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.awaiting_approval_cannot_send'));
        }
        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.no_client_phone'));
        }
        if (empty($invoice->share_token)) {
            $invoice->update(['share_token' => bin2hex(random_bytes(20))]);
        }
        $shareUrl = url('/i/' . $invoice->share_token);

        $message = "Hi {$client->name}, your invoice {$invoice->invoice_number} from {$company->name} for "
            . number_format((float) $invoice->total, 2) . " SAR is ready: {$shareUrl}";
        $result = Sms::sendMessage($client->phone, $message);

        if (!empty($result['ok'])) {
            $this->flash('success', t('common.sms_notification_sent'));
        } else {
            $this->flash('error', t('common.sms_send_failed', ['error' => $result['error'] ?? json_encode($result['data'] ?? $result)]));
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
            $amount = number_format((float) $invoice->total, 2) . ' SAR';
            $logMessage = $status === 'paid'
                ? "Invoice #{$invoice->invoice_number} marked paid — {$amount}"
                : "Invoice #{$invoice->invoice_number} → {$status} — {$amount}";
            AuditLog::recordForCompany(Auth::user(), 'invoice_status_change', 'invoice', $invoice->id, $logMessage);
            $this->flash('success', t('user.invoices.status_updated'));
        }
        return redirect('/app/invoices/' . $invoice->id);
    }

    /**
     * Owner/admin sign-off that clears a pending invoice to reach the client — OR,
     * when the company has a configured multi-step approval chain for invoices
     * (ApprovalChain::hasChain()), sign-off from whoever holds the role required
     * for the CURRENT pending step instead of the generic 'approve_documents' Gate.
     * A non-pending invoice is left untouched either way.
     *
     * Same hasChain()-first dispatch as EstimateController::approve() — see that
     * method's docblock for why a company with no chain configured takes the
     * exact same path as before this feature existed.
     */
    public function approve(int $id): RedirectResponse
    {
        if (ApprovalChain::hasChain('invoice', $id)) {
            $invoice = $this->findOwned($id);
            if ($invoice->approval_status !== 'pending') {
                $this->flash('error', t('user.invoices.not_awaiting_approval'));
                return redirect('/app/invoices/' . $invoice->id);
            }
            if (!ApprovalChain::canActOnCurrentStep(Auth::user(), 'invoice', $invoice->id)) {
                $this->flash('error', t('user.invoices.not_your_approval_step'));
                return redirect('/app/invoices/' . $invoice->id);
            }
            if (ApprovalChain::approveCurrentStep(Auth::user(), 'invoice', $invoice->id) === 'done') {
                $invoice->update(['approval_status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()]);
                AuditLog::recordForCompany(Auth::user(), 'invoice_approved', 'invoice', $invoice->id, "Invoice {$invoice->invoice_number} approved");
            }
            $this->flash('success', t('user.invoices.approved'));
            return redirect('/app/invoices/' . $invoice->id);
        }

        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        if ($invoice->approval_status !== 'pending') {
            $this->flash('error', t('user.invoices.not_awaiting_approval'));
            return redirect('/app/invoices/' . $invoice->id);
        }
        $invoice->update(['approval_status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()]);
        AuditLog::recordForCompany(Auth::user(), 'invoice_approved', 'invoice', $invoice->id, "Invoice {$invoice->invoice_number} approved");
        $this->flash('success', t('user.invoices.approved'));
        return redirect('/app/invoices/' . $invoice->id);
    }

    /** Mirrors approve() above: chain-aware rejection when a chain is configured, otherwise the exact original single-step flow. Rejecting at ANY step kills the whole document immediately (see ApprovalChain::rejectCurrentStep()). */
    public function reject(Request $request, int $id): RedirectResponse
    {
        if (ApprovalChain::hasChain('invoice', $id)) {
            $invoice = $this->findOwned($id);
            if ($invoice->approval_status !== 'pending') {
                $this->flash('error', t('user.invoices.not_awaiting_approval'));
                return redirect('/app/invoices/' . $invoice->id);
            }
            if (!ApprovalChain::canActOnCurrentStep(Auth::user(), 'invoice', $invoice->id)) {
                $this->flash('error', t('user.invoices.not_your_approval_step'));
                return redirect('/app/invoices/' . $invoice->id);
            }
            $reason = trim((string) $request->input('reason', '')) ?: null;
            ApprovalChain::rejectCurrentStep(Auth::user(), 'invoice', $invoice->id, $reason);
            $invoice->update([
                'approval_status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            AuditLog::recordForCompany(Auth::user(), 'invoice_rejected', 'invoice', $invoice->id, "Invoice {$invoice->invoice_number} rejected" . ($reason ? " — {$reason}" : ''));
            $this->flash('success', t('user.invoices.rejected'));
            return redirect('/app/invoices/' . $invoice->id);
        }

        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $invoice = $this->findOwned($id);
        if ($invoice->approval_status !== 'pending') {
            $this->flash('error', t('user.invoices.not_awaiting_approval'));
            return redirect('/app/invoices/' . $invoice->id);
        }
        $reason = trim((string) $request->input('reason', '')) ?: null;
        $invoice->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => null,
            'approved_at' => null,
        ]);
        AuditLog::recordForCompany(Auth::user(), 'invoice_rejected', 'invoice', $invoice->id, "Invoice {$invoice->invoice_number} rejected" . ($reason ? " — {$reason}" : ''));
        $this->flash('success', t('user.invoices.rejected'));
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
            $this->flash('success', t('user.invoices.retention_released'));
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
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.zatca_locked_cannot_delete'));
        }
        InvoiceItem::where('invoice_id', $invoice->id)->delete();
        $invoice->delete();
        $this->flash('success', t('user.invoices.deleted'));
        return redirect('/app/invoices');
    }

    public function pdf(int $id): Response
    {
        $invoice = $this->findOwned($id);
        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);
        $company = Company::find($invoice->company_id);
        $template = $company->activeInvoiceTemplate();
        // Stage 2: a company that has customized a template for this document type
        // (Company::activeInvoiceTemplateFor()) renders through the new layout-family
        // system (Controller::streamPdf() picks pdf.document-v2 whenever this is
        // non-null) — every other company (the common case right after Stage 1)
        // falls straight through to the unchanged 6-preset system below.
        $invoiceTemplate = $company->activeInvoiceTemplateFor('invoice');
        $lang = $this->pdfLangFor($invoiceTemplate);

        // The real gross subtotal (sum of each line's qty*unit_price, before
        // ANY discount) — kept as its own figure distinct from
        // invoice->total - invoice->vat_amount, which is now the TAXABLE
        // amount (post line+header discount, plus other charges — see
        // store()'s identical computation). For an invoice with no
        // discount/other charges (every invoice created before this
        // feature, and any new one that simply leaves them blank) the two
        // are numerically identical, so nothing here changes what those
        // invoices have always rendered.
        $grossSubtotal = (float) $items->sum('total');
        $lineDiscountsTotal = (float) $items->sum('discount_amount');
        $headerDiscount = (float) $invoice->discount_amount;
        $totalDiscount = $lineDiscountsTotal + $headerDiscount;
        $otherCharges = (float) $invoice->other_charges;
        $taxableAmount = (float) $invoice->total - (float) $invoice->vat_amount;

        // This app has no dedicated paid-amount/partial-payment tracking on
        // an invoice — only the flat 'unpaid'/'paid'/'overdue' status (see
        // Invoice::isOverdue()). Absent that, the only honest "Paid" figure
        // is the full total once status is 'paid', and 0 otherwise; a
        // partially-paid invoice isn't a state this app can represent today.
        $paidAmount = $invoice->status === 'paid' ? (float) $invoice->total : 0.0;
        $balanceDue = round((float) $invoice->total - $paidAmount, 2);

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
            'billToAddress' => \App\Support\Pdf\AddressFormatter::fromModel($client),
            'sellerAddress' => \App\Support\Pdf\AddressFormatter::fromModel($company),
            'items' => $items->map(function ($i) use ($lang, $invoice) {
                // A real ZATCA-style line's own "total" is its taxable
                // amount plus the VAT charged on it — NOT the bare
                // qty*unit_price this column showed before line discounts
                // existed. Computed here (not left to document-v2's shared
                // $itemRows, whose 'total' fallback other document types
                // still rely on meaning "whatever that item's own total
                // is") so only invoices get this corrected figure; every
                // other document type sharing document-v2.blade.php/
                // document.blade.php is completely unaffected.
                $lineTaxable = max(0, (float) $i->qty * (float) $i->unit_price - (float) $i->discount_amount);
                $lineVat = $lineTaxable * (float) $invoice->vat_rate / 100;
                return [
                    'description' => ($lang === 'ar' && !empty($i->description_ar)) ? $i->description_ar : $i->description,
                    'item_code' => $i->item_code,
                    'unit' => $i->unit,
                    'qty' => $i->qty,
                    'unit_price' => $i->unit_price,
                    'discount' => (float) $i->discount_amount,
                    'tax_code' => $i->tax_code ?: 'S',
                    'total' => round($lineTaxable + $lineVat, 2),
                ];
            })->all(),
            'subtotal' => $grossSubtotal,
            'discountPercent' => $grossSubtotal > 0 ? round($totalDiscount / $grossSubtotal * 100, 2) : 0.0,
            'discountAmount' => $totalDiscount,
            'otherCharges' => $otherCharges,
            'taxableAmount' => $taxableAmount,
            'paymentMethod' => $invoice->payment_method,
            // A plain Y-m-d string, not the Carbon instance itself — unlike
            // $docDate (created_at, a real timestamp), "Date of Supply" is
            // always a pure date with no meaningful time component, and a
            // bare (string) cast of a Carbon instance ignores the model's
            // 'date:Y-m-d' cast format and would print "00:00:00" on every
            // PDF template that does `(string) $dateOfSupply`.
            'dateOfSupply' => $invoice->date_of_supply?->format('Y-m-d'),
            'paidAmount' => $paidAmount,
            'balanceDue' => $balanceDue,
            'vatRate' => $invoice->vat_rate,
            'vatAmount' => (float) $invoice->vat_amount,
            'total' => (float) $invoice->total,
            'qrCode' => $this->zatcaQrDataUri($invoice, $company),
            'footerNote' => $lang === 'ar' ? 'تم إنشاؤه بواسطة ' . Setting::siteName() : 'Generated by ' . Setting::siteName(),
            'invoiceTemplate' => $invoiceTemplate,
            'company' => $company,
            'documentType' => 'invoice',
            'pageSize' => $invoiceTemplate ? ($invoiceTemplate->page_size ?: 'a4') : 'a4',
            'partyLabelKey' => 'pdf.bill_to',
            'partyNameAr' => $client?->name_ar,
            'partyVat' => $client?->vat_number,
            'zatcaStatus' => in_array($invoice->zatca_status, ['cleared', 'reported'], true) ? $invoice->zatca_status : null,
        ], $invoice->invoice_number . '.pdf');
    }

    public function xml(int $id, ZatcaSyncService $zatcaSync): Response|RedirectResponse
    {
        $invoice = $this->findOwned($id);
        if (empty($invoice->zatca_uuid)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.no_zatca_chain_data_legacy'));
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
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('common.zatca_phase2_not_activated'));
        }
        if (empty($invoice->zatca_uuid)) {
            return $this->redirectWithFlash('/app/invoices/' . $invoice->id, 'error', t('user.invoices.no_zatca_chain_data'));
        }

        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $client = $this->ownedClient($invoice->client_id, $invoice->company_id);

        $result = $zatcaSync->submitInvoice($invoice, $company, $client, $this->itemsForXml($items));

        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : t('user.invoices.zatca_rejected', ['reason' => $result['message']]));

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

    /**
     * Parses a submitted field as a number and checks it against inclusive bounds,
     * returning null for anything missing/non-numeric/out of range so the caller can
     * redirect back with a translated error instead of silently coercing bad input —
     * mirrors EstimateController's identical helper of the same name, applied here to
     * invoice line-item qty/unit_price/discount and the header discount/other-charges/
     * retention_percent fields, none of which had any type or range check before.
     */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected — used for fields where clearing the input has always meant "none" (unit_price, discounts, retention_percent). */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $this->validatedNumber($value, $min, $max);
    }

    /** True when $value is empty, or a real calendar date in Y-m-d form — used to validate due_date/date_of_supply before they're stored, since a malformed string would otherwise be written as-is or crash a later Carbon::parse(). */
    private function isValidOptionalDate(string $value): bool
    {
        if ($value === '') {
            return true;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
