<?php

use App\Http\Controllers\Api\V1\AssessmentAnamnesisController;
use App\Http\Controllers\Api\V1\AssessmentAnthropometryController;
use App\Http\Controllers\Api\V1\AssessmentBodyCompositionController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AssessmentProgressPhotoController;
use App\Http\Controllers\Api\V1\AssessmentPhotoConsentController;
use App\Http\Controllers\Api\V1\AssessmentNeuromotorTestController;
use App\Http\Controllers\Api\V1\AssessmentVo2MaxController;
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
                | Perímetros e antropometria
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/assessments/{assessment}/anthropometry',
                    [
                        AssessmentAnthropometryController::class,
                        'show',
                    ]
                );

                Route::put(
                    '/assessments/{assessment}/anthropometry',
                    [
                        AssessmentAnthropometryController::class,
                        'update',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | VO2Max
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/assessments/{assessment}/vo2-max',
                    [
                        AssessmentVo2MaxController::class,
                        'show',
                    ]
                );

                Route::put(
                    '/assessments/{assessment}/vo2-max',
                    [
                        AssessmentVo2MaxController::class,
                        'update',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Testes neuromotores
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/assessments/{assessment}/neuromotor-tests',
                    [
                        AssessmentNeuromotorTestController::class,
                        'show',
                    ]
                );

                Route::put(
                    '/assessments/{assessment}/neuromotor-tests',
                    [
                        AssessmentNeuromotorTestController::class,
                        'update',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Fotos de evolução
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/assessments/{assessment}/progress-photos',
                    [
                        AssessmentProgressPhotoController::class,
                        'index',
                    ]
                );

                Route::put(
                    '/assessments/{assessment}/progress-photos/consent',
                    [
                        AssessmentPhotoConsentController::class,
                        'update',
                    ]
                );

                Route::delete(
                    '/assessments/{assessment}/progress-photos/consent',
                    [
                        AssessmentPhotoConsentController::class,
                        'destroy',
                    ]
                );

                Route::post(
                    '/assessments/{assessment}/progress-photos/{position}',
                    [
                        AssessmentProgressPhotoController::class,
                        'store',
                    ]
                );

                Route::get(
                    '/assessments/{assessment}/progress-photos/{position}',
                    [
                        AssessmentProgressPhotoController::class,
                        'show',
                    ]
                );

                Route::patch(
                    '/assessments/{assessment}/progress-photos/{position}',
                    [
                        AssessmentProgressPhotoController::class,
                        'update',
                    ]
                );

                Route::delete(
                    '/assessments/{assessment}/progress-photos/{position}',
                    [
                        AssessmentProgressPhotoController::class,
                        'destroy',
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
