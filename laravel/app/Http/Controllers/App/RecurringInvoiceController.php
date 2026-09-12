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

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return $this->redirectWithFlash('/app/recurring-invoices/create', 'error', 'A title is required.');
        }

        $items = $this->itemsFromRequest($request);
        if (empty($items)) {
            return $this->redirectWithFlash('/app/recurring-invoices/create', 'error', 'A recurring invoice needs at least one line item.');
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
            'next_run_date' => $request->input('next_run_date') ?: now()->format('Y-m-d'),
            'is_active' => true,
            'apply_vat' => (bool) $request->input('apply_vat', true),
            'retention_percent' => min(100, max(0, (float) $request->input('retention_percent', 0))),
            'due_days' => max(0, (int) $request->input('due_days', 14)),
        ]);

        foreach ($items as $item) {
            RecurringInvoiceItem::create(['recurring_invoice_id' => $template->id, ...$item]);
        }

        $this->flash('success', 'Recurring invoice template created.');
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

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return $this->redirectWithFlash('/app/recurring-invoices/' . $template->id . '/edit', 'error', 'A title is required.');
        }

        $items = $this->itemsFromRequest($request);
        if (empty($items)) {
            return $this->redirectWithFlash('/app/recurring-invoices/' . $template->id . '/edit', 'error', 'A recurring invoice needs at least one line item.');
        }

        $client = $this->ownedClient($request->input('client_id') ?: null, $companyId);
        $project = $this->ownedProject($request->input('project_id') ?: null, $companyId);
        $frequency = array_key_exists($request->input('frequency'), RecurringInvoice::FREQUENCIES) ? $request->input('frequency') : 'monthly';

        $template->update([
            'client_id' => $client?->id,
            'project_id' => $project?->id,
            'title' => $title,
            'frequency' => $frequency,
            'next_run_date' => $request->input('next_run_date') ?: $template->next_run_date,
            'apply_vat' => (bool) $request->input('apply_vat', true),
            'retention_percent' => min(100, max(0, (float) $request->input('retention_percent', 0))),
            'due_days' => max(0, (int) $request->input('due_days', 14)),
        ]);

        RecurringInvoiceItem::where('recurring_invoice_id', $template->id)->delete();
        foreach ($items as $item) {
            RecurringInvoiceItem::create(['recurring_invoice_id' => $template->id, ...$item]);
        }

        $this->flash('success', 'Recurring invoice template updated.');
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

        $this->flash('success', 'Recurring invoice template ' . ($template->is_active ? 'resumed' : 'paused') . '.');
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
            return $this->redirectWithFlash('/app/recurring-invoices', 'success', 'This template has already generated invoices, so it has been deactivated instead of deleted — the invoices it generated are untouched.');
        }

        RecurringInvoiceItem::where('recurring_invoice_id', $template->id)->delete();
        $template->delete();
        return $this->redirectWithFlash('/app/recurring-invoices', 'success', 'Recurring invoice template deleted.');
    }

    /** @return array<int, array{description:string,description_ar:string,qty:float,unit_price:float}> */
    private function itemsFromRequest(Request $request): array
    {
        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);

        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $items[] = [
                'description' => $desc,
                'description_ar' => trim((string) ($descriptionsAr[$i] ?? '')),
                'qty' => (float) ($qtys[$i] ?? 1),
                'unit_price' => (float) ($prices[$i] ?? 0),
            ];
        }
        return $items;
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
