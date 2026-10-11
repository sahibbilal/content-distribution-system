<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeOAuthController extends Controller
{
    /**
     * Initiate Google OAuth for YouTube (upload + read channel name)
     */
    public function initiate(Request $request)
    {
        $clientId = trim(env('YOUTUBE_CLIENT_ID', ''));
        $clientSecret = trim(env('YOUTUBE_CLIENT_SECRET', ''));
        $redirectUri = trim(env('YOUTUBE_REDIRECT_URI', url('/api/platforms/youtube/oauth/callback')));

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'error' => 'YouTube OAuth not configured',
                'message' => 'Please set YOUTUBE_CLIENT_ID and YOUTUBE_CLIENT_SECRET in .env file',
            ], 400);
        }

        $state = bin2hex(random_bytes(16));
        // Cache, not session: this token-auth API call has no session on live.
        Cache::put('youtube_oauth_state:' . $state, $request->user()->id, now()->addMinutes(10));

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
            // offline + consent so Google always returns a refresh token
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return response()->json(['auth_url' => $authUrl]);
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(Request $request)
    {
        $frontend = env('FRONTEND_URL', config('app.url')) . '/platforms/youtube';
        $fail = fn (string $message) => redirect($frontend . '?youtube_error=' . urlencode($message));

        if ($request->query('error')) {
            Log::error('YouTube OAuth error', ['error' => $request->query('error')]);
            return $fail('Authorization failed: ' . $request->query('error'));
        }

        $state = $request->query('state');
        $userId = $state ? Cache::pull('youtube_oauth_state:' . $state) : null;

        if (!$userId) {
            return $fail('Invalid state token. Please try again.');
        }

        $code = $request->query('code');
        if (!$code) {
            return $fail('Authorization code not received');
        }

        try {
            $tokenResponse = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => trim(env('YOUTUBE_CLIENT_ID', '')),
                'client_secret' => trim(env('YOUTUBE_CLIENT_SECRET', '')),
                'redirect_uri' => trim(env('YOUTUBE_REDIRECT_URI', url('/api/platforms/youtube/oauth/callback'))),
                'grant_type' => 'authorization_code',
            ]);
            $token = $tokenResponse->json() ?? [];

            if (!$tokenResponse->successful() || empty($token['access_token'])) {
                Log::error('YouTube token exchange failed', ['status' => $tokenResponse->status()]);
                return $fail('Failed to exchange authorization code. ' . ($token['error_description'] ?? $tokenResponse->body()));
            }
            if (empty($token['refresh_token'])) {
                return $fail('Google did not return a refresh token. Remove this app at myaccount.google.com/permissions and try again.');
            }

            $channelResponse = Http::withToken($token['access_token'])->timeout(15)
                ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true']);
            $channel = $channelResponse->json('items.0');

            if (!$channel) {
                return $fail('No YouTube channel found on this Google account. Create a channel on YouTube first.');
            }

            $platform = Platform::updateOrCreate(
                ['user_id' => $userId, 'platform_type' => 'youtube'],
                [
                    'credentials' => [
                        'refresh_token' => $token['refresh_token'],
                        'channel_id' => $channel['id'],
                        'channel_title' => $channel['snippet']['title'] ?? null,
                        'connected_at' => now()->toIso8601String(),
                    ],
                    'is_active' => true,
                ]
            );

            Log::info('YouTube OAuth connection successful', ['user_id' => $userId, 'platform_id' => $platform->id]);

            return redirect($frontend . '?youtube_success=1');
        } catch (\Exception $e) {
            Log::error('YouTube OAuth callback exception', ['error' => $e->getMessage()]);
            return $fail('OAuth callback failed: ' . $e->getMessage());
        }
    }
}
