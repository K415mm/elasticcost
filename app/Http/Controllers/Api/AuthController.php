<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'string', 'in:client,manager,sales_manager,partner,ceo'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'client',
        ]);

        $token = $user->createToken('registration-token', [$user->role])->accessToken;

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'access_token' => $token,
        ], 201);
    }

    /**
     * Login user and create token.
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('login-token', [$user->role])->accessToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'access_token' => $token,
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'role', 'email_verified_at', 'created_at']),
        ]);
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Refresh token — revoke current and issue a new one.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->token()->revoke();

        $token = $user->createToken('refreshed-token', [$user->role])->accessToken;

        return response()->json([
            'message' => 'Token refreshed successfully.',
            'access_token' => $token,
        ]);
    }
}
