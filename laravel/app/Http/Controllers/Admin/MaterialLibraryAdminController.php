<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLibraryItem;
use App\Models\MaterialLibraryItemCityPrice;
use App\Models\MaterialLibraryPriceImportLog;
use App\Support\SaudiCities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MaterialLibraryAdminController extends Controller
{
    /** Fixed, consistent category list used both here and on the company-facing browse page. */
    public const CATEGORIES = [
        'Concrete & Steel', 'Masonry', 'Finishing & Tiling', 'Plumbing', 'Electrical',
        'HVAC', 'Paint & Coatings', 'Doors & Windows', 'Waterproofing & Insulation',
        'Aggregates', 'Sanitaryware', 'Glass & Aluminum',
    ];

    public function index(Request $request): View
    {
        $items = MaterialLibraryItem::query()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('admin.material-library.index', [
            'items' => $items,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/admin/material-library', 'error', t('admin.material_library.name_required'));
        }
        MaterialLibraryItem::create([...$this->fromInput($request), 'is_active' => true]);
        return $this->redirectWithFlash('/admin/material-library', 'success', t('admin.material_library.added'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        MaterialLibraryItem::findOrFail($id)->update([
            ...$this->fromInput($request),
            'is_active' => (bool) $request->input('is_active'),
        ]);
        return $this->redirectWithFlash('/admin/material-library', 'success', t('admin.material_library.updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        MaterialLibraryItem::destroy($id);
        return $this->redirectWithFlash('/admin/material-library', 'success', t('admin.material_library.deleted'));
    }

    /**
     * Per-city price-book browse/edit page: there is no live Saudi material-price API
     * (GASTAT only publishes region-level PDF bulletins), so this admin-maintained
     * per-city override layer on material_library_items.material_cost is the
     * approved stand-in. Defaults to the first canonical city when none is given.
     */
    public function cityPrices(Request $request): View
    {
        $city = trim((string) $request->query('city', ''));
        if ($city === '' || ! SaudiCities::has($city)) {
            $cities = SaudiCities::all();
            $city = $cities[0]['en'] ?? '';
        }

        $items = MaterialLibraryItem::where('is_active', true)
            ->orderBy('category')->orderBy('sort_order')->orderBy('id')
            ->get();

        $overrides = $city !== ''
            ? MaterialLibraryItemCityPrice::where('city', $city)->pluck('unit_cost', 'material_library_item_id')
            : collect();

        return view('admin.material-library.city-prices', [
            'items' => $items,
            'overrides' => $overrides,
            'city' => $city,
            'cities' => SaudiCities::withExtra($city),
        ]);
    }

    /**
     * Upserts/clears one city's price overrides from the editable grid on
     * cityPrices(): a blank or zero-or-less submitted value means "use the base
     * price" and removes any existing override for that (item, city) pair.
     */
    public function updateCityPrices(Request $request): RedirectResponse
    {
        $city = trim((string) $request->input('city', ''));
        if ($city === '' || ! SaudiCities::has($city)) {
            return $this->redirectWithFlash('/admin/material-library/city-prices', 'error', t('admin.material_library.city_required'));
        }

        $validIds = MaterialLibraryItem::pluck('id')->flip();
        $touched = 0;
        foreach ((array) $request->input('prices', []) as $itemId => $value) {
            $itemId = (int) $itemId;
            if ($itemId <= 0 || ! $validIds->has($itemId)) {
                continue;
            }
            $float = trim((string) $value) === '' ? 0.0 : (float) $value;
            if ($float <= 0) {
                MaterialLibraryItemCityPrice::where('material_library_item_id', $itemId)->where('city', $city)->delete();
                $touched++;
                continue;
            }
            MaterialLibraryItemCityPrice::updateOrCreate(
                ['material_library_item_id' => $itemId, 'city' => $city],
                ['unit_cost' => $float, 'source' => 'manual', 'updated_by' => Auth::id()]
            );
            $touched++;
        }

        return $this->redirectWithFlash(
            '/admin/material-library/city-prices?city='.urlencode($city),
            'success',
            t('admin.material_library.city_prices_updated', ['count' => $touched])
        );
    }

    /**
     * CSV import for one city's price book: two columns, sku_or_name,unit_cost — the
     * header row (if present) is detected by checking whether the first row's second
     * column parses as a number, so a file with no header still imports correctly.
     * Matches each row first by exact sku (case-insensitive), then by exact name;
     * unmatched rows are skipped (counted, never fatal to the rest of the import).
     */
    public function importCityPricesCsv(Request $request): RedirectResponse
    {
        $city = trim((string) $request->input('city', ''));
        if ($city === '' || ! SaudiCities::has($city)) {
            return $this->redirectWithFlash('/admin/material-library/city-prices', 'error', t('admin.material_library.city_required'));
        }

        $file = $request->file('csv');
        if (! $file || ! $file->isValid()) {
            return $this->redirectWithFlash('/admin/material-library/city-prices?city='.urlencode($city), 'error', t('admin.material_library.csv_file_required'));
        }

        $lines = preg_split('/\r\n|\r|\n/', trim((string) file_get_contents($file->getRealPath())));
        $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== ''));
        if (! empty($lines)) {
            $firstRow = str_getcsv($lines[0]);
            if (! is_numeric(trim((string) ($firstRow[1] ?? '')))) {
                array_shift($lines);
            }
        }

        $bySku = [];
        $byName = [];
        foreach (MaterialLibraryItem::where('is_active', true)->get() as $item) {
            if (! empty($item->sku)) {
                $bySku[mb_strtolower(trim($item->sku))] = $item;
            }
            $byName[mb_strtolower(trim($item->name))] = $item;
        }

        $matched = 0;
        $skipped = 0;
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            $key = mb_strtolower(trim((string) ($row[0] ?? '')));
            $priceRaw = trim((string) ($row[1] ?? ''));
            $item = $bySku[$key] ?? $byName[$key] ?? null;
            if ($key === '' || ! is_numeric($priceRaw) || (float) $priceRaw <= 0 || ! $item) {
                $skipped++;
                continue;
            }
            MaterialLibraryItemCityPrice::updateOrCreate(
                ['material_library_item_id' => $item->id, 'city' => $city],
                ['unit_cost' => (float) $priceRaw, 'source' => 'csv_import', 'updated_by' => Auth::id()]
            );
            $matched++;
        }

        MaterialLibraryPriceImportLog::create([
            'city' => $city,
            'filename' => $file->getClientOriginalName(),
            'row_count' => $matched + $skipped,
            'matched_count' => $matched,
            'skipped_count' => $skipped,
            'imported_by' => Auth::id(),
        ]);

        $this->flash('success', t('admin.material_library.city_price_import_summary', [
            'matched' => $matched, 'skipped' => $skipped, 'total' => $matched + $skipped,
        ]));

        return redirect('/admin/material-library/city-prices?city='.urlencode($city));
    }

    private function fromInput(Request $request): array
    {
        return [
            'sku' => trim((string) $request->input('sku', '')) ?: null,
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')) ?: null,
            'category' => $request->input('category', '') ?: 'Other',
            'unit' => $request->input('unit', 'each') ?: 'each',
            'material_cost' => (float) $request->input('material_cost', 0),
            'labor_cost' => (float) $request->input('labor_cost', 0),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
