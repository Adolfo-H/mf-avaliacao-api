<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentEvaluatorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $evaluators = User::query()
            ->whereIn('role',
                $request->user()?->isAdmin()
                    ? [UserRole::Evaluator->value, UserRole::Admin->value]
                    : [UserRole::Evaluator->value]
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
