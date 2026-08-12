<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        return view('app.clients.index', [
            'clients' => Client::where('company_id', Auth::user()->company_id)->orderBy('name')->get()->toArray(),
        ]);
    }

    public function create(): View
    {
        return view('app.clients.form', ['client' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/clients/create', 'error', 'Client name is required.');
        }
        Client::create([
            'company_id' => Auth::user()->company_id,
            'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'address' => $request->input('address', ''),
        ]);
        return $this->redirectWithFlash('/app/clients', 'success', 'Client added.');
    }

    public function edit(int $id): View
    {
        return view('app.clients.form', ['client' => $this->findOwned($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $client = $this->findOwned($id);
        $client->update([
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'address' => $request->input('address', ''),
        ]);
        return $this->redirectWithFlash('/app/clients', 'success', 'Client updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        return $this->redirectWithFlash('/app/clients', 'success', 'Client removed.');
    }

    public function enablePortal(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('client_portal')) {
            return $redirect;
        }
        $client = $this->findOwned($id);

        if (empty($client->email)) {
            return $this->redirectWithFlash('/app/clients', 'error', 'Add an email address for this client before enabling portal access.');
        }

        $tempPassword = bin2hex(random_bytes(4));
        $client->update([
            'portal_enabled' => true,
            'password' => Hash::make($tempPassword),
        ]);

        return $this->redirectWithFlash('/app/clients', 'success', "Portal access enabled for {$client->email}. Temporary password: {$tempPassword} (share this securely).");
    }

    public function disablePortal(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->update(['portal_enabled' => false]);
        return $this->redirectWithFlash('/app/clients', 'success', 'Portal access disabled.');
    }

    private function findOwned(int $id): Client
    {
        $client = Client::find($id);
        abort_if(!$client || $client->company_id !== Auth::user()->company_id, 404, 'Client not found.');
        return $client;
    }
}
