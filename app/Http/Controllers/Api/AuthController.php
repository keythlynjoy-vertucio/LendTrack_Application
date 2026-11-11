<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Incorrect email or password.'],
            ]);
        }

        abort_unless(
            $user->isAdmin() || $user->isStaff(),
            403,
            'Your account does not have access.'
        );

        $expiresAt = now()->addDays(7);

        $token = $user->createToken(
            $data['device_name'],
            ['*'],
            $expiresAt
        )->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->only([
                'id', 'name', 'email', 'role',
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}