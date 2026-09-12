<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $materials = DB::table('materials as m')
            ->leftJoin('suppliers as s', 's.id', '=', 'm.supplier_id')
            ->where('m.company_id', $companyId)
            ->orderBy('m.category')->orderBy('m.name')
            ->select('m.*', 's.name as supplier_name', 's.name_ar as supplier_name_ar')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.materials.index', [
            'materials' => $materials,
            'company' => Company::find($companyId)->toArray(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        return view('app.materials.form', [
            'material' => null,
            'suppliers' => Supplier::where('company_id', Auth::user()->company_id)->orderBy('name')->get()->toArray(),
            'units' => UnitOfMeasure::where('company_id', Auth::user()->company_id)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/materials/create', 'error', 'Material name is required.');
        }
        Material::create($this->fromInput($request));
        $this->flash('success', 'Material added.');
        return redirect('/app/materials');
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        return view('app.materials.form', [
            'material' => $this->findOwned($id)->toArray(),
            'suppliers' => Supplier::where('company_id', Auth::user()->company_id)->orderBy('name')->get()->toArray(),
            'units' => UnitOfMeasure::where('company_id', Auth::user()->company_id)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->update($this->fromInput($request));
        $this->flash('success', 'Material updated.');
        return redirect('/app/materials');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        $this->flash('success', 'Material removed.');
        return redirect('/app/materials');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $file = $request->file('csv');
        if (!$file || !$file->isValid()) {
            return $this->redirectWithFlash('/app/materials', 'error', 'Please choose a CSV file to import.');
        }
        $content = file_get_contents($file->getRealPath());
        $result = $this->importCsvContent((string) $content);
        $this->flash('success', "Imported {$result['created']} new and updated {$result['updated']} existing materials.");
        return redirect('/app/materials');
    }

    public function syncFromSheet(): RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);
        $url = trim((string) ($company->price_sync_url ?? ''));

        if ($url === '') {
            return $this->redirectWithFlash('/app/materials', 'error', 'Set a Google Sheets CSV link on the Integrations page first.');
        }
        if (!$this->isAllowedSheetUrl($url)) {
            return $this->redirectWithFlash('/app/materials', 'error', 'Only https://docs.google.com links are allowed for price sync.');
        }

        $context = stream_context_create(['http' => ['timeout' => 10, 'follow_location' => 1, 'max_redirects' => 3]]);
        $content = @file_get_contents($url, false, $context, 0, 2 * 1024 * 1024);

        if ($content === false) {
            return $this->redirectWithFlash('/app/materials', 'error', 'Could not reach that Google Sheet. Make sure it is published to the web as CSV.');
        }

        $result = $this->importCsvContent($content);
        $company->update(['price_sync_last_at' => now()]);
        $this->flash('success', "Synced from Google Sheets: {$result['created']} new, {$result['updated']} updated.");
        return redirect('/app/materials');
    }

    /**
     * Expected header row (any order): sku, name (or description), category, unit,
     * material_price, labor_price, supplier. For sheets that don't split cost, a single
     * unit_cost column is also accepted (treated entirely as material cost, no labor).
     */
    private function importCsvContent(string $content): array
    {
        $companyId = Auth::user()->company_id;
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return ['created' => 0, 'updated' => 0];
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        $col = array_flip($header);
        $nameCol = $col['name'] ?? $col['description'] ?? null;

        $created = 0;
        $updated = 0;
        $supplierCache = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line);
            $name = $nameCol !== null ? trim($row[$nameCol] ?? '') : '';
            if ($name === '') {
                continue;
            }
            $sku = trim($row[$col['sku']] ?? '');
            $category = trim($row[$col['category']] ?? '');
            $unit = trim($row[$col['unit']] ?? '') ?: 'unit';

            if (isset($col['material_price']) || isset($col['labor_price'])) {
                $materialCost = (float) ($row[$col['material_price']] ?? 0);
                $laborCost = (float) ($row[$col['labor_price']] ?? 0);
            } else {
                $materialCost = (float) ($row[$col['unit_cost']] ?? 0);
                $laborCost = 0.0;
            }

            $supplierId = null;
            $supplierName = trim($row[$col['supplier']] ?? '');
            if ($supplierName !== '') {
                $supplierId = $this->resolveSupplierId($supplierName, $companyId, $supplierCache);
            }

            $existing = $sku !== ''
                ? Material::where('company_id', $companyId)->where('sku', $sku)->first()
                : Material::where('company_id', $companyId)->where('name', $name)->first();

            $data = [
                'name' => $name, 'category' => $category, 'unit' => $unit,
                'material_cost' => $materialCost, 'labor_cost' => $laborCost, 'unit_cost' => $materialCost + $laborCost,
                'sku' => $sku, 'updated_at' => now(),
            ];
            if ($supplierId !== null) {
                $data['supplier_id'] = $supplierId;
            }

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                Material::create([...$data, 'company_id' => $companyId]);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /** Finds a supplier by name (case-insensitive) for this company, creating one if it doesn't exist yet. */
    private function resolveSupplierId(string $name, int $companyId, array &$cache): int
    {
        $key = strtolower($name);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $existing = Supplier::where('company_id', $companyId)->whereRaw('LOWER(name) = ?', [$key])->first();
        if ($existing) {
            return $cache[$key] = $existing->id;
        }
        $supplier = Supplier::create(['company_id' => $companyId, 'name' => $name]);
        return $cache[$key] = $supplier->id;
    }

    private function isAllowedSheetUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        return $scheme === 'https' && in_array(strtolower((string) $host), ['docs.google.com', 'sheets.googleapis.com'], true);
    }

    private function fromInput(Request $request): array
    {
        $materialCost = (float) $request->input('material_cost', 0);
        $laborCost = (float) $request->input('labor_cost', 0);
        return [
            'company_id' => Auth::user()->company_id,
            'supplier_id' => $request->input('supplier_id') ?: null,
            'sku' => $request->input('sku', ''),
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'category' => $request->input('category', ''),
            'unit' => $request->input('unit', 'unit'),
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'unit_cost' => $materialCost + $laborCost,
            // qty_on_hand is deliberately not settable here — it only ever changes via a
            // recorded stock movement (see MaterialStockController), so it always has a
            // paper trail. reorder_level has no such requirement since it's just a threshold.
            'reorder_level' => $request->filled('reorder_level') ? (float) $request->input('reorder_level') : null,
            'notes' => $request->input('notes', ''),
            'updated_at' => now(),
        ];
    }

    private function findOwned(int $id): Material
    {
        $material = Material::find($id);
        abort_if(!$material || $material->company_id !== Auth::user()->company_id, 404, 'Material not found.');
        return $material;
    }
}
