<?php

use App\Http\Controllers\Api\V1\AssessmentAnamnesisController;
use App\Http\Controllers\Api\V1\AssessmentBodyCompositionController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AssessmentEvaluatorController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EvaluatorController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentPhotoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Health
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/health',
        function () {
            return response()->json([
                'status' => 'ok',
                'app' => 'MF Avaliação Física',
                'api' => 'v1',
            ]);
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Autenticação
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/auth/login',
        [
            AuthController::class,
            'login',
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Rotas autenticadas
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'auth:sanctum'
    )
        ->group(
            function (): void {
                /*
                |--------------------------------------------------------------------------
                | Sessão
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/auth/me',
                    [
                        AuthController::class,
                        'me',
                    ]
                );

                Route::post(
                    '/auth/logout',
                    [
                        AuthController::class,
                        'logout',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Alunos
                |--------------------------------------------------------------------------
                */

                Route::patch(
                    '/students/{student}/status',
                    [
                        StudentController::class,
                        'updateStatus',
                    ]
                );

                Route::apiResource(
                    'students',
                    StudentController::class
                )->except(
                    'destroy'
                );

                /*
                |--------------------------------------------------------------------------
                | Fotos dos alunos
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/students/{student}/photo',
                    [
                        StudentPhotoController::class,
                        'store',
                    ]
                );

                Route::get(
                    '/students/{student}/photo',
                    [
                        StudentPhotoController::class,
                        'show',
                    ]
                );

                Route::delete(
                    '/students/{student}/photo',
                    [
                        StudentPhotoController::class,
                        'destroy',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Avaliadores disponíveis
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/assessment-evaluators',
                    [
                        AssessmentEvaluatorController::class,
                        'index',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Avaliações
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/assessments/{assessment}/complete',
                    [
                        AssessmentController::class,
                        'complete',
                    ]
                );

                Route::apiResource(
                    'assessments',
                    AssessmentController::class
                )->except(
                    'destroy'
                );

                /*
                |--------------------------------------------------------------------------
                | Anamnese
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/assessments/{assessment}/anamnesis',
                    [
                        AssessmentAnamnesisController::class,
                        'show',
                    ]
                );

                Route::put(
                    '/assessments/{assessment}/anamnesis',
                    [
                        AssessmentAnamnesisController::class,
                        'update',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Composição corporal
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/assessments/{assessment}/body-composition',
                    [
                        AssessmentBodyCompositionController::class,
                        'show',
                    ]
                );

                Route::put(
                    '/assessments/{assessment}/body-composition',
                    [
                        AssessmentBodyCompositionController::class,
                        'update',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Administração
                |--------------------------------------------------------------------------
                */

                Route::middleware(
                    'admin'
                )
                    ->group(
                        function (): void {
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
                            )->except(
                                'destroy'
                            );
                        }
                    );
            }
        );
});
