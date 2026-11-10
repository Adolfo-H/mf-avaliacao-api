<?php

use App\Http\Controllers\Api\V1\MfBillingAutomationController;
use App\Http\Controllers\Api\V1\MfBillingLocalWorkerController;
use App\Http\Middleware\EnsureMfBillingAccess;
use App\Http\Middleware\EnsureMfBillingLocalWorker;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/billing')->group(function (): void {
    Route::middleware(['auth:sanctum', EnsureMfBillingAccess::class, 'admin', 'throttle:60,1'])
        ->prefix('automation')->group(function (): void {
            Route::get('settings', [MfBillingAutomationController::class, 'settings']);
            Route::put('settings', [MfBillingAutomationController::class, 'update']);
            Route::get('history', [MfBillingAutomationController::class, 'history']);
        });

    // O conector NÃO utiliza credenciais do usuário nem expõe sessão do WhatsApp na web.
    Route::middleware(['throttle:30,1', EnsureMfBillingLocalWorker::class])
        ->prefix('local-worker')->group(function (): void {
            Route::post('heartbeat', [MfBillingLocalWorkerController::class, 'heartbeat']);
            Route::post('claim', [MfBillingLocalWorkerController::class, 'claim']);
            Route::post('jobs/{job}/check', [MfBillingLocalWorkerController::class, 'check']);
            Route::post('jobs/{job}/ack', [MfBillingLocalWorkerController::class, 'ack']);
            Route::post('opt-out', [MfBillingLocalWorkerController::class, 'optOut']);
        });
});
