<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqQuote;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Request-for-Quote / multi-supplier quote comparison: a company invites several
 * suppliers to bid on the same scope of work, records each quote, compares them side
 * by side, and awards one — a documented competitive-bid trail that sits next to the
 * single-vendor PurchaseOrderController flow without replacing it. Awarding a quote
 * never auto-creates a PurchaseOrder; that stays a deliberate manual next step.
 */
class RfqController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $rfqs = Rfq::where('company_id', $companyId)->orderByDesc('id')->get();
        $projects = Project::where('company_id', $companyId)->get()->keyBy('id');
        $quoteCounts = RfqQuote::whereIn('rfq_id', $rfqs->pluck('id'))
            ->selectRaw('rfq_id, count(*) as c')
            ->groupBy('rfq_id')
            ->pluck('c', 'rfq_id');

        $rows = $rfqs->map(fn (Rfq $r) => [
            ...$r->toArray(),
            'project_name' => $r->project_id ? ($projects->get($r->project_id)->name ?? null) : null,
            'quote_count' => (int) ($quoteCounts[$r->id] ?? 0),
        ])->all();

        return view('app.rfqs.index', [
            'rfqs' => $rows,
            'statuses' => Rfq::STATUSES,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        return view('app.rfqs.form', [
            'rfq' => null,
            'items' => [],
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return $this->redirectWithFlash('/app/rfqs/create', 'error', t('user.rfqs.title_required'));
        }

        $rfq = Rfq::create([
            'company_id' => $companyId,
            'project_id' => $this->ownedProjectId($request->input('project_id') ?: null, $companyId),
            'title' => $title,
            'status' => 'draft',
            'due_date' => $request->input('due_date') ?: null,
            'created_by' => Auth::id(),
            'notes' => trim((string) $request->input('notes', '')),
        ]);

        $this->syncItems($request, $rfq);

        $this->flash('success', t('user.rfqs.created'));
        return redirect('/app/rfqs/' . $rfq->id);
    }

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);

        $items = RfqItem::where('rfq_id', $rfq->id)->orderBy('id')->get()->toArray();
        $quotes = RfqQuote::where('rfq_id', $rfq->id)->orderBy('total_amount')->get();
        $suppliers = Supplier::whereIn('id', $quotes->pluck('supplier_id')->unique())->get()->keyBy('id');

        $cheapestId = $quotes->sortBy('total_amount')->first()?->id;
        $fastestId = $quotes->whereNotNull('lead_time_days')->sortBy('lead_time_days')->first()?->id;

        $quoteRows = $quotes->map(fn (RfqQuote $q) => [
            ...$q->toArray(),
            'supplier_name' => $suppliers->get($q->supplier_id)->name ?? '—',
            'is_cheapest' => $q->id === $cheapestId,
            'is_fastest' => $fastestId !== null && $q->id === $fastestId,
        ])->all();

        $project = $rfq->project_id ? Project::find($rfq->project_id) : null;

        return view('app.rfqs.show', [
            'rfq' => $rfq->toArray(),
            'project' => $project?->toArray(),
            'items' => $items,
            'quotes' => $quoteRows,
            'suppliers' => Supplier::where('company_id', $rfq->company_id)->orderBy('name')->get()->toArray(),
            'statuses' => Rfq::STATUSES,
            'isEditable' => $rfq->isEditable(),
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);
        $companyId = Auth::user()->company_id;

        return view('app.rfqs.form', [
            'rfq' => $rfq->toArray(),
            'items' => RfqItem::where('rfq_id', $rfq->id)->orderBy('id')->get()->toArray(),
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id . '/edit', 'error', t('user.rfqs.title_required'));
        }

        // Status can be moved forward through draft -> sent -> comparing -> cancelled here,
        // but never set to 'awarded' this way — that only ever happens through award(),
        // which also stamps the winning quote, so the two can't fall out of sync.
        $status = $request->input('status');
        $data = [
            'project_id' => $this->ownedProjectId($request->input('project_id') ?: null, $rfq->company_id),
            'title' => $title,
            'due_date' => $request->input('due_date') ?: null,
            'notes' => trim((string) $request->input('notes', '')),
        ];
        if (array_key_exists($status, Rfq::STATUSES) && $status !== 'awarded') {
            $data['status'] = $status;
        }

        $rfq->update($data);
        $this->flash('success', t('user.rfqs.updated'));
        return redirect('/app/rfqs/' . $rfq->id);
    }

    public function addItem(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);

        $description = trim((string) $request->input('description'));
        if ($description === '') {
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.item_description_required'));
        }

        RfqItem::create([
            'rfq_id' => $rfq->id,
            'description' => $description,
            'qty' => (float) ($request->input('qty') ?: 1),
            'unit' => trim((string) $request->input('unit', '')) ?: null,
        ]);

        $this->flash('success', t('user.rfqs.item_added'));
        return redirect('/app/rfqs/' . $rfq->id);
    }

    public function removeItem(int $id, int $itemId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);
        $item = RfqItem::find($itemId);
        abort_if(!$item || $item->rfq_id !== $rfq->id, 404, 'RFQ item not found.');
        $item->delete();

        $this->flash('success', t('user.rfqs.item_removed'));
        return redirect('/app/rfqs/' . $rfq->id);
    }

    public function recordQuote(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);

        $supplier = $this->ownedSupplier($request->input('supplier_id') ?: null, $rfq->company_id);
        if (!$supplier) {
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.quote_supplier_required'));
        }

        $totalAmount = (float) $request->input('total_amount');
        if ($totalAmount <= 0) {
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.quote_amount_required'));
        }

        RfqQuote::create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'total_amount' => $totalAmount,
            'lead_time_days' => $request->filled('lead_time_days') ? (int) $request->input('lead_time_days') : null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'is_awarded' => false,
            'submitted_at' => $request->input('submitted_at') ?: now()->format('Y-m-d'),
        ]);

        // Receiving the first quote moves a still-draft/sent RFQ into "comparing" —
        // bids are now actually coming in, so the RFQ is no longer just a plan.
        if (in_array($rfq->status, ['draft', 'sent'], true)) {
            $rfq->update(['status' => 'comparing']);
        }

        $this->flash('success', t('user.rfqs.quote_added'));
        return redirect('/app/rfqs/' . $rfq->id);
    }

    public function award(int $id, int $quoteId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);
        $quote = RfqQuote::find($quoteId);
        abort_if(!$quote || $quote->rfq_id !== $rfq->id, 404, 'Quote not found.');

        DB::transaction(function () use ($rfq, $quote) {
            RfqQuote::where('rfq_id', $rfq->id)->update(['is_awarded' => false]);
            $quote->update(['is_awarded' => true]);
            $rfq->update(['status' => 'awarded']);
        });

        $this->flash('success', t('user.rfqs.awarded'));
        return redirect('/app/rfqs/' . $rfq->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);
        if ($rfq->status === 'awarded') {
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.cannot_delete_awarded'));
        }

        RfqQuote::where('rfq_id', $rfq->id)->delete();
        RfqItem::where('rfq_id', $rfq->id)->delete();
        $rfq->delete();

        return $this->redirectWithFlash('/app/rfqs', 'success', t('user.rfqs.deleted'));
    }

    /** Shared by store()'s initial line items — update()/edit() manage items one at a time via addItem()/removeItem(). */
    private function syncItems(Request $request, Rfq $rfq): void
    {
        $descriptions = $request->input('item_description', []);
        $qtys = $request->input('item_qty', []);
        $units = $request->input('item_unit', []);

        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            RfqItem::create([
                'rfq_id' => $rfq->id,
                'description' => $desc,
                'qty' => (float) ($qtys[$i] ?? 1),
                'unit' => trim((string) ($units[$i] ?? '')) ?: null,
            ]);
        }
    }

    private function findOwned(int $id): Rfq
    {
        $rfq = Rfq::find($id);
        abort_if(!$rfq || $rfq->company_id !== Auth::user()->company_id, 404, 'RFQ not found.');
        return $rfq;
    }

    /** Only returns the project id if it belongs to $companyId — never trust a raw project_id from the request. */
    private function ownedProjectId(?int $id, int $companyId): ?int
    {
        if (!$id) {
            return null;
        }
        $project = Project::find($id);
        return ($project && $project->company_id === $companyId) ? $project->id : null;
    }

    /** Only returns the supplier if it belongs to $companyId — never trust a raw supplier_id from the request. */
    private function ownedSupplier(?int $id, int $companyId): ?Supplier
    {
        if (!$id) {
            return null;
        }
        $supplier = Supplier::find($id);
        return ($supplier && $supplier->company_id === $companyId) ? $supplier : null;
    }
}
