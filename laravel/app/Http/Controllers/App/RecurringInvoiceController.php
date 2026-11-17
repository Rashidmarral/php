<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RecurringInvoiceController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('recurring_invoices')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $templates = RecurringInvoice::where('company_id', $companyId)
            ->orderByDesc('is_active')
            ->orderBy('next_run_date')
            ->get();

        $rows = $templates->map(function (RecurringInvoice $rt) {
            $client = $rt->client_id ? Client::find($rt->client_id) : null;
            $row = $rt->toArray();
            $row['client_name'] = $client?->name;
            $row['client_name_ar'] = $client?->name_ar;
            $row['generated_count'] = $rt->generatedInvoices()->count();
            return $row;
        })->all();

        return view('app.recurring-invoices.index', ['templates' => $rows]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('recurring_invoices')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);

        return view('app.recurring-invoices.form', [
            'template' => null,
            'items' => [],
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'frequencies' => RecurringInvoice::FREQUENCIES,
            'vatRate' => (float) Setting::get('vat_rate', '15'),
            'defaultRetentionPercent' => (float) ($company->default_retention_percent ?? 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('recurring_invoices')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $title = mb_substr(trim((string) $request->input('title')), 0, 255);
        if ($title === '') {
            return $this->redirectWithFlash('/app/recurring-invoices/create', 'error', t('user.recurring_invoices.title_required'));
        }

        $nextRunDateRaw = trim((string) $request->input('next_run_date', ''));
        if (!$this->isValidOptionalDate($nextRunDateRaw)) {
            return $this->redirectWithFlash('/app/recurring-invoices/create', 'error', t('user.recurring_invoices.next_run_date_invalid'));
        }

        $retentionPercent = $this->validatedNumberOrDefault($request->input('retention_percent', 0), 0, 100, 0.0);
        if ($retentionPercent === null) {
            return $this->redirectWithFlash('/app/recurring-invoices/create', 'error', t('user.recurring_invoices.retention_percent_invalid'));
        }
        $dueDays = $this->validatedNumberOrDefault($request->input('due_days', 14), 0, 3650, 14.0);
        if ($dueDays === null) {
            return $this->redirectWithFlash('/app/recurring-invoices/create', 'error', t('user.recurring_invoices.due_days_invalid'));
        }

        [$items, $itemsError] = $this->itemsFromRequest($request);
        if ($itemsError !== null) {
            return $this->redirectWithFlash('/app/recurring-invoices/create', 'error', t($itemsError));
        }
        if (empty($items)) {
            return $this->redirectWithFlash('/app/recurring-invoices/create', 'error', t('user.recurring_invoices.line_item_required'));
        }

        $client = $this->ownedClient($request->input('client_id') ?: null, $companyId);
        $project = $this->ownedProject($request->input('project_id') ?: null, $companyId);
        $frequency = array_key_exists($request->input('frequency'), RecurringInvoice::FREQUENCIES) ? $request->input('frequency') : 'monthly';

        $template = RecurringInvoice::create([
            'company_id' => $companyId,
            'client_id' => $client?->id,
            'project_id' => $project?->id,
            'title' => $title,
            'frequency' => $frequency,
            'next_run_date' => $nextRunDateRaw !== '' ? $nextRunDateRaw : now()->format('Y-m-d'),
            'is_active' => true,
            'apply_vat' => (bool) $request->input('apply_vat', true),
            'retention_percent' => $retentionPercent,
            'due_days' => (int) $dueDays,
        ]);

        foreach ($items as $item) {
            RecurringInvoiceItem::create(['recurring_invoice_id' => $template->id, ...$item]);
        }

        $this->flash('success', t('user.recurring_invoices.created'));
        return redirect('/app/recurring-invoices/' . $template->id);
    }

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('recurring_invoices')) {
            return $redirect;
        }
        $template = $this->findOwned($id);
        $client = $this->ownedClient($template->client_id, $template->company_id);
        $project = $this->ownedProject($template->project_id, $template->company_id);
        $items = $template->items()->orderBy('id')->get()->toArray();
        $generated = $template->generatedInvoices()->get()->toArray();

        return view('app.recurring-invoices.show', [
            'template' => $template->toArray(),
            'client' => $client,
            'project' => $project,
            'items' => $items,
            'generated' => $generated,
            'frequencies' => RecurringInvoice::FREQUENCIES,
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('recurring_invoices')) {
            return $redirect;
        }
        $template = $this->findOwned($id);
        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);

        return view('app.recurring-invoices.form', [
            'template' => $template->toArray(),
            'items' => $template->items()->orderBy('id')->get()->toArray(),
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'frequencies' => RecurringInvoice::FREQUENCIES,
            'vatRate' => (float) Setting::get('vat_rate', '15'),
            'defaultRetentionPercent' => (float) ($company->default_retention_percent ?? 0),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('recurring_invoices')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $template = $this->findOwned($id);
        $companyId = $template->company_id;

        $title = mb_substr(trim((string) $request->input('title')), 0, 255);
        if ($title === '') {
            return $this->redirectWithFlash('/app/recurring-invoices/' . $template->id . '/edit', 'error', t('user.recurring_invoices.title_required'));
        }

        $nextRunDateRaw = trim((string) $request->input('next_run_date', ''));
        if (!$this->isValidOptionalDate($nextRunDateRaw)) {
            return $this->redirectWithFlash('/app/recurring-invoices/' . $template->id . '/edit', 'error', t('user.recurring_invoices.next_run_date_invalid'));
        }

        $retentionPercent = $this->validatedNumberOrDefault($request->input('retention_percent', 0), 0, 100, 0.0);
        if ($retentionPercent === null) {
            return $this->redirectWithFlash('/app/recurring-invoices/' . $template->id . '/edit', 'error', t('user.recurring_invoices.retention_percent_invalid'));
        }
        $dueDays = $this->validatedNumberOrDefault($request->input('due_days', 14), 0, 3650, 14.0);
        if ($dueDays === null) {
            return $this->redirectWithFlash('/app/recurring-invoices/' . $template->id . '/edit', 'error', t('user.recurring_invoices.due_days_invalid'));
        }

        [$items, $itemsError] = $this->itemsFromRequest($request);
        if ($itemsError !== null) {
            return $this->redirectWithFlash('/app/recurring-invoices/' . $template->id . '/edit', 'error', t($itemsError));
        }
        if (empty($items)) {
            return $this->redirectWithFlash('/app/recurring-invoices/' . $template->id . '/edit', 'error', t('user.recurring_invoices.line_item_required'));
        }

        $client = $this->ownedClient($request->input('client_id') ?: null, $companyId);
        $project = $this->ownedProject($request->input('project_id') ?: null, $companyId);
        $frequency = array_key_exists($request->input('frequency'), RecurringInvoice::FREQUENCIES) ? $request->input('frequency') : 'monthly';

        $template->update([
            'client_id' => $client?->id,
            'project_id' => $project?->id,
            'title' => $title,
            'frequency' => $frequency,
            'next_run_date' => $nextRunDateRaw !== '' ? $nextRunDateRaw : $template->next_run_date,
            'apply_vat' => (bool) $request->input('apply_vat', true),
            'retention_percent' => $retentionPercent,
            'due_days' => (int) $dueDays,
        ]);

        RecurringInvoiceItem::where('recurring_invoice_id', $template->id)->delete();
        foreach ($items as $item) {
            RecurringInvoiceItem::create(['recurring_invoice_id' => $template->id, ...$item]);
        }

        $this->flash('success', t('user.recurring_invoices.updated'));
        return redirect('/app/recurring-invoices/' . $template->id);
    }

    /** Pauses/resumes generation without touching the template or any invoice it has already generated. */
    public function toggleActive(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('recurring_invoices')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $template = $this->findOwned($id);
        $template->update(['is_active' => !$template->is_active]);

        $this->flash('success', t($template->is_active ? 'user.recurring_invoices.resumed' : 'user.recurring_invoices.paused'));
        return redirect('/app/recurring-invoices/' . $template->id);
    }

    /**
     * A template that has never generated an invoice can be deleted outright.
     * Once it HAS generated one, deleting the template would leave those real,
     * already-sent invoices' recurring_invoice_id pointing at nothing — instead
     * we just deactivate it (same as toggleActive) so future generation stops
     * for good while every invoice already sent to the client stays exactly as
     * it is.
     */
    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('recurring_invoices')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $template = $this->findOwned($id);

        if ($template->generatedInvoices()->exists()) {
            $template->update(['is_active' => false]);
            return $this->redirectWithFlash('/app/recurring-invoices', 'success', t('user.recurring_invoices.deactivated_instead_of_deleted'));
        }

        RecurringInvoiceItem::where('recurring_invoice_id', $template->id)->delete();
        $template->delete();
        return $this->redirectWithFlash('/app/recurring-invoices', 'success', t('user.recurring_invoices.deleted'));
    }

    /**
     * @return array{0: array<int, array{description:string,description_ar:string,qty:float,unit_price:float}>, 1: ?string}
     *
     * The second element is a translation key for the first invalid qty/unit_price
     * found (null when everything parsed cleanly) — store()/update() redirect back
     * with it instead of silently coercing non-numeric/out-of-range input to 0,
     * which is what (float) casts of raw request input used to do here.
     */
    private function itemsFromRequest(Request $request): array
    {
        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);

        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = mb_substr(trim((string) $desc), 0, 255);
            if ($desc === '') {
                continue;
            }
            $qty = $this->validatedNumber($qtys[$i] ?? 1, 0.01, 99999999.99);
            if ($qty === null) {
                return [[], 'user.recurring_invoices.qty_invalid'];
            }
            $price = $this->validatedNumberOrDefault($prices[$i] ?? 0, 0, 99999999.99, 0.0);
            if ($price === null) {
                return [[], 'user.recurring_invoices.unit_price_invalid'];
            }
            $items[] = [
                'description' => $desc,
                'description_ar' => mb_substr(trim((string) ($descriptionsAr[$i] ?? '')), 0, 255),
                'qty' => $qty,
                'unit_price' => $price,
            ];
        }
        return [$items, null];
    }

    /**
     * Parses a submitted field as a number and checks it against inclusive bounds,
     * returning null for anything missing/non-numeric/out of range — mirrors
     * EstimateController's identical helper, applied here to recurring invoice
     * line items, retention_percent and due_days.
     */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected. */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $this->validatedNumber($value, $min, $max);
    }

    /** True when $value is empty, or a real calendar date in Y-m-d form — used to validate next_run_date before it's stored. */
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

    private function findOwned(int $id): RecurringInvoice
    {
        $template = RecurringInvoice::find($id);
        abort_if(!$template || $template->company_id !== Auth::user()->company_id, 404, 'Recurring invoice not found.');
        return $template;
    }

    /** Only returns the client if it belongs to $companyId — never trust a raw client_id from the request. */
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
