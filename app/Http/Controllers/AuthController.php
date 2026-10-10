<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $emailSent = true;
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                // The account exists; the user can request another email from the login page.
                report($e);
                $emailSent = false;
            }

            return response()->json([
                'message' => $emailSent
                    ? 'Account created. Check your email to verify your address before logging in.'
                    : 'Account created, but the verification email could not be sent. Use "Resend verification email" on the login page.',
                'email_sent' => $emailSent,
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

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address before logging in.',
                'needs_verification' => true,
            ], 403);
        }

        $expiresAt = $request->boolean('remember') ? now()->addDays(30) : now()->addDay();
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$request->hasValidSignature()
            || !$user
            || !hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            return redirect('/login?verify_error=1');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect('/login?verified=1');
    }

    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if ($user && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Same response either way so this can't be used to discover registered emails.
        return response()->json([
            'message' => 'If that account exists and is not verified, a new verification email has been sent.',
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

