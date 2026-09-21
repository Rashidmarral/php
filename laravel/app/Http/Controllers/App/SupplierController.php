<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        return view('app.suppliers.index', [
            'suppliers' => Supplier::where('company_id', Auth::user()->company_id)->orderBy('name')->get()->toArray(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        return view('app.suppliers.form', ['supplier' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/suppliers/create', 'error', t('user.suppliers.name_required'));
        }
        Supplier::create([
            'company_id' => Auth::user()->company_id,
            'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'contact_name' => $request->input('contact_name', ''),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'address' => $request->input('address', ''),
            'category' => $request->input('category', ''),
            'notes' => $request->input('notes', ''),
        ]);
        $this->flash('success', t('user.suppliers.added'));
        return redirect('/app/suppliers');
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        return view('app.suppliers.form', ['supplier' => $this->findOwned($id)->toArray()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $supplier = $this->findOwned($id);
        $supplier->update([
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'contact_name' => $request->input('contact_name', ''),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'address' => $request->input('address', ''),
            'category' => $request->input('category', ''),
            'notes' => $request->input('notes', ''),
        ]);
        $this->flash('success', t('user.suppliers.updated'));
        return redirect('/app/suppliers');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        $this->flash('success', t('user.suppliers.removed'));
        return redirect('/app/suppliers');
    }

    private function findOwned(int $id): Supplier
    {
        $supplier = Supplier::find($id);
        abort_if(!$supplier || $supplier->company_id !== Auth::user()->company_id, 404, 'Supplier not found.');
        return $supplier;
    }
}
