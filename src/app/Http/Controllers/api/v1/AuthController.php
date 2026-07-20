<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ]);

        $user = new User();
        $user->name = $request->input('name');
        $user->username = $request->input('username');
        $user->email = $request->input('email');
        $user->password = Hash::make($request->input('password'));
        $user->save();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email
                ]
            ]
        ], 201);

    }
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string']
        ]);

        $user = User::where('username', '=', $request->input('username'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Credentials'
            ], 404);
        }

        $accessToken = $user->createToken('auth_token')->plainTextToken;
        $plainRefreshToken = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email
                ],
                'authentication_token' => $accessToken,
                'refresh_token' => $plainRefreshToken,
            ]
        ]);
    }

    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $refreshToken = RefreshToken::where(
            'token',
            hash('sha256', $request->refresh_token)
        )->first();

        if (
            !$refreshToken ||
            $refreshToken->expires_at->isPast()
        ) {
            return response()->json([
                'message' => 'Invalid refresh token'
            ], 401);
        }

        $user = $refreshToken->user;

        $user->tokens()->delete();

        $accessToken = $user
            ->createToken('auth_token')
            ->plainTextToken;

        $refreshToken->delete();

        $newRefresh = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $newRefresh),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'authentication_token' => $accessToken,
            'refresh_token' => $newRefresh,
        ]);
    }
}
