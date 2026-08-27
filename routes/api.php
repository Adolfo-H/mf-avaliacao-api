<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EvaluatorController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function (): JsonResponse {
        return response()->json([
            'status' => 'ok',
            'app' => 'MF Avaliação Física',
            'api' => 'v1',
        ]);
    });

    Route::prefix('auth')->group(function (): void {
        Route::post(
            '/login',
            [AuthController::class, 'login'],
        );

        Route::middleware('auth:sanctum')->group(
            function (): void {
                Route::get(
                    '/me',
                    [AuthController::class, 'me'],
                );

                Route::post(
                    '/logout',
                    [AuthController::class, 'logout'],
                );
            },
        );
    });

    Route::middleware([
        'auth:sanctum',
        'admin',
    ])->group(function (): void {
        Route::patch(
            '/evaluators/{evaluator}/status',
            [EvaluatorController::class, 'updateStatus'],
        );

        Route::apiResource(
            'evaluators',
            EvaluatorController::class,
        )->except('destroy');
    });
});
