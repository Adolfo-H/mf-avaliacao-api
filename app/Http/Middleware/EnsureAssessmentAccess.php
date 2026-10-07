<?php

namespace App\Http\Middleware;

use App\Models\Assessment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssessmentAccess
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $assessment =
            $request->route(
                'assessment'
            );

        /*
         * Rotas sem {assessment}, como
         * login, index e store, seguem
         * normalmente.
         */
        if (
            ! $assessment
                instanceof Assessment
        ) {
            return $next($request);
        }

        $user =
            $request->user();

        if (! $user) {
            abort(
                401,
                'Não autenticado.'
            );
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if (
            $user->isEvaluator()
            &&
            $assessment->evaluator_id
                === $user->id
        ) {
            return $next($request);
        }

        abort(
            403,
            'Você não possui permissão para acessar esta avaliação.'
        );
    }
}
