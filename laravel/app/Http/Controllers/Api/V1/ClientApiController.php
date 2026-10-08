<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientApiController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Client::where('company_id', $this->companyId())->orderBy('name')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        $client = Client::find($id);
        abort_if(!$client || $client->company_id !== $this->companyId(), 404, 'Client not found.');
        return response()->json(['data' => $client]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $client = Client::create(['company_id' => $this->companyId(), ...$data]);
        return response()->json(['data' => $client], 201);
    }
}
