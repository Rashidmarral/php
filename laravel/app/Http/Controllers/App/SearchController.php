<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SearchController extends Controller
{
    /** Top N per entity type — this is a quick jump-to, not a full reporting query. */
    private const PER_TYPE_LIMIT = 5;

    /** Below this, a query is almost always a single keystroke mid-type, so running
     * it would table-scan five entity types for nothing — same reasoning each
     * module's own ?q= filter would apply to just one table. */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Cross-entity "jump to" search across this company's own Projects, Clients,
     * Estimates, Invoices and Suppliers — always scoped to Auth::user()->company_id,
     * same as every other query in this controller family, since a leak here would
     * surface another tenant's data in search results.
     *
     * Supports a plain GET (renders the results page) and, with ?format=json, a
     * JSON variant used by the topbar's debounced live-search dropdown.
     */
    public function search(Request $request): View|JsonResponse
    {
        $companyId = Auth::user()->company_id;
        $q = trim((string) $request->input('q', ''));
        $data = mb_strlen($q) >= self::MIN_QUERY_LENGTH ? $this->runSearch($companyId, $q) : $this->emptyData();
        $groups = $this->groups($data);
        $total = array_sum(array_map(fn ($g) => count($g['items']), $groups));

        if ($request->query('format') === 'json') {
            return response()->json(['q' => $q, 'groups' => $groups, 'total' => $total]);
        }

        return view('app.search.results', [
            'q' => $q,
            'groups' => $groups,
            'total' => $total,
            'minLength' => self::MIN_QUERY_LENGTH,
        ]);
    }

    private function emptyData(): array
    {
        return ['projects' => [], 'clients' => [], 'estimates' => [], 'invoices' => [], 'suppliers' => []];
    }

    /**
     * Same case-insensitive partial-match semantics ProjectController::index() and
     * EstimateController::index() already use for their own ?q= filters — just
     * fanned out across entity types and capped per type.
     */
    private function runSearch(int $companyId, string $q): array
    {
        $projects = Project::where('company_id', $companyId)
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('name_ar', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'name', 'name_ar'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => local($p, 'name'), 'url' => url('/app/projects/' . $p->id)])
            ->all();

        $clients = Client::where('company_id', $companyId)
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('name_ar', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'name', 'name_ar', 'email'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'label' => local($c, 'name') . ($c->email ? ' · ' . $c->email : ''),
                'url' => url('/app/clients/' . $c->id . '/edit'),
            ])
            ->all();

        $estimates = Estimate::where('company_id', $companyId)
            ->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('title_ar', 'like', "%{$q}%");
            })
            ->orderByDesc('created_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'title', 'title_ar'])
            ->map(fn ($e) => ['id' => $e->id, 'label' => local($e, 'title'), 'url' => url('/app/estimates/' . $e->id)])
            ->all();

        $invoices = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', "%{$q}%")
            ->orderByDesc('created_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'invoice_number'])
            ->map(fn ($i) => ['id' => $i->id, 'label' => $i->invoice_number, 'url' => url('/app/invoices/' . $i->id)])
            ->all();

        $suppliers = Supplier::where('company_id', $companyId)
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('name_ar', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'name', 'name_ar'])
            ->map(fn ($s) => ['id' => $s->id, 'label' => local($s, 'name'), 'url' => url('/app/suppliers/' . $s->id . '/edit')])
            ->all();

        return compact('projects', 'clients', 'estimates', 'invoices', 'suppliers');
    }

    /** Section labels reuse the sidebar's own side.* keys rather than near-duplicate search.* ones. */
    private function groups(array $data): array
    {
        return [
            ['key' => 'projects', 'label' => t('side.projects'), 'items' => $data['projects']],
            ['key' => 'clients', 'label' => t('side.clients'), 'items' => $data['clients']],
            ['key' => 'estimates', 'label' => t('side.estimates'), 'items' => $data['estimates']],
            ['key' => 'invoices', 'label' => t('side.invoices'), 'items' => $data['invoices']],
            ['key' => 'suppliers', 'label' => t('side.suppliers'), 'items' => $data['suppliers']],
        ];
    }
}
