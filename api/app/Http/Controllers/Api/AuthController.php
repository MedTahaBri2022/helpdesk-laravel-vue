<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Self-service registration always creates a customer. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->issueToken($user, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Same answer for an unknown email and a wrong password: the endpoint
        // cannot be used to find out which addresses have an account.
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        return $this->issueToken($user);
    }

    public function logout(Request $request): Response
    {
        // Revokes the token used for this request only; other devices stay signed in.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function issueToken(User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('spa')->plainTextToken,
            'user' => new UserResource($user),
        ], $status);
    }
}
