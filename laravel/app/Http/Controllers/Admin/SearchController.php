<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /** Top N per entity type — this is a quick jump-to, not a full reporting query. */
    private const PER_TYPE_LIMIT = 5;

    /** Same reasoning as App\SearchController: too short a query is almost always a
     * keystroke mid-type, not a deliberate search. */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Cross-entity "jump to" search for the platform admin panel. Unlike the
     * company side, this is deliberately platform-wide (an admin legitimately
     * jumps across ALL companies/tickets, not one tenant's own records) and is
     * scoped to the entity types an admin actually wants to jump to quickly:
     * Companies by name, and platform-facing support tickets by subject —
     * not the exact same five company-side entity types.
     */
    public function search(Request $request): View|JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $data = mb_strlen($q) >= self::MIN_QUERY_LENGTH ? $this->runSearch($q) : ['companies' => [], 'tickets' => []];
        $groups = $this->groups($data);
        $total = array_sum(array_map(fn ($g) => count($g['items']), $groups));

        if ($request->query('format') === 'json') {
            return response()->json(['q' => $q, 'groups' => $groups, 'total' => $total]);
        }

        return view('admin.search.results', [
            'q' => $q,
            'groups' => $groups,
            'total' => $total,
            'minLength' => self::MIN_QUERY_LENGTH,
        ]);
    }

    private function runSearch(string $q): array
    {
        $companies = Company::where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('name_ar', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'name', 'name_ar'])
            ->map(fn ($c) => ['id' => $c->id, 'label' => local($c, 'name'), 'url' => url('/admin/companies/' . $c->id)])
            ->all();

        $tickets = SupportTicket::where('channel', 'platform')
            ->where('subject', 'like', "%{$q}%")
            ->orderByDesc('created_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'subject'])
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->subject, 'url' => url('/admin/support/' . $t->id)])
            ->all();

        return compact('companies', 'tickets');
    }

    /** Section labels reuse the admin sidebar's own aside.* keys rather than near-duplicate search.* ones. */
    private function groups(array $data): array
    {
        return [
            ['key' => 'companies', 'label' => t('aside.companies'), 'items' => $data['companies']],
            ['key' => 'tickets', 'label' => t('aside.support'), 'items' => $data['tickets']],
        ];
    }
}
