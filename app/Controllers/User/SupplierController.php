<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function __construct()
    {
        Feature::requireOrRedirect('suppliers');
    }

    public function index(): void
    {
        $suppliers = Supplier::where('company_id', Auth::companyId(), 'name ASC');
        $this->view('user/suppliers/index', ['pageTitle' => 'Suppliers', 'suppliers' => $suppliers], 'layouts/app');
    }

    public function create(): void
    {
        $this->view('user/suppliers/form', ['pageTitle' => 'New Supplier', 'supplier' => null], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Supplier name is required.');
            self::redirect('/app/suppliers/create');
        }
        Supplier::create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'contact_name' => $this->input('contact_name', ''),
            'email' => $this->input('email', ''),
            'phone' => $this->input('phone', ''),
            'address' => $this->input('address', ''),
            'category' => $this->input('category', ''),
            'notes' => $this->input('notes', ''),
        ]);
        $this->flash('success', 'Supplier added.');
        self::redirect('/app/suppliers');
    }

    public function edit(string $id): void
    {
        $supplier = $this->findOwned((int) $id);
        $this->view('user/suppliers/form', ['pageTitle' => 'Edit Supplier', 'supplier' => $supplier], 'layouts/app');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $supplier = $this->findOwned((int) $id);
        Supplier::update($supplier['id'], [
            'name' => trim((string) $this->input('name')),
            'contact_name' => $this->input('contact_name', ''),
            'email' => $this->input('email', ''),
            'phone' => $this->input('phone', ''),
            'address' => $this->input('address', ''),
            'category' => $this->input('category', ''),
            'notes' => $this->input('notes', ''),
        ]);
        $this->flash('success', 'Supplier updated.');
        self::redirect('/app/suppliers');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $supplier = $this->findOwned((int) $id);
        Supplier::delete($supplier['id']);
        $this->flash('success', 'Supplier removed.');
        self::redirect('/app/suppliers');
    }

    private function findOwned(int $id): array
    {
        $supplier = Supplier::find($id);
        if (!$supplier || (int) $supplier['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Supplier not found.');
        }
        return $supplier;
    }
}
