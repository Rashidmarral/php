<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\AssemblyItem;
use App\Models\CostCode;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Company-level CRUD for Assemblies — a reusable group of estimate line
 * items (e.g. "Standard Interior Wall") defined once and inserted into an
 * estimate scaled by a single applied quantity (see the assembly-picker
 * partial and _line-items.blade.php for the insert-into-estimate UI this
 * feeds). Mirrors MaterialController's ownership-scoping and validation
 * style; create()/store() and edit()/update() parse the nested items the
 * same array-field way EstimateController::store() parses line items.
 */
class AssemblyController extends Controller
{
    private const ITEM_TYPES = ['material', 'labor', 'equipment', 'subcontractor', 'other'];

    public function index(): View
    {
        $companyId = Auth::user()->company_id;
        $assemblies = Assembly::where('company_id', $companyId)
            ->withCount('items')
            ->orderBy('name')
            ->get()
            ->toArray();

        return view('app.assemblies.index', ['assemblies' => $assemblies]);
    }

    public function create(): View
    {
        $companyId = Auth::user()->company_id;

        return view('app.assemblies.form', [
            'assembly' => null,
            'items' => [],
            'units' => UnitOfMeasure::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'costCodeDivisions' => CostCode::selectableFor($companyId)->toArray(),
            'itemTypes' => self::ITEM_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        [$fields, $error] = $this->fromInput($request);
        if ($error) {
            return $this->redirectWithFlash('/app/assemblies/create', 'error', $error);
        }
        [$items, $error] = $this->itemsFromInput($request, $companyId);
        if ($error) {
            return $this->redirectWithFlash('/app/assemblies/create', 'error', $error);
        }

        $assembly = Assembly::create([...$fields, 'company_id' => $companyId]);
        foreach ($items as $item) {
            AssemblyItem::create(['assembly_id' => $assembly->id, ...$item]);
        }

        $this->flash('success', t('user.assemblies.added'));

        return redirect('/app/assemblies');
    }

    public function edit(int $id): View
    {
        $assembly = $this->findOwned($id);
        $companyId = $assembly->company_id;

        return view('app.assemblies.form', [
            'assembly' => $assembly->toArray(),
            'items' => $assembly->items()->get()->toArray(),
            'units' => UnitOfMeasure::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'costCodeDivisions' => CostCode::selectableFor($companyId)->toArray(),
            'itemTypes' => self::ITEM_TYPES,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $assembly = $this->findOwned($id);
        $companyId = $assembly->company_id;
        [$fields, $error] = $this->fromInput($request);
        if ($error) {
            return $this->redirectWithFlash('/app/assemblies/'.$id.'/edit', 'error', $error);
        }
        [$items, $error] = $this->itemsFromInput($request, $companyId);
        if ($error) {
            return $this->redirectWithFlash('/app/assemblies/'.$id.'/edit', 'error', $error);
        }

        $assembly->update($fields);
        // Nested items have no stable per-row identity in this form (plain
        // add/remove-row JS, no hidden item id), so the whole set is replaced
        // on every save — the same "delete then recreate" approach the admin
        // estimate-template editor avoids only because it edits one row at a
        // time; here the editor is always the full set at once.
        AssemblyItem::where('assembly_id', $assembly->id)->delete();
        foreach ($items as $item) {
            AssemblyItem::create(['assembly_id' => $assembly->id, ...$item]);
        }

        $this->flash('success', t('user.assemblies.updated'));

        return redirect('/app/assemblies');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $assembly = $this->findOwned($id);
        AssemblyItem::where('assembly_id', $assembly->id)->delete();
        $assembly->delete();

        $this->flash('success', t('user.assemblies.removed'));

        return redirect('/app/assemblies');
    }

    /**
     * Shared by store()/update(): validates and normalizes the assembly's own
     * fields. Rejects (rather than silently casting) anything that doesn't
     * parse, same discipline as MaterialController::fromInput().
     *
     * @return array{0: array<string, mixed>, 1: ?string} [fields, error]
     */
    private function fromInput(Request $request): array
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return [[], t('user.assemblies.name_required')];
        }
        $baseUom = trim((string) $request->input('base_uom'));
        if ($baseUom === '') {
            return [[], t('user.assemblies.base_uom_required')];
        }

        return [[
            'name' => mb_substr($name, 0, 150),
            'name_ar' => mb_substr(trim((string) $request->input('name_ar', '')), 0, 150),
            'base_uom' => mb_substr($baseUom, 0, 20),
            'description' => mb_substr(trim((string) $request->input('description', '')), 0, 255),
            'is_active' => (bool) $request->input('is_active', true),
        ], null];
    }

    /**
     * Parses the nested item_*[] arrays the create/edit form submits — same
     * array-field shape and bounds as EstimateController::store()'s line
     * items (qty 0.01–999999.99, cost 0–9999999.99), just qty_per_unit
     * in place of qty. A blank description row is silently skipped (an
     * empty trailing row left by the add-row JS), but at least one real
     * item is required.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: ?string} [items, error]
     */
    private function itemsFromInput(Request $request, int $companyId): array
    {
        $descriptions = $request->input('item_description', []);
        $descriptionsAr = $request->input('item_description_ar', []);
        $types = $request->input('item_type', []);
        $uoms = $request->input('item_uom', []);
        $qtys = $request->input('item_qty_per_unit', []);
        $costs = $request->input('item_cost', []);
        $costCodes = $request->input('item_cost_code', []);

        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qtyPerUnit = $this->validatedNumber($qtys[$i] ?? null, 0.01, 999999.99);
            if ($qtyPerUnit === null) {
                return [[], t('user.assemblies.qty_per_unit_invalid')];
            }
            $cost = $this->validatedNumberOrDefault($costs[$i] ?? 0, 0, 9999999.99, 0.0);
            if ($cost === null) {
                return [[], t('user.assemblies.unit_cost_invalid')];
            }

            $items[] = [
                'description' => mb_substr($desc, 0, 255),
                'description_ar' => mb_substr(trim((string) ($descriptionsAr[$i] ?? '')), 0, 255),
                'item_type' => in_array($types[$i] ?? '', self::ITEM_TYPES, true) ? $types[$i] : 'material',
                'uom' => mb_substr(trim((string) ($uoms[$i] ?? '')) ?: 'each', 0, 20),
                'qty_per_unit' => $qtyPerUnit,
                'unit_cost' => $cost,
                'cost_code_id' => CostCode::ownedById(isset($costCodes[$i]) ? (int) $costCodes[$i] : null, $companyId)?->id,
                'sort_order' => count($items),
            ];
        }

        if (empty($items)) {
            return [[], t('user.assemblies.item_required')];
        }

        return [$items, null];
    }

    private function findOwned(int $id): Assembly
    {
        $assembly = Assembly::find($id);
        abort_if(! $assembly || $assembly->company_id !== Auth::user()->company_id, 404, 'Assembly not found.');

        return $assembly;
    }

    /** Rejects a non-numeric or out-of-range value — see MaterialController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
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
}
