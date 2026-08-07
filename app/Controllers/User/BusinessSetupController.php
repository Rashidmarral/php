<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\BuildingType;
use App\Models\ClientType;
use App\Models\ContactType;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;

class BusinessSetupController extends Controller
{
    /** @var array<string,array{model:class-string,label:string,defaults:string[]}> */
    private const SIMPLE_TYPES = [
        'building-types' => ['model' => BuildingType::class, 'label' => 'Building Types', 'defaults' => [
            'Single Family Residential', 'Villa', 'Duplex', 'Apartment Building', 'Commercial', 'Industrial', 'Renovation / Remodel',
        ]],
        'contact-types' => ['model' => ContactType::class, 'label' => 'Contact Types', 'defaults' => [
            'Client', 'Subcontractor', 'Supplier', 'Consultant', 'Architect', 'Government / Municipality',
        ]],
        'client-types' => ['model' => ClientType::class, 'label' => 'Client Types', 'defaults' => [
            'Individual Homeowner', 'Real Estate Developer', 'Government Entity', 'Commercial Business', 'Property Management Company',
        ]],
    ];

    public function index(): void
    {
        self::redirect('/app/business-setup/building-types');
    }

    public function simple(string $type): void
    {
        $config = $this->config($type);
        $rows = $config['model']::where('company_id', Auth::companyId(), 'sort_order ASC, id ASC');
        $this->view('user/business-setup/simple', [
            'pageTitle' => $config['label'],
            'type' => $type,
            'config' => $config,
            'rows' => $rows,
        ], 'layouts/app');
    }

    public function storeSimple(string $type): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $config = $this->config($type);

        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Name is required.');
            self::redirect('/app/business-setup/' . $type);
        }

        $config['model']::create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
        $this->flash('success', 'Added.');
        self::redirect('/app/business-setup/' . $type);
    }

    public function updateSimple(string $type, string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $config = $this->config($type);
        $row = $this->findOwned($config['model'], (int) $id);

        $config['model']::update($row['id'], [
            'name' => trim((string) $this->input('name')),
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
        $this->flash('success', 'Updated.');
        self::redirect('/app/business-setup/' . $type);
    }

    public function destroySimple(string $type, string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $config = $this->config($type);
        $row = $this->findOwned($config['model'], (int) $id);
        $config['model']::delete($row['id']);
        $this->flash('success', 'Removed.');
        self::redirect('/app/business-setup/' . $type);
    }

    public function loadDefaultsSimple(string $type): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $config = $this->config($type);
        $companyId = Auth::companyId();
        $existingNames = array_column($config['model']::where('company_id', $companyId), 'name');

        foreach ($config['defaults'] as $i => $name) {
            if (!in_array($name, $existingNames, true)) {
                $config['model']::create(['company_id' => $companyId, 'name' => $name, 'sort_order' => $i]);
            }
        }
        $this->flash('success', 'Suggested defaults added.');
        self::redirect('/app/business-setup/' . $type);
    }

    // ---------------- Units of Measure ----------------

    public function units(): void
    {
        $this->view('user/business-setup/units', [
            'pageTitle' => 'Units of Measure',
            'rows' => UnitOfMeasure::where('company_id', Auth::companyId(), 'sort_order ASC, id ASC'),
        ], 'layouts/app');
    }

    public function storeUnit(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $code = trim((string) $this->input('code'));
        $name = trim((string) $this->input('name'));
        if ($code === '' || $name === '') {
            $this->flash('error', 'Code and name are required.');
            self::redirect('/app/business-setup/units-of-measure');
        }
        UnitOfMeasure::create([
            'company_id' => Auth::companyId(), 'code' => $code, 'name' => $name,
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
        $this->flash('success', 'Unit added.');
        self::redirect('/app/business-setup/units-of-measure');
    }

    public function updateUnit(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $row = $this->findOwned(UnitOfMeasure::class, (int) $id);
        UnitOfMeasure::update($row['id'], [
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
        $this->flash('success', 'Unit updated.');
        self::redirect('/app/business-setup/units-of-measure');
    }

    public function destroyUnit(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $row = $this->findOwned(UnitOfMeasure::class, (int) $id);
        UnitOfMeasure::delete($row['id']);
        $this->flash('success', 'Unit removed.');
        self::redirect('/app/business-setup/units-of-measure');
    }

    public function loadDefaultUnits(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $companyId = Auth::companyId();
        $defaults = [
            ['sqm', 'Square meter'], ['m3', 'Cubic meter'], ['lm', 'Linear meter'], ['each', 'Each'],
            ['lot', 'Lot / Job'], ['hr', 'Hour'], ['ton', 'Ton'], ['point', 'Point (electrical/plumbing)'], ['kg', 'Kilogram'],
        ];
        foreach ($defaults as $i => [$code, $name]) {
            $exists = UnitOfMeasure::query('SELECT id FROM units_of_measure WHERE company_id = ? AND code = ?', [$companyId, $code])->fetch();
            if (!$exists) {
                UnitOfMeasure::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'sort_order' => $i]);
            }
        }
        $this->flash('success', 'Suggested units added.');
        self::redirect('/app/business-setup/units-of-measure');
    }

    // ---------------- Tax Rates ----------------

    public function taxRates(): void
    {
        $this->view('user/business-setup/tax-rates', [
            'pageTitle' => 'Tax Rates',
            'rows' => TaxRate::where('company_id', Auth::companyId(), 'sort_order ASC, id ASC'),
        ], 'layouts/app');
    }

    public function storeTaxRate(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $companyId = Auth::companyId();
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Name is required.');
            self::redirect('/app/business-setup/tax-rates');
        }
        $isDefault = $this->input('is_default') ? 1 : 0;
        if ($isDefault) {
            TaxRate::query('UPDATE tax_rates SET is_default = 0 WHERE company_id = ?', [$companyId]);
        }
        TaxRate::create([
            'company_id' => $companyId, 'name' => $name,
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'rate_percent' => (float) $this->input('rate_percent', 0),
            'is_default' => $isDefault,
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
        $this->flash('success', 'Tax rate added.');
        self::redirect('/app/business-setup/tax-rates');
    }

    public function updateTaxRate(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $row = $this->findOwned(TaxRate::class, (int) $id);
        $isDefault = $this->input('is_default') ? 1 : 0;
        if ($isDefault) {
            TaxRate::query('UPDATE tax_rates SET is_default = 0 WHERE company_id = ?', [Auth::companyId()]);
        }
        TaxRate::update($row['id'], [
            'name' => trim((string) $this->input('name')),
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'rate_percent' => (float) $this->input('rate_percent', 0),
            'is_default' => $isDefault,
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
        $this->flash('success', 'Tax rate updated.');
        self::redirect('/app/business-setup/tax-rates');
    }

    public function destroyTaxRate(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $row = $this->findOwned(TaxRate::class, (int) $id);
        TaxRate::delete($row['id']);
        $this->flash('success', 'Tax rate removed.');
        self::redirect('/app/business-setup/tax-rates');
    }

    /** @return array{model:class-string,label:string,defaults:string[]} */
    private function config(string $type): array
    {
        if (!isset(self::SIMPLE_TYPES[$type])) {
            http_response_code(404);
            die('Unknown business setup type.');
        }
        return self::SIMPLE_TYPES[$type];
    }

    private function findOwned(string $modelClass, int $id): array
    {
        $row = $modelClass::find($id);
        if (!$row || (int) $row['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Not found.');
        }
        return $row;
    }
}
