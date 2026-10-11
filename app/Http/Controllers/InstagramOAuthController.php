<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramOAuthController extends Controller
{
    /**
     * Initiate "Instagram API with Instagram Login" (no Facebook Page needed)
     */
    public function initiate(Request $request)
    {
        $appId = trim(env('INSTAGRAM_APP_ID', ''));
        $appSecret = trim(env('INSTAGRAM_APP_SECRET', ''));
        $redirectUri = trim(env('INSTAGRAM_REDIRECT_URI', url('/api/platforms/instagram/oauth/callback')));

        if (!$appId || !$appSecret) {
            return response()->json([
                'error' => 'Instagram OAuth not configured',
                'message' => 'Please set INSTAGRAM_APP_ID and INSTAGRAM_APP_SECRET in .env file',
            ], 400);
        }

        $state = bin2hex(random_bytes(16));
        // Cache, not session: this token-auth API call has no session on live.
        Cache::put('instagram_oauth_state:' . $state, $request->user()->id, now()->addMinutes(10));

        $authUrl = 'https://www.instagram.com/oauth/authorize?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_content_publish',
            'state' => $state,
        ]);

        return response()->json(['auth_url' => $authUrl]);
    }

    /**
     * Handle Instagram OAuth callback
     */
    public function callback(Request $request)
    {
        $frontend = env('FRONTEND_URL', config('app.url')) . '/platforms/instagram';
        $fail = fn (string $message) => redirect($frontend . '?instagram_error=' . urlencode($message));

        if ($request->query('error')) {
            $description = $request->query('error_description', 'OAuth authorization failed');
            Log::error('Instagram OAuth error', ['error' => $request->query('error'), 'description' => $description]);
            return $fail($description);
        }

        $state = $request->query('state');
        $userId = $state ? Cache::pull('instagram_oauth_state:' . $state) : null;

        if (!$userId) {
            return $fail('Invalid state token. Please try again.');
        }

        // Instagram appends "#_" to the redirect code; it is not part of the code
        $code = rtrim((string) $request->query('code'), '#_');
        if ($code === '') {
            return $fail('Authorization code not received');
        }

        $appId = trim(env('INSTAGRAM_APP_ID', ''));
        $appSecret = trim(env('INSTAGRAM_APP_SECRET', ''));

        try {
            // 1. Code -> short-lived token (1 hour)
            $shortResponse = Http::asForm()->timeout(30)->post('https://api.instagram.com/oauth/access_token', [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'grant_type' => 'authorization_code',
                'redirect_uri' => trim(env('INSTAGRAM_REDIRECT_URI', url('/api/platforms/instagram/oauth/callback'))),
                'code' => $code,
            ]);
            $short = $shortResponse->json() ?? [];
            $short = $short['data'][0] ?? $short;

            if (!$shortResponse->successful() || empty($short['access_token'])) {
                Log::error('Instagram token exchange failed', ['status' => $shortResponse->status()]);
                return $fail('Failed to exchange authorization code. ' . ($short['error_message'] ?? $shortResponse->body()));
            }

            // 2. Short-lived -> long-lived token (60 days)
            $longResponse = Http::timeout(30)->get('https://graph.instagram.com/access_token', [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => $appSecret,
                'access_token' => $short['access_token'],
            ]);
            $long = $longResponse->json() ?? [];

            if (!$longResponse->successful() || empty($long['access_token'])) {
                Log::error('Instagram long-lived token exchange failed', ['status' => $longResponse->status()]);
                return $fail('Failed to get a long-lived token. ' . ($long['error']['message'] ?? $longResponse->body()));
            }

            // 3. Profile (the id used for publishing is "user_id")
            $me = Http::timeout(10)->get('https://graph.instagram.com/v23.0/me', [
                'fields' => 'user_id,username,account_type',
                'access_token' => $long['access_token'],
            ])->json() ?? [];

            $igId = $me['user_id'] ?? $me['id'] ?? $short['user_id'] ?? null;
            if (!$igId) {
                return $fail('Could not read your Instagram profile.');
            }

            $platform = Platform::updateOrCreate(
                ['user_id' => $userId, 'platform_type' => 'instagram'],
                [
                    'credentials' => [
                        'access_token' => $long['access_token'],
                        'expires_in' => $long['expires_in'] ?? 5184000,
                        'instagram_user_id' => (string) $igId,
                        'username' => $me['username'] ?? null,
                        'account_type' => $me['account_type'] ?? null,
                        'connected_at' => now()->toIso8601String(),
                    ],
                    'is_active' => true,
                ]
            );

            Log::info('Instagram OAuth connection successful', ['user_id' => $userId, 'platform_id' => $platform->id]);

            return redirect($frontend . '?instagram_success=1');
        } catch (\Exception $e) {
            Log::error('Instagram OAuth callback exception', ['error' => $e->getMessage()]);
            return $fail('OAuth callback failed: ' . $e->getMessage());
        }
    }
}
