<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientApiController;
use App\Http\Controllers\Api\V1\EstimateApiController;
use App\Http\Controllers\Api\V1\InvoiceApiController;
use App\Http\Controllers\Api\V1\MaterialApiController;
use App\Http\Controllers\Api\V1\ProjectApiController;
use App\Http\Controllers\Api\V1\SupplierApiController;
use App\Http\Controllers\Api\V1\VendorBillApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API (v1) — token-authenticated (Sanctum), built as the foundation for the
| future mobile app and third-party integrations. Every endpoint is scoped
| to the authenticated user's company; there is no cross-company access.
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/projects', [ProjectApiController::class, 'index']);
        Route::post('/projects', [ProjectApiController::class, 'store']);
        Route::get('/projects/{id}', [ProjectApiController::class, 'show']);

        Route::get('/clients', [ClientApiController::class, 'index']);
        Route::post('/clients', [ClientApiController::class, 'store']);
        Route::get('/clients/{id}', [ClientApiController::class, 'show']);

        Route::get('/estimates', [EstimateApiController::class, 'index']);
        Route::get('/estimates/{id}', [EstimateApiController::class, 'show']);

        Route::get('/invoices', [InvoiceApiController::class, 'index']);
        Route::get('/invoices/{id}', [InvoiceApiController::class, 'show']);

        Route::get('/materials', [MaterialApiController::class, 'index']);
        Route::post('/materials', [MaterialApiController::class, 'store']);

        Route::get('/suppliers', [SupplierApiController::class, 'index']);
        Route::post('/suppliers', [SupplierApiController::class, 'store']);

        Route::get('/vendor-bills', [VendorBillApiController::class, 'index']);
        Route::post('/vendor-bills', [VendorBillApiController::class, 'store']);
    });
});
