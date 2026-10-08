<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only company-facing browsing of the admin-curated Tenders board (government
 * procurement + giga-project opportunities). No company_id — this is global content,
 * not private CRM data (see Lead for that). Nothing here is created/edited by company users.
 */
class TenderController extends Controller
{
    public function index(Request $request): View
    {
        $category = (string) $request->input('category', '');
        $query = Tender::where('is_active', true);
        if (array_key_exists($category, Tender::TYPES)) {
            $query->where('category', $category);
        }
        $tenders = $query->orderBy('submission_deadline')->orderBy('sort_order')->get()->toArray();

        return view('app.tenders.index', [
            'tenders' => $tenders,
            'categories' => Tender::TYPES,
            'categoryFilter' => $category,
            'openCount' => Tender::where('is_active', true)->whereDate('submission_deadline', '>=', now()->format('Y-m-d'))->count(),
        ]);
    }

    public function show(int $id): View
    {
        $tender = Tender::where('is_active', true)->find($id);
        abort_if(!$tender, 404, 'Tender not found.');
        return view('app.tenders.show', ['tender' => $tender->toArray(), 'categories' => Tender::TYPES]);
    }
}
