<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqQuote;
use App\Models\RfqQuoteItem;
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
        if (!$this->isValidOptionalDate(trim((string) $request->input('due_date', '')))) {
            return $this->redirectWithFlash('/app/rfqs/create', 'error', t('user.rfqs.due_date_invalid'));
        }

        [$items, $itemsError] = $this->validatedItems($request);
        if ($itemsError) {
            return $this->redirectWithFlash('/app/rfqs/create', 'error', $itemsError);
        }

        $rfq = Rfq::create([
            'company_id' => $companyId,
            'project_id' => $this->ownedProjectId($request->input('project_id') ?: null, $companyId),
            'title' => mb_substr($title, 0, 200),
            'status' => 'draft',
            'due_date' => $request->input('due_date') ?: null,
            'created_by' => Auth::id(),
            'notes' => mb_substr(trim((string) $request->input('notes', '')), 0, 2000),
        ]);

        foreach ($items as $item) {
            RfqItem::create(['rfq_id' => $rfq->id, ...$item]);
        }

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

        // Invited-but-not-yet-submitted quotes carry a placeholder total_amount of 0 —
        // never let one of those win "cheapest"/"fastest" against real, submitted bids.
        $submittedQuotes = $quotes->whereNotNull('submitted_at');
        $cheapestId = $submittedQuotes->sortBy('total_amount')->first()?->id;
        $fastestId = $submittedQuotes->whereNotNull('lead_time_days')->sortBy('lead_time_days')->first()?->id;

        $quoteRows = $quotes->map(fn (RfqQuote $q) => [
            ...$q->toArray(),
            'supplier_name' => $suppliers->get($q->supplier_id)->name ?? '—',
            'is_cheapest' => $q->id === $cheapestId,
            'is_fastest' => $fastestId !== null && $q->id === $fastestId,
            // Only a still-pending invite has a usable bid link — once submitted,
            // the token stays valid but there is nothing left to copy/send.
            'invite_url' => ($q->submitted_at === null && !empty($q->invite_token))
                ? url('/rfq-bid/' . $q->invite_token)
                : null,
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
        if (!$this->isValidOptionalDate(trim((string) $request->input('due_date', '')))) {
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id . '/edit', 'error', t('user.rfqs.due_date_invalid'));
        }

        // Status can be moved forward through draft -> sent -> comparing -> cancelled here,
        // but never set to 'awarded' this way — that only ever happens through award(),
        // which also stamps the winning quote, so the two can't fall out of sync.
        $status = $request->input('status');
        $data = [
            'project_id' => $this->ownedProjectId($request->input('project_id') ?: null, $rfq->company_id),
            'title' => mb_substr($title, 0, 200),
            'due_date' => $request->input('due_date') ?: null,
            'notes' => mb_substr(trim((string) $request->input('notes', '')), 0, 2000),
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
        $qty = $this->validatedNumberOrDefault($request->input('qty'), 0.01, 9999999999.99, 1.0);
        if ($qty === null) {
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.qty_invalid'));
        }

        RfqItem::create([
            'rfq_id' => $rfq->id,
            'description' => mb_substr($description, 0, 255),
            'qty' => $qty,
            'unit' => mb_substr(trim((string) $request->input('unit', '')), 0, 50) ?: null,
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

        // Optional per-line breakdown (item_unit_price[{rfq_item_id}]) — when one or
        // more lines are itemized, the lump total_amount field is ignored entirely and
        // total_amount is computed as the sum of the itemized lines instead, rather
        // than trusting two independently-typed numbers to agree. Only when nothing
        // was itemized does the plain lump total_amount get validated and used,
        // exactly as before this feature existed.
        $itemRows = $this->parseItemizedQuote($request, $rfq->id);
        if ($itemRows) {
            $totalAmount = round(array_sum(array_column($itemRows, 'total')), 2);
        } else {
            $totalAmount = $this->validatedNumber($request->input('total_amount'), 0.01, 9999999999.99);
            if ($totalAmount === null) {
                return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.quote_amount_required'));
            }
        }
        $leadTimeDays = null;
        if ($request->filled('lead_time_days')) {
            $leadTimeDays = $this->validatedNumber($request->input('lead_time_days'), 0, 3650);
            if ($leadTimeDays === null) {
                return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.lead_time_days_invalid'));
            }
        }
        $submittedAt = trim((string) $request->input('submitted_at', ''));
        if (!$this->isValidOptionalDate($submittedAt)) {
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.submitted_at_invalid'));
        }

        $quote = RfqQuote::create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'total_amount' => $totalAmount,
            'lead_time_days' => $leadTimeDays !== null ? (int) $leadTimeDays : null,
            'notes' => mb_substr(trim((string) $request->input('notes', '')), 0, 2000) ?: null,
            'is_awarded' => false,
            'submitted_at' => $submittedAt !== '' ? $submittedAt : now()->format('Y-m-d'),
        ]);
        $this->createQuoteItems($quote->id, $itemRows);

        // Receiving the first quote moves a still-draft/sent RFQ into "comparing" —
        // bids are now actually coming in, so the RFQ is no longer just a plan.
        if (in_array($rfq->status, ['draft', 'sent'], true)) {
            $rfq->update(['status' => 'comparing']);
        }

        $this->flash('success', t('user.rfqs.quote_added'));
        return redirect('/app/rfqs/' . $rfq->id);
    }

    /**
     * Self-service alternative to recordQuote(): instead of the GC typing in a
     * supplier's number themselves, this invites the supplier to submit it directly
     * via a private, token-based link (ShareController::rfqBid()/submitRfqBid()) —
     * the same public-token-route shape as Estimate/PaymentCertificate/ChangeOrder's
     * share links, except the token lives on the per-supplier RfqQuote row rather
     * than on the parent Rfq, since one RFQ can invite several suppliers and each
     * must only ever see/submit their own quote. Both paths keep working side by
     * side and both end up as an ordinary RfqQuote row.
     */
    public function invite(Request $request, int $id): RedirectResponse
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
            return $this->redirectWithFlash('/app/rfqs/' . $rfq->id, 'error', t('user.rfqs.invite_supplier_required'));
        }

        RfqQuote::create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'invite_token' => bin2hex(random_bytes(20)),
            'total_amount' => 0,
            'lead_time_days' => null,
            'notes' => null,
            'is_awarded' => false,
            'submitted_at' => null,
        ]);

        $this->flash('success', t('user.rfqs.invite_created'));
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

    /**
     * Bid-leveling matrix: every RFQ item as a row, every submitted quote as a column,
     * so a contractor can see who's cheapest on which specific line, not just overall.
     * A cell is null ("not broken down") for any supplier who didn't itemize that line
     * — itemization is optional per supplier, so a mix of itemized and lump-only quotes
     * on the same RFQ is expected, not an error. Read-only: nothing here is persisted.
     */
    public function leveling(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfq_quotes')) {
            return $redirect;
        }
        $rfq = $this->findOwned($id);

        $items = RfqItem::where('rfq_id', $rfq->id)->orderBy('id')->get();
        // An invite that hasn't been answered yet has nothing to level — its
        // placeholder total_amount of 0 and empty item breakdown would only
        // muddy the comparison.
        $quotes = RfqQuote::where('rfq_id', $rfq->id)->whereNotNull('submitted_at')->orderBy('id')->get();
        $suppliers = Supplier::whereIn('id', $quotes->pluck('supplier_id')->unique())->get()->keyBy('id');
        $quoteItemsByQuote = RfqQuoteItem::whereIn('rfq_quote_id', $quotes->pluck('id'))
            ->get()
            ->groupBy('rfq_quote_id');

        $quoteColumns = $quotes->map(fn (RfqQuote $q) => [
            'id' => $q->id,
            'supplier_name' => $suppliers->get($q->supplier_id)->name ?? '—',
            'total_amount' => (float) $q->total_amount,
            'items' => ($quoteItemsByQuote->get($q->id) ?? collect())->keyBy('rfq_item_id'),
        ])->all();

        $bestCombinedTotal = 0.0;
        $rows = $items->map(function (RfqItem $item) use ($quoteColumns, &$bestCombinedTotal) {
            $cheapestQuoteId = null;
            $cheapestTotal = null;
            $cells = [];
            foreach ($quoteColumns as $col) {
                $quoteItem = $col['items']->get($item->id);
                $total = $quoteItem ? (float) $quoteItem->total : null;
                $cells[$col['id']] = [
                    'unit_price' => $quoteItem ? (float) $quoteItem->unit_price : null,
                    'total' => $total,
                ];
                // First one found wins a tie — ties are rare (independent suppliers
                // rarely land on the exact same line price) and there's no further
                // signal here (speed, notes) to break one, so "first found" is simplest.
                if ($total !== null && ($cheapestTotal === null || $total < $cheapestTotal)) {
                    $cheapestTotal = $total;
                    $cheapestQuoteId = $col['id'];
                }
            }
            if ($cheapestTotal !== null) {
                $bestCombinedTotal += $cheapestTotal;
            }

            return [
                'item' => $item->toArray(),
                'cells' => $cells,
                'cheapest_quote_id' => $cheapestQuoteId,
            ];
        })->all();

        return view('app.rfqs.leveling', [
            'rfq' => $rfq->toArray(),
            'rows' => $rows,
            'quoteColumns' => $quoteColumns,
            'bestCombinedTotal' => $bestCombinedTotal,
            'anyLineItemized' => collect($rows)->contains(fn ($r) => collect($r['cells'])->contains(fn ($c) => $c['total'] !== null)),
        ]);
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

    /**
     * Shared by store()'s initial line items — update()/edit() manage items one at a time via
     * addItem()/removeItem(). Validates every row before anything is persisted (unlike the old
     * create-as-we-go loop) so an invalid qty aborts the whole submission instead of leaving a
     * partially-built RFQ behind.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: ?string} [items, error]
     */
    private function validatedItems(Request $request): array
    {
        $descriptions = $request->input('item_description', []);
        $qtys = $request->input('item_qty', []);
        $units = $request->input('item_unit', []);

        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qty = $this->validatedNumberOrDefault($qtys[$i] ?? 1, 0.01, 9999999999.99, 1.0);
            if ($qty === null) {
                return [[], t('user.rfqs.qty_invalid')];
            }
            $items[] = [
                'description' => mb_substr($desc, 0, 255),
                'qty' => $qty,
                'unit' => mb_substr(trim((string) ($units[$i] ?? '')), 0, 50) ?: null,
            ];
        }

        return [$items, null];
    }

    /**
     * Parses the optional item_unit_price[{rfq_item_id}] array against this RFQ's own
     * items, keeping only entries that name a real item on this RFQ and carry a
     * non-empty, numeric, strictly-positive price — anything else (blank, zero,
     * non-numeric, or an item id not on this RFQ) is silently skipped rather than
     * rejected, since itemization is entirely optional and a partial breakdown is a
     * normal, valid submission. Returns [] when nothing was itemized, which callers
     * treat as "fall back to the plain lump total_amount", unchanged from before this
     * table existed.
     *
     * @return array<int, array{rfq_item_id: int, unit_price: float, total: float}>
     */
    private function parseItemizedQuote(Request $request, int $rfqId): array
    {
        $prices = $request->input('item_unit_price', []);
        if (!is_array($prices) || !$prices) {
            return [];
        }
        $items = RfqItem::where('rfq_id', $rfqId)->get()->keyBy('id');

        $rows = [];
        foreach ($prices as $itemId => $price) {
            $item = $items->get((int) $itemId);
            if (!$item || $price === null || $price === '' || !is_numeric($price)) {
                continue;
            }
            $unitPrice = (float) $price;
            if ($unitPrice <= 0) {
                continue;
            }
            $rows[] = [
                'rfq_item_id' => $item->id,
                'unit_price' => $unitPrice,
                'total' => round((float) $item->qty * $unitPrice, 2),
            ];
        }

        return $rows;
    }

    /** Creates one RfqQuoteItem per parsed row — a no-op when $rows is empty (a plain lump-sum quote). */
    private function createQuoteItems(int $quoteId, array $rows): void
    {
        foreach ($rows as $row) {
            RfqQuoteItem::create([
                'rfq_quote_id' => $quoteId,
                'rfq_item_id' => $row['rfq_item_id'],
                'unit_price' => $row['unit_price'],
                'total' => $row['total'],
            ]);
        }
    }

    /** Rejects a non-numeric or out-of-range value — see EstimateController::validatedNumber() for the shared shape this mirrors. */
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

    /** True when $value is empty, or a real calendar date in Y-m-d form. */
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
