<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],

            'device_name' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $throttleKey =
            'login:'
            . Str::lower(
                $credentials[
                    'email'
                ]
            )
            . '|'
            . $request->ip();

        if (
            RateLimiter::tooManyAttempts(
                $throttleKey,
                5
            )
        ) {
            return response()->json(
                [
                    'message' =>
                        'Muitas tentativas de login. Aguarde antes de tentar novamente.',

                    'retry_after_seconds' =>
                        RateLimiter::availableIn(
                            $throttleKey
                        ),
                ],
                429
            );
        }

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (
            ! $user
            || ! $user->active
            || ! Hash::check(
                $credentials['password'],
                $user->password,
            )
        ) {
            RateLimiter::hit(
                $throttleKey,
                60
            );

            throw ValidationException::withMessages([
                'email' => [
                    'E-mail ou senha inválidos.',
                ],
            ]);
        }

        RateLimiter::clear(
            $throttleKey
        );

        $token = $user
            ->createToken($credentials['device_name'])
            ->plainTextToken;

        AuditLogger::record(
            $request,
            'auth.login',
            subject: $user,
            metadata: [
                'device_name' =>
                    $credentials[
                        'device_name'
                    ],
            ],
            actor: $user
        );

        return response()->json([
            'token' => $token,

            'token_type' => 'Bearer',

            'user' => [
                'id' => $user->id,

                'name' => $user->name,

                'email' => $user->email,

                'role' => $user->role->value,

                'active' => $user->active,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,

                'name' => $user->name,

                'email' => $user->email,

                'role' => $user->role->value,

                'active' => $user->active,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLogger::record(
            $request,
            'auth.logout',
            subject:
                $request->user(),
            actor:
                $request->user()
        );

        $request
            ->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }
}
