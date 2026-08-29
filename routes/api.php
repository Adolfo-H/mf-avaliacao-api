<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EvaluatorController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentPhotoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    */

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'app' => 'MF Avaliação Física',
            'api' => 'v1',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Autenticação
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/auth/login',
        [AuthController::class, 'login']
    );

    /*
    |--------------------------------------------------------------------------
    | Rotas autenticadas
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')
        ->group(function (): void {
            /*
            |--------------------------------------------------------------------------
            | Usuário autenticado
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/auth/me',
                [AuthController::class, 'me']
            );

            Route::post(
                '/auth/logout',
                [AuthController::class, 'logout']
            );

            /*
            |--------------------------------------------------------------------------
            | Alunos
            |--------------------------------------------------------------------------
            */

            Route::patch(
                '/students/{student}/status',
                [StudentController::class, 'updateStatus']
            );

            Route::apiResource(
                'students',
                StudentController::class
            )->except('destroy');

            /*
            |--------------------------------------------------------------------------
            | Foto do aluno
            |--------------------------------------------------------------------------
            |
            | POST   -> envia ou substitui a fotografia
            | GET    -> consulta a fotografia privada
            | DELETE -> remove a fotografia
            |
            */

            Route::post(
                '/students/{student}/photo',
                [StudentPhotoController::class, 'store']
            );

            Route::get(
                '/students/{student}/photo',
                [StudentPhotoController::class, 'show']
            );

            Route::delete(
                '/students/{student}/photo',
                [StudentPhotoController::class, 'destroy']
            );

            /*
            |--------------------------------------------------------------------------
            | Administração
            |--------------------------------------------------------------------------
            */

            Route::middleware('admin')
                ->group(function (): void {
                    /*
                    |--------------------------------------------------------------------------
                    | Avaliadores
                    |--------------------------------------------------------------------------
                    */

                    Route::patch(
                        '/evaluators/{evaluator}/status',
                        [
                            EvaluatorController::class,
                            'updateStatus',
                        ]
                    );

                    Route::apiResource(
                        'evaluators',
                        EvaluatorController::class
                    )->except('destroy');
                });
        });
});
