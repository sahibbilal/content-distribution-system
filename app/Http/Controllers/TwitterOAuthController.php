<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitterOAuthController extends Controller
{
    /**
     * Initiate X (Twitter) OAuth 2.0 flow with PKCE
     */
    public function initiate(Request $request)
    {
        $clientId = trim(env('TWITTER_CLIENT_ID', ''));
        $clientSecret = trim(env('TWITTER_CLIENT_SECRET', ''));
        $redirectUri = env('TWITTER_REDIRECT_URI', url('/api/platforms/twitter/oauth/callback'));

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'error' => 'X OAuth not configured',
                'message' => 'Please set TWITTER_CLIENT_ID and TWITTER_CLIENT_SECRET in .env file',
            ], 400);
        }

        $state = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        // Cache, not session: this token-auth API call has no session on live.
        Cache::put('twitter_oauth_state:' . $state, [
            'user_id' => $request->user()->id,
            'verifier' => $verifier,
            'account_tier' => $request->query('account_tier') === 'premium' ? 'premium' : 'free',
        ], now()->addMinutes(10));

        $authUrl = 'https://twitter.com/i/oauth2/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'tweet.read tweet.write users.read media.write offline.access',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return response()->json(['auth_url' => $authUrl]);
    }

    /**
     * Handle X (Twitter) OAuth callback
     */
    public function callback(Request $request)
    {
        $frontend = env('FRONTEND_URL', config('app.url')) . '/platforms/twitter';
        $fail = fn (string $message) => redirect($frontend . '?twitter_error=' . urlencode($message));

        if ($request->query('error')) {
            $description = $request->query('error_description', 'OAuth authorization failed');
            Log::error('Twitter OAuth error', ['error' => $request->query('error'), 'description' => $description]);
            return $fail($description);
        }

        $state = $request->query('state');
        $pending = $state ? Cache::pull('twitter_oauth_state:' . $state) : null;

        if (!$pending) {
            return $fail('Invalid state token. Please try again.');
        }

        $code = $request->query('code');
        if (!$code) {
            return $fail('Authorization code not received');
        }

        try {
            $tokenResponse = Http::asForm()
                ->withBasicAuth(trim(env('TWITTER_CLIENT_ID', '')), trim(env('TWITTER_CLIENT_SECRET', '')))
                ->timeout(30)
                ->post('https://api.twitter.com/2/oauth2/token', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => trim(env('TWITTER_REDIRECT_URI', url('/api/platforms/twitter/oauth/callback'))),
                    'code_verifier' => $pending['verifier'],
                ]);

            $token = $tokenResponse->json() ?? [];

            if (!$tokenResponse->successful() || empty($token['access_token'])) {
                Log::error('Twitter token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'error' => $token['error'] ?? 'unknown',
                ]);
                return $fail('Failed to exchange authorization code for token. ' . ($token['error_description'] ?? $tokenResponse->body()));
            }

            $me = Http::withToken($token['access_token'])->timeout(10)
                ->get('https://api.twitter.com/2/users/me')->json('data');

            if (empty($me['id'])) {
                return $fail('Could not read your X profile.');
            }

            $platform = Platform::updateOrCreate(
                ['user_id' => $pending['user_id'], 'platform_type' => 'twitter'],
                [
                    'credentials' => [
                        'access_token' => $token['access_token'],
                        'refresh_token' => $token['refresh_token'] ?? null,
                        'expires_in' => $token['expires_in'] ?? null,
                        'scope' => $token['scope'] ?? null,
                        'user_id' => $me['id'],
                        'username' => $me['username'] ?? null,
                        'account_tier' => $pending['account_tier'] ?? 'free',
                        'connected_at' => now()->toIso8601String(),
                    ],
                    'is_active' => true,
                ]
            );

            Log::info('Twitter OAuth connection successful', [
                'user_id' => $pending['user_id'],
                'platform_id' => $platform->id,
            ]);

            return redirect($frontend . '?twitter_success=1');
        } catch (\Exception $e) {
            Log::error('Twitter OAuth callback exception', ['error' => $e->getMessage()]);
            return $fail('OAuth callback failed: ' . $e->getMessage());
        }
    }
}
