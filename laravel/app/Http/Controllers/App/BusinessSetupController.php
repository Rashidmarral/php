<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BuildingType;
use App\Models\ClientType;
use App\Models\ContactType;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BusinessSetupController extends Controller
{
    /** @var array<string,array{model:class-string<Model>,label:string,defaults:string[]}> */
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

    public function index(): RedirectResponse
    {
        return redirect('/app/business-setup/building-types');
    }

    public function simple(string $type): View
    {
        $config = $this->config($type);
        $rows = $config['model']::where('company_id', Auth::user()->company_id)->orderBy('sort_order')->orderBy('id')->get()->toArray();

        return view('app.business-setup.simple', [
            'type' => $type,
            'config' => $config,
            'rows' => $rows,
        ]);
    }

    public function storeSimple(Request $request, string $type): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $config = $this->config($type);

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/business-setup/' . $type, 'error', t('user.business_setup.name_required'));
        }

        $config['model']::create([
            'company_id' => Auth::user()->company_id,
            'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        $this->flash('success', t('user.business_setup.added'));
        return redirect('/app/business-setup/' . $type);
    }

    public function updateSimple(Request $request, string $type, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $config = $this->config($type);
        $row = $this->findOwned($config['model'], $id);

        $row->update([
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        $this->flash('success', t('user.business_setup.updated'));
        return redirect('/app/business-setup/' . $type);
    }

    public function destroySimple(string $type, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $config = $this->config($type);
        $this->findOwned($config['model'], $id)->delete();
        $this->flash('success', t('user.business_setup.removed'));
        return redirect('/app/business-setup/' . $type);
    }

    public function loadDefaultsSimple(string $type): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $config = $this->config($type);
        $companyId = Auth::user()->company_id;
        $existingNames = $config['model']::where('company_id', $companyId)->pluck('name')->all();

        foreach ($config['defaults'] as $i => $name) {
            if (!in_array($name, $existingNames, true)) {
                $config['model']::create(['company_id' => $companyId, 'name' => $name, 'sort_order' => $i]);
            }
        }
        $this->flash('success', t('user.business_setup.suggested_defaults_added'));
        return redirect('/app/business-setup/' . $type);
    }

    // ---------------- Units of Measure ----------------

    public function units(): View
    {
        return view('app.business-setup.units', [
            'rows' => UnitOfMeasure::where('company_id', Auth::user()->company_id)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
        ]);
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $code = trim((string) $request->input('code'));
        $name = trim((string) $request->input('name'));
        if ($code === '' || $name === '') {
            return $this->redirectWithFlash('/app/business-setup/units-of-measure', 'error', t('user.business_setup.code_and_name_required'));
        }
        UnitOfMeasure::create([
            'company_id' => Auth::user()->company_id, 'code' => $code, 'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        $this->flash('success', t('user.business_setup.unit_added'));
        return redirect('/app/business-setup/units-of-measure');
    }

    public function updateUnit(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $row = $this->findOwned(UnitOfMeasure::class, $id);
        $row->update([
            'code' => trim((string) $request->input('code')),
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        $this->flash('success', t('user.business_setup.unit_updated'));
        return redirect('/app/business-setup/units-of-measure');
    }

    public function destroyUnit(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $this->findOwned(UnitOfMeasure::class, $id)->delete();
        $this->flash('success', t('user.business_setup.unit_removed'));
        return redirect('/app/business-setup/units-of-measure');
    }

    public function loadDefaultUnits(): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $defaults = [
            ['sqm', 'Square meter'], ['m3', 'Cubic meter'], ['lm', 'Linear meter'], ['each', 'Each'],
            ['lot', 'Lot / Job'], ['hr', 'Hour'], ['ton', 'Ton'], ['point', 'Point (electrical/plumbing)'], ['kg', 'Kilogram'],
        ];
        foreach ($defaults as $i => [$code, $name]) {
            $exists = UnitOfMeasure::where('company_id', $companyId)->where('code', $code)->exists();
            if (!$exists) {
                UnitOfMeasure::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'sort_order' => $i]);
            }
        }
        $this->flash('success', t('user.business_setup.suggested_units_added'));
        return redirect('/app/business-setup/units-of-measure');
    }

    // ---------------- Tax Rates ----------------

    public function taxRates(): View
    {
        return view('app.business-setup.tax-rates', [
            'rows' => TaxRate::where('company_id', Auth::user()->company_id)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
        ]);
    }

    public function storeTaxRate(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/business-setup/tax-rates', 'error', t('user.business_setup.name_required'));
        }
        $isDefault = $request->boolean('is_default');
        if ($isDefault) {
            TaxRate::where('company_id', $companyId)->update(['is_default' => false]);
        }
        TaxRate::create([
            'company_id' => $companyId, 'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'rate_percent' => (float) $request->input('rate_percent', 0),
            'is_default' => $isDefault,
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        $this->flash('success', t('user.business_setup.tax_rate_added'));
        return redirect('/app/business-setup/tax-rates');
    }

    public function updateTaxRate(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $row = $this->findOwned(TaxRate::class, $id);
        $isDefault = $request->boolean('is_default');
        if ($isDefault) {
            TaxRate::where('company_id', Auth::user()->company_id)->update(['is_default' => false]);
        }
        $row->update([
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'rate_percent' => (float) $request->input('rate_percent', 0),
            'is_default' => $isDefault,
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        $this->flash('success', t('user.business_setup.tax_rate_updated'));
        return redirect('/app/business-setup/tax-rates');
    }

    public function destroyTaxRate(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $this->findOwned(TaxRate::class, $id)->delete();
        $this->flash('success', t('user.business_setup.tax_rate_removed'));
        return redirect('/app/business-setup/tax-rates');
    }

    /** @return array{model:class-string<Model>,label:string,defaults:string[]} */
    private function config(string $type): array
    {
        abort_if(!isset(self::SIMPLE_TYPES[$type]), 404, 'Unknown business setup type.');
        return self::SIMPLE_TYPES[$type];
    }

    /** @param class-string<Model> $modelClass */
    private function findOwned(string $modelClass, int $id): Model
    {
        $row = $modelClass::find($id);
        abort_if(!$row || $row->company_id !== Auth::user()->company_id, 404, 'Not found.');
        return $row;
    }
}
