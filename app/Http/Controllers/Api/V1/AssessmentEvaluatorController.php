<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AssessmentEvaluatorController extends Controller
{
    public function index(): JsonResponse
    {
        $evaluators = User::query()
            ->where(
                'role',
                UserRole::Evaluator->value
            )
            ->where('active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
            ]);

        return response()->json([
            'data' => $evaluators->map(
                fn (User $evaluator): array => [
                    'id' => $evaluator->id,
                    'name' => $evaluator->name,
                    'email' => $evaluator->email,
                ]
            )->values(),
        ]);
    }
}
