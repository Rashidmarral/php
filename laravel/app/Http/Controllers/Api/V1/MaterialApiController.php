<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialApiController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Material::where('company_id', $this->companyId())->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_cost' => ['nullable', 'numeric'],
            'material_cost' => ['nullable', 'numeric'],
            'labor_cost' => ['nullable', 'numeric'],
            'supplier_id' => ['nullable', 'integer'],
        ]);

        $material = Material::create(['company_id' => $this->companyId(), ...$data]);
        return response()->json(['data' => $material], 201);
    }
}
