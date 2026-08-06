<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Client;

class ClientController extends Controller
{
    public function index(): void
    {
        $clients = Client::where('company_id', Auth::companyId(), 'name ASC');
        $this->view('user/clients/index', ['pageTitle' => 'Clients', 'clients' => $clients], 'layouts/app');
    }

    public function create(): void
    {
        $this->view('user/clients/form', ['pageTitle' => 'New Client', 'client' => null], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Client name is required.');
            self::redirect('/app/clients/create');
        }
        Client::create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'email' => $this->input('email', ''),
            'phone' => $this->input('phone', ''),
            'address' => $this->input('address', ''),
        ]);
        $this->flash('success', 'Client added.');
        self::redirect('/app/clients');
    }

    public function edit(string $id): void
    {
        $client = $this->findOwned((int) $id);
        $this->view('user/clients/form', ['pageTitle' => 'Edit Client', 'client' => $client], 'layouts/app');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $client = $this->findOwned((int) $id);
        Client::update($client['id'], [
            'name' => trim((string) $this->input('name')),
            'email' => $this->input('email', ''),
            'phone' => $this->input('phone', ''),
            'address' => $this->input('address', ''),
        ]);
        $this->flash('success', 'Client updated.');
        self::redirect('/app/clients');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $client = $this->findOwned((int) $id);
        Client::delete($client['id']);
        $this->flash('success', 'Client removed.');
        self::redirect('/app/clients');
    }

    public function enablePortal(string $id): void
    {
        $this->verifyCsrf();
        $client = $this->findOwned((int) $id);

        if (empty($client['email'])) {
            $this->flash('error', 'Add an email address for this client before enabling portal access.');
            self::redirect('/app/clients');
        }

        $tempPassword = bin2hex(random_bytes(4));
        Client::update($client['id'], [
            'portal_enabled' => 1,
            'password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
        ]);

        $this->flash('success', "Portal access enabled for {$client['email']}. Temporary password: {$tempPassword} (share this securely).");
        self::redirect('/app/clients');
    }

    public function disablePortal(string $id): void
    {
        $this->verifyCsrf();
        $client = $this->findOwned((int) $id);
        Client::update($client['id'], ['portal_enabled' => 0]);
        $this->flash('success', 'Portal access disabled.');
        self::redirect('/app/clients');
    }

    private function findOwned(int $id): array
    {
        $client = Client::find($id);
        if (!$client || (int) $client['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Client not found.');
        }
        return $client;
    }
}
