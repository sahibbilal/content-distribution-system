<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function facebookLogin(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
            'user_id' => 'required|string',
        ]);

        try {
            // Verify the Facebook access token and get user info
            $accessToken = $request->access_token;
            $fbUserId = $request->user_id;

            // Call Facebook Graph API to verify token and get user info
            $fbResponse = \Illuminate\Support\Facades\Http::get("https://graph.facebook.com/v18.0/me", [
                'fields' => 'id,name,email',
                'access_token' => $accessToken,
            ]);

            if (!$fbResponse->successful()) {
                return response()->json([
                    'message' => 'Invalid Facebook access token',
                ], 401);
            }

            $fbUser = $fbResponse->json();

            // Find or create user
            $user = User::where('email', $fbUser['email'] ?? null)
                ->orWhere(function($query) use ($fbUserId) {
                    // You might want to store Facebook user ID in a separate column
                    // For now, we'll use email
                })
                ->first();

            if (!$user) {
                // Create new user from Facebook data
                $user = User::create([
                    'name' => $fbUser['name'] ?? 'Facebook User',
                    'email' => $fbUser['email'] ?? $fbUserId . '@facebook.com',
                    'password' => Hash::make(uniqid()), // Random password since Facebook login doesn't provide one
                    'email_verified_at' => now(), // Facebook emails are considered verified
                ]);
            }

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Facebook login failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

