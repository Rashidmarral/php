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
        $material = $this->findOwned((int) $id);
        Material::update($material['id'], $this->fromInput());
        $this->flash('success', 'Material updated.');
        self::redirect('/app/materials');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $material = $this->findOwned((int) $id);
        Material::delete($material['id']);
        $this->flash('success', 'Material removed.');
        self::redirect('/app/materials');
    }

    public function importCsv(): void
    {
        $this->verifyCsrf();
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

    /** Expected header row (any order): sku, name, category, unit, unit_cost */
    private function importCsvContent(string $content): array
    {
        $companyId = Auth::companyId();
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return ['created' => 0, 'updated' => 0];
        }

        $header = array_map(fn($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        $col = array_flip($header);

        $created = 0;
        $updated = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line);
            $name = trim($row[$col['name']] ?? '');
            if ($name === '') {
                continue;
            }
            $sku = trim($row[$col['sku']] ?? '');
            $category = trim($row[$col['category']] ?? '');
            $unit = trim($row[$col['unit']] ?? '') ?: 'unit';
            $unitCost = (float) ($row[$col['unit_cost']] ?? 0);

            $existing = $sku !== ''
                ? Material::query('SELECT * FROM materials WHERE company_id = ? AND sku = ? LIMIT 1', [$companyId, $sku])->fetch()
                : Material::query('SELECT * FROM materials WHERE company_id = ? AND name = ? LIMIT 1', [$companyId, $name])->fetch();

            $data = ['name' => $name, 'category' => $category, 'unit' => $unit, 'unit_cost' => $unitCost, 'sku' => $sku, 'updated_at' => date('Y-m-d H:i:s')];

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

    private function isAllowedSheetUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        return $scheme === 'https' && in_array(strtolower((string) $host), ['docs.google.com', 'sheets.googleapis.com'], true);
    }

    private function fromInput(): array
    {
        return [
            'company_id' => Auth::companyId(),
            'supplier_id' => $this->input('supplier_id') ?: null,
            'sku' => $this->input('sku', ''),
            'name' => trim((string) $this->input('name')),
            'category' => $this->input('category', ''),
            'unit' => $this->input('unit', 'unit'),
            'unit_cost' => (float) $this->input('unit_cost', 0),
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
