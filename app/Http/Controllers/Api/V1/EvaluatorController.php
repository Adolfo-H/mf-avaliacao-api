<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEvaluatorRequest;
use App\Http\Requests\Api\V1\UpdateEvaluatorRequest;
use App\Http\Requests\Api\V1\UpdateEvaluatorStatusRequest;
use App\Http\Resources\Api\V1\EvaluatorResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EvaluatorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $evaluators = User::query()
            ->where('role', UserRole::Evaluator->value)
            ->with('evaluatorProfile')
            ->orderBy('name')
            ->paginate(20);

        return EvaluatorResource::collection($evaluators);
    }

    public function store(
        StoreEvaluatorRequest $request,
    ): EvaluatorResource {
        $data = $request->validated();

        $evaluator = DB::transaction(function () use ($data): User {
            $active = $data['active'] ?? true;

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Evaluator,
                'active' => $active,
            ]);

            $user->evaluatorProfile()->create([
                'phone' => $data['phone'] ?? null,
                'professional_registration' => $data['professional_registration'] ?? null,
                'specialty' => $data['specialty'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'active' => $active,
            ]);

            return $user->load('evaluatorProfile');
        });

        return new EvaluatorResource($evaluator);
    }

    public function show(User $evaluator): EvaluatorResource
    {
        $this->ensureEvaluator($evaluator);

        return new EvaluatorResource(
            $evaluator->load('evaluatorProfile'),
        );
    }

    public function update(
        UpdateEvaluatorRequest $request,
        User $evaluator,
    ): EvaluatorResource {
        $this->ensureEvaluator($evaluator);

        $data = $request->validated();

        DB::transaction(function () use ($evaluator, $data): void {
            $userData = [];

            foreach (['name', 'email'] as $field) {
                if (array_key_exists($field, $data)) {
                    $userData[$field] = $data[$field];
                }
            }

            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }

            if ($userData !== []) {
                $evaluator->update($userData);
            }

            $profileData = [];

            foreach ([
                'phone',
                'professional_registration',
                'specialty',
                'company_name',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $profileData[$field] = $data[$field];
                }
            }

            if ($profileData !== []) {
                $evaluator
                    ->evaluatorProfile()
                    ->updateOrCreate(
                        ['user_id' => $evaluator->id],
                        $profileData,
                    );
            }
        });

        return new EvaluatorResource(
            $evaluator
                ->fresh()
                ->load('evaluatorProfile'),
        );
    }

    public function updateStatus(
        UpdateEvaluatorStatusRequest $request,
        User $evaluator,
    ): EvaluatorResource {
        $this->ensureEvaluator($evaluator);

        $active = $request->boolean('active');

        DB::transaction(function () use (
            $evaluator,
            $active,
        ): void {
            $evaluator->update([
                'active' => $active,
            ]);

            $evaluator
                ->evaluatorProfile()
                ->updateOrCreate(
                    ['user_id' => $evaluator->id],
                    [
                        'active' => $active,
                    ],
                );

            if (! $active) {
                $evaluator->tokens()->delete();
            }
        });

        return new EvaluatorResource(
            $evaluator
                ->fresh()
                ->load('evaluatorProfile'),
        );
    }

    private function ensureEvaluator(User $user): void
    {
        abort_unless(
            $user->role === UserRole::Evaluator,
            404,
            'Avaliador não encontrado.',
        );
    }
}
