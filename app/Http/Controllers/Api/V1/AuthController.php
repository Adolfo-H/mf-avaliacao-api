<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            throw ValidationException::withMessages([
                'email' => [
                    'E-mail ou senha inválidos.',
                ],
            ]);
        }

        $token = $user
            ->createToken($credentials['device_name'])
            ->plainTextToken;

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
        $request
            ->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }
}
