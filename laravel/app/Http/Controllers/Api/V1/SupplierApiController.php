<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierApiController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Supplier::where('company_id', $this->companyId())->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $supplier = Supplier::create(['company_id' => $this->companyId(), ...$data]);
        return response()->json(['data' => $supplier], 201);
    }
}
