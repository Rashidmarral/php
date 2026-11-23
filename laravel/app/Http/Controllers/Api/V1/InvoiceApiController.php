<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;

class InvoiceApiController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Invoice::where('company_id', $this->companyId())->orderByDesc('created_at')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        abort_if(!$invoice || $invoice->company_id !== $this->companyId(), 404, 'Invoice not found.');

        return response()->json([
            'data' => $invoice,
            'items' => InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get(),
        ]);
    }
}
