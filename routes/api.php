<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EvaluatorController;
use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
     * Health check público.
     */
    Route::get('/health', function (): JsonResponse {
        return response()->json([
            'status' => 'ok',
            'app' => 'MF Avaliação Física',
            'api' => 'v1',
        ]);
    });

    /*
     * Autenticação.
     */
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

    /*
     * Rotas que exigem usuário autenticado.
     */
    Route::middleware('auth:sanctum')->group(
        function (): void {
            /*
             * Alunos.
             *
             * Por enquanto qualquer usuário autenticado
             * pode acessar estas rotas.
             *
             * Mais adiante refinaremos as permissões
             * por perfil.
             */
            Route::patch(
                '/students/{student}/status',
                [
                    StudentController::class,
                    'updateStatus',
                ],
            );

            Route::apiResource(
                'students',
                StudentController::class,
            )->except('destroy');

            /*
             * Administração.
             *
             * Somente administrador pode gerenciar
             * avaliadores.
             */
            Route::middleware('admin')->group(
                function (): void {
                    Route::patch(
                        '/evaluators/{evaluator}/status',
                        [
                            EvaluatorController::class,
                            'updateStatus',
                        ],
                    );

                    Route::apiResource(
                        'evaluators',
                        EvaluatorController::class,
                    )->except('destroy');
                },
            );
        },
    );
});
