<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Models\Company;
use App\Models\Material;
use App\Models\Supplier;

class MaterialController extends Controller
{
    public function __construct()
    {
        Feature::requireOrRedirect('materials');
    }

    public function index(): void
    {
        $companyId = Auth::companyId();
        $materials = Material::query(
            'SELECT m.*, s.name AS supplier_name FROM materials m LEFT JOIN suppliers s ON s.id = m.supplier_id WHERE m.company_id = ? ORDER BY m.category ASC, m.name ASC',
            [$companyId]
        )->fetchAll();
        $company = Company::find($companyId);

        $this->view('user/materials/index', [
            'pageTitle' => 'Materials & Pricing',
            'materials' => $materials,
            'company' => $company,
        ], 'layouts/app');
    }

    public function create(): void
    {
        $suppliers = Supplier::where('company_id', Auth::companyId(), 'name ASC');
        $this->view('user/materials/form', ['pageTitle' => 'New Material', 'material' => null, 'suppliers' => $suppliers], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Material name is required.');
            self::redirect('/app/materials/create');
        }
        Material::create($this->fromInput());
        $this->flash('success', 'Material added.');
        self::redirect('/app/materials');
    }

    public function edit(string $id): void
    {
        $material = $this->findOwned((int) $id);
        $suppliers = Supplier::where('company_id', Auth::companyId(), 'name ASC');
        $this->view('user/materials/form', ['pageTitle' => 'Edit Material', 'material' => $material, 'suppliers' => $suppliers], 'layouts/app');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $material = $this->findOwned((int) $id);
        Material::update($material['id'], $this->fromInput());
        $this->flash('success', 'Material updated.');
        self::redirect('/app/materials');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $material = $this->findOwned((int) $id);
        Material::delete($material['id']);
        $this->flash('success', 'Material removed.');
        self::redirect('/app/materials');
    }

    public function importCsv(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please choose a CSV file to import.');
            self::redirect('/app/materials');
        }
        $content = file_get_contents($_FILES['csv']['tmp_name']);
        $result = $this->importCsvContent((string) $content);
        $this->flash('success', "Imported {$result['created']} new and updated {$result['updated']} existing materials.");
        self::redirect('/app/materials');
    }

    public function syncFromSheet(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $companyId = Auth::companyId();
        $company = Company::find($companyId);
        $url = trim((string) ($company['price_sync_url'] ?? ''));

        if ($url === '') {
            $this->flash('error', 'Set a Google Sheets CSV link on the Integrations page first.');
            self::redirect('/app/materials');
        }
        if (!$this->isAllowedSheetUrl($url)) {
            $this->flash('error', 'Only https://docs.google.com links are allowed for price sync.');
            self::redirect('/app/materials');
        }

        $context = stream_context_create(['http' => ['timeout' => 10, 'follow_location' => 1, 'max_redirects' => 3]]);
        $content = @file_get_contents($url, false, $context, 0, 2 * 1024 * 1024);

        if ($content === false) {
            $this->flash('error', 'Could not reach that Google Sheet. Make sure it is published to the web as CSV.');
            self::redirect('/app/materials');
        }

        $result = $this->importCsvContent($content);
        Company::update($companyId, ['price_sync_last_at' => date('Y-m-d H:i:s')]);
        $this->flash('success', "Synced from Google Sheets: {$result['created']} new, {$result['updated']} updated.");
        self::redirect('/app/materials');
    }

    /**
     * Expected header row (any order): sku, name (or description), category, unit,
     * material_price, labor_price, supplier. For sheets that don't split cost, a single
     * unit_cost column is also accepted (treated entirely as material cost, no labor).
     */
    private function importCsvContent(string $content): array
    {
        $companyId = Auth::companyId();
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return ['created' => 0, 'updated' => 0];
        }

        $header = array_map(fn($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
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
                ? Material::query('SELECT * FROM materials WHERE company_id = ? AND sku = ? LIMIT 1', [$companyId, $sku])->fetch()
                : Material::query('SELECT * FROM materials WHERE company_id = ? AND name = ? LIMIT 1', [$companyId, $name])->fetch();

            $data = [
                'name' => $name, 'category' => $category, 'unit' => $unit,
                'material_cost' => $materialCost, 'labor_cost' => $laborCost, 'unit_cost' => $materialCost + $laborCost,
                'sku' => $sku, 'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($supplierId !== null) {
                $data['supplier_id'] = $supplierId;
            }

            if ($existing) {
                Material::update($existing['id'], $data);
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
        $existing = Supplier::query('SELECT id FROM suppliers WHERE company_id = ? AND LOWER(name) = ?', [$companyId, $key])->fetch();
        if ($existing) {
            return $cache[$key] = (int) $existing['id'];
        }
        $id = Supplier::create(['company_id' => $companyId, 'name' => $name]);
        return $cache[$key] = $id;
    }

    private function isAllowedSheetUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        return $scheme === 'https' && in_array(strtolower((string) $host), ['docs.google.com', 'sheets.googleapis.com'], true);
    }

    private function fromInput(): array
    {
        $materialCost = (float) $this->input('material_cost', 0);
        $laborCost = (float) $this->input('labor_cost', 0);
        return [
            'company_id' => Auth::companyId(),
            'supplier_id' => $this->input('supplier_id') ?: null,
            'sku' => $this->input('sku', ''),
            'name' => trim((string) $this->input('name')),
            'category' => $this->input('category', ''),
            'unit' => $this->input('unit', 'unit'),
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'unit_cost' => $materialCost + $laborCost,
            'notes' => $this->input('notes', ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function findOwned(int $id): array
    {
        $material = Material::find($id);
        if (!$material || (int) $material['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Material not found.');
        }
        return $material;
    }
}
