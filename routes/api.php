<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\PipelineStageController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadNoteController;
use App\Http\Controllers\Api\LeadTaskController;
use App\Http\Controllers\Api\ReportController;

Route::group([
    'prefix' => 'auth'
], function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api')->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api')->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->middleware('auth:api')->name('me');
});

Route::middleware('auth:api')->prefix('v1')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::apiResource('inventory', InventoryController::class);
    Route::post('inventory/{inventoryItem}/adjust', [InventoryController::class, 'adjust']);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('sales', SaleController::class)->except(['destroy']);
    Route::post('sales/{sale}/payments', [SaleController::class, 'addPayment']);
    Route::get('sales/{sale}/payments', [SaleController::class, 'payments']);

    Route::get('cash-sessions', [CashSessionController::class, 'index']);
    Route::post('cash-sessions/open', [CashSessionController::class, 'open']);
    Route::get('cash-sessions/{cashSession}', [CashSessionController::class, 'show']);
    Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close']);
    Route::post('cash-sessions/{cashSession}/movements', [CashSessionController::class, 'addMovement']);

    Route::apiResource('pipeline-stages', PipelineStageController::class);
    Route::apiResource('leads', LeadController::class);
    Route::post('lead-notes', [LeadNoteController::class, 'store']);
    Route::delete('lead-notes/{leadNote}', [LeadNoteController::class, 'destroy']);
    Route::post('lead-tasks', [LeadTaskController::class, 'store']);
    Route::put('lead-tasks/{leadTask}', [LeadTaskController::class, 'update']);
    Route::delete('lead-tasks/{leadTask}', [LeadTaskController::class, 'destroy']);

    Route::get('reports/summary', [ReportController::class, 'summary']);
});
