<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokOAuthController extends Controller
{
    /**
     * Initiate TikTok OAuth flow
     */
    public function initiate(Request $request)
    {
        $clientKey = env('TIKTOK_CLIENT_KEY');
        $clientSecret = env('TIKTOK_CLIENT_SECRET');
        $redirectUri = env('TIKTOK_REDIRECT_URI', url('/api/platforms/tiktok/oauth/callback'));

        if (!$clientKey || !$clientSecret) {
            return response()->json([
                'error' => 'TikTok OAuth not configured',
                'message' => 'Please set TIKTOK_CLIENT_KEY and TIKTOK_CLIENT_SECRET in .env file',
            ], 400);
        }

        // Generate state token for CSRF protection
        $state = bin2hex(random_bytes(16));
        session(['tiktok_oauth_state' => $state]);
        session(['tiktok_oauth_user_id' => $request->user()->id]);

        // Required scopes for video upload and publishing
        $scopes = [
            'user.info.basic',      // Basic user info
            'video.upload',         // Upload videos
            'video.publish',        // Publish videos directly
        ];

        $authUrl = 'https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
            'client_key' => $clientKey,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state,
        ]);

        return response()->json([
            'auth_url' => $authUrl,
        ]);
    }

    /**
     * Handle TikTok OAuth callback
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        // Check for errors
        if ($error) {
            $errorDescription = $request->query('error_description', 'OAuth authorization failed');
            Log::error('TikTok OAuth error', ['error' => $error, 'description' => $errorDescription]);
            
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/tiktok?tiktok_error=' . urlencode($errorDescription));
        }

        // Verify state token
        $storedState = session('tiktok_oauth_state');
        $userId = session('tiktok_oauth_user_id');

        if (!$state || $state !== $storedState) {
            Log::error('TikTok OAuth state mismatch', [
                'received' => $state,
                'stored' => $storedState,
            ]);
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/tiktok?tiktok_error=' . urlencode('Invalid state token. Please try again.'));
        }

        if (!$code) {
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/tiktok?tiktok_error=' . urlencode('Authorization code not received'));
        }

        // Exchange code for access token
        try {
            $clientKey = trim(env('TIKTOK_CLIENT_KEY', ''));
            $clientSecret = trim(env('TIKTOK_CLIENT_SECRET', ''));
            $redirectUri = trim(env('TIKTOK_REDIRECT_URI', url('/api/platforms/tiktok/oauth/callback')));

            // Validate credentials are present
            if (empty($clientKey) || empty($clientSecret)) {
                Log::error('TikTok OAuth credentials missing', [
                    'has_client_key' => !empty($clientKey),
                    'has_client_secret' => !empty($clientSecret),
                ]);
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/tiktok?tiktok_error=' . urlencode('TikTok OAuth credentials not configured. Please check your .env file.'));
            }

            // Exchange authorization code for access token
            $tokenResponse = Http::asForm()
                ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                    'client_key' => $clientKey,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                ]);

            if (!$tokenResponse->successful()) {
                $errorBody = $tokenResponse->body();
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error_description'] ?? $errorData['error'] ?? $errorBody;
                
                Log::error('TikTok token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'error' => $errorData['error'] ?? 'unknown',
                    'error_description' => $errorMessage,
                ]);

                $userFriendlyError = 'Failed to exchange authorization code for token. ';
                if (isset($errorData['error']) && $errorData['error'] === 'invalid_client') {
                    $userFriendlyError .= 'This usually means: 1) Client Key or Secret is incorrect in .env, 2) Redirect URI doesn\'t match TikTok app settings (current: ' . $redirectUri . '), or 3) Credentials have extra spaces.';
                } else {
                    $userFriendlyError .= $errorMessage;
                }
                
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/tiktok?tiktok_error=' . urlencode($userFriendlyError));
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;
            $openId = $tokenData['open_id'] ?? null;
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? null;
            $scope = $tokenData['scope'] ?? null;

            if (!$accessToken || !$openId) {
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/tiktok?tiktok_error=' . urlencode('Access token or Open ID not received from TikTok'));
            }

            // Get user info to verify connection
            try {
                $userInfoResponse = Http::withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])->timeout(10)
                ->get('https://open.tiktokapis.com/v2/user/info/', [
                    'fields' => 'open_id,union_id,avatar_url,display_name',
                ]);

                $userInfo = null;
                if ($userInfoResponse->successful()) {
                    $userInfo = $userInfoResponse->json();
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get TikTok user info: ' . $e->getMessage());
            }

            // Store credentials in platform
            $platform = Platform::updateOrCreate(
                [
                    'user_id' => $userId,
                    'platform_type' => 'tiktok',
                ],
                [
                    'credentials' => [
                        'access_token' => $accessToken,
                        'open_id' => $openId,
                        'refresh_token' => $refreshToken,
                        'expires_in' => $expiresIn,
                        'scope' => $scope,
                        'connected_at' => now()->toIso8601String(),
                    ],
                    'is_active' => true,
                ]
            );

            // Clear session
            session()->forget(['tiktok_oauth_state', 'tiktok_oauth_user_id']);

            Log::info('TikTok OAuth connection successful', [
                'user_id' => $userId,
                'platform_id' => $platform->id,
                'open_id' => $openId,
                'has_user_info' => !empty($userInfo),
            ]);

            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/tiktok?tiktok_success=1');

        } catch (\Exception $e) {
            Log::error('TikTok OAuth callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/tiktok?tiktok_error=' . urlencode('OAuth callback failed: ' . $e->getMessage()));
        }
    }
}
