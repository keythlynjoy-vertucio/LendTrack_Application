<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BorrowerApiController;
use App\Http\Controllers\Api\CollectionApiController;
use App\Http\Controllers\Api\LoanApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PsgcController;
use App\Http\Controllers\Api\ReportApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ManagementApiController;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [ManagementApiController::class, 'dashboard']);
    Route::get('/payments/{payment}/receipt', [ManagementApiController::class, 'receipt']);
    Route::middleware('admin')->group(function () {
        Route::get('/activity-logs', [ManagementApiController::class, 'activity']);
        Route::get('/discrepancies', [ManagementApiController::class, 'discrepancies']);
        Route::get('/records/{type}', [ManagementApiController::class, 'records']);
        Route::post('/records/{type}/{id}', [ManagementApiController::class, 'changeRecord'])->whereNumber('id');
    });
    Route::post('/staff', [\App\Http\Controllers\Api\StaffApiController::class, 'store'])
        ->middleware(['admin', 'throttle:10,1']);
    // Account
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Borrowers
    Route::get('/borrowers', [
        BorrowerApiController::class,
        'index',
    ]);

    Route::post('/borrowers', [
        BorrowerApiController::class,
        'store',
    ]);

    Route::get('/borrowers/{borrower}', [
        BorrowerApiController::class,
        'show',
    ]);

    Route::patch('/borrowers/{borrower}', [
        BorrowerApiController::class,
        'update',
    ]);

    Route::get('/psgc/regions/{regionCode}/cities-municipalities', [PsgcController::class, 'regionCities'])->where('regionCode', '[0-9]{10}');
    // PSGC address selections
    Route::get('/psgc/provinces', [
        PsgcController::class,
        'provinces',
    ]);

    Route::get(
        '/psgc/provinces/{provinceCode}/cities-municipalities',
        [PsgcController::class, 'cities']
    )->where('provinceCode', '[0-9]{10}');

    Route::get(
        '/psgc/cities-municipalities/{cityCode}/barangays',
        [PsgcController::class, 'barangays']
    )->where('cityCode', '[0-9]{10}');

    // Loans
    Route::get('/loans', [
        LoanApiController::class,
        'index',
    ]);

    Route::post('/loans', [
        LoanApiController::class,
        'store',
    ]);

    Route::get('/loans/{loan}', [
        LoanApiController::class,
        'show',
    ]);

    Route::get('/loans/{loan}/payment-quote', \App\Http\Controllers\Api\PaymentQuoteController::class);
    // Payments
    Route::get('/loans/{loan}/payments', [
        LoanApiController::class,
        'payments',
    ]);

    Route::post('/loans/{loan}/payments', [
        PaymentApiController::class,
        'store',
    ]);

    // Collection monitoring for Admin and Staff
    Route::get('/collections', [
        CollectionApiController::class,
        'index',
    ]);

    // Admin-only reports
    Route::middleware('admin')->group(function () {
        Route::get('/reports/collections', [
            ReportApiController::class,
            'collections',
        ]);
    });
});