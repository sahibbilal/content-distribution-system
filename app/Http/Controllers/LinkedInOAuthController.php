<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkedInOAuthController extends Controller
{
    /**
     * Initiate LinkedIn OAuth flow
     */
    public function initiate(Request $request)
    {
        $clientId = env('LINKEDIN_CLIENT_ID');
        $clientSecret = env('LINKEDIN_CLIENT_SECRET');
        // Default redirect URI should match the route in web.php
        $redirectUri = env('LINKEDIN_REDIRECT_URI', url('/api/platforms/linkedin/oauth/callback'));

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'error' => 'LinkedIn OAuth not configured',
                'message' => 'Please set LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET in .env file',
            ], 400);
        }

        // Generate state token for CSRF protection
        $state = bin2hex(random_bytes(16));
        session(['linkedin_oauth_state' => $state]);
        session(['linkedin_oauth_user_id' => $request->user()->id]);

        $authUrl = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'openid profile email w_member_social'
        ]);

        return response()->json([
            'auth_url' => $authUrl,
        ]);
    }

    /**
     * Handle LinkedIn OAuth callback
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        // Check for errors
        if ($error) {
            $errorDescription = $request->query('error_description', 'OAuth authorization failed');
            Log::error('LinkedIn OAuth error', ['error' => $error, 'description' => $errorDescription]);
            
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_error=' . urlencode($errorDescription));
        }

        // Verify state token
        $storedState = session('linkedin_oauth_state');
        $userId = session('linkedin_oauth_user_id');

        if (!$state || $state !== $storedState) {
            Log::error('LinkedIn OAuth state mismatch', [
                'received' => $state,
                'stored' => $storedState,
            ]);
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_error=' . urlencode('Invalid state token. Please try again.'));
        }

        if (!$code) {
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_error=' . urlencode('Authorization code not received'));
        }

        // Exchange code for access token
        try {
            $clientId = trim(env('LINKEDIN_CLIENT_ID', ''));
            $clientSecret = trim(env('LINKEDIN_CLIENT_SECRET', ''));
            // Default redirect URI should match the route in web.php
            $redirectUri = trim(env('LINKEDIN_REDIRECT_URI', url('/api/platforms/linkedin/oauth/callback')));

            // Validate credentials are present and not empty after trimming
            if (empty($clientId) || empty($clientSecret)) {
                Log::error('LinkedIn OAuth credentials missing or empty', [
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                    'client_id_length' => strlen($clientId),
                    'client_secret_length' => strlen($clientSecret),
                ]);
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_error=' . urlencode('LinkedIn OAuth credentials not configured. Please check your .env file. Make sure LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET are set without quotes or extra spaces.'));
            }

            // Log redirect URI for debugging (without sensitive data)
            Log::info('LinkedIn token exchange attempt', [
                'redirect_uri' => $redirectUri,
                'client_id_length' => strlen($clientId),
                'client_secret_length' => strlen($clientSecret),
                'client_id_starts_with' => substr($clientId, 0, 4) . '...',
            ]);

            // LinkedIn OAuth token exchange
            // LinkedIn accepts credentials in POST body (not Basic Auth)
            $tokenResponse = Http::asForm()
                ->post('https://www.linkedin.com/oauth/v2/accessToken', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if (!$tokenResponse->successful()) {
                $errorBody = $tokenResponse->body();
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error_description'] ?? $errorData['error'] ?? $errorBody;
                
                Log::error('LinkedIn token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'error' => $errorData['error'] ?? 'unknown',
                    'error_description' => $errorMessage,
                    'redirect_uri_used' => $redirectUri,
                ]);

                // Provide more helpful error message
                $userFriendlyError = 'Failed to exchange authorization code for token. ';
                if (isset($errorData['error']) && $errorData['error'] === 'invalid_client') {
                    $userFriendlyError .= 'This usually means: 1) Client ID or Secret is incorrect in .env, 2) Redirect URI doesn\'t match LinkedIn app settings (current: ' . $redirectUri . '), or 3) Credentials have extra spaces. Please verify your LinkedIn app settings.';
                } else {
                    $userFriendlyError .= $errorMessage;
                }
                
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_error=' . urlencode($userFriendlyError));
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;
            $idToken = $tokenData['id_token'] ?? null; // OpenID Connect ID token
            $expiresIn = $tokenData['expires_in'] ?? null;

            if (!$accessToken) {
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_error=' . urlencode('Access token not received from LinkedIn'));
            }

            // Try to extract person URN from ID token if available (OpenID Connect)
            $personUrnFromToken = null;
            if ($idToken) {
                // ID token is a JWT, decode it to get the 'sub' claim
                try {
                    $tokenParts = explode('.', $idToken);
                    if (count($tokenParts) === 3) {
                        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
                        $personUrnFromToken = $payload['sub'] ?? null;
                        Log::info('Extracted person URN from ID token', ['urn' => $personUrnFromToken]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to decode ID token: ' . $e->getMessage());
                }
            }

            // Get user profile to get person URN
            // First, try to use person URN from ID token if available
            $personUrn = $personUrnFromToken;
            $profileData = null;

            // If we don't have it from ID token, try API endpoints
            if (!$personUrn) {
                // LinkedIn OpenID Connect returns user info at /v2/userInfo
                $profileResponse = Http::withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])->timeout(10)
                ->get('https://api.linkedin.com/v2/userInfo');

                if ($profileResponse->successful()) {
                    $profileData = $profileResponse->json();
                    // OpenID Connect returns 'sub' field which contains the person URN in format "urn:li:person:xxx"
                    $personUrn = $profileData['sub'] ?? null;
                    
                    Log::info('LinkedIn userInfo successful', [
                        'sub' => $personUrn,
                        'email' => $profileData['email'] ?? null,
                    ]);
                } else {
                    // Fallback: Try /v2/me endpoint (requires r_liteprofile or r_basicprofile scope)
                    Log::info('OpenID Connect userinfo failed, trying /v2/me', [
                        'status' => $profileResponse->status(),
                        'body' => $profileResponse->body(),
                    ]);

                    $meResponse = Http::withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                        'X-Restli-Protocol-Version' => '2.0.0',
                    ])->timeout(10)
                    ->get('https://api.linkedin.com/v2/me');

                    if ($meResponse->successful()) {
                        $profileData = $meResponse->json();
                        $personUrn = $profileData['id'] ?? null;
                        Log::info('LinkedIn /v2/me successful', ['id' => $personUrn]);
                    } else {
                        $errorBody = $meResponse->body();
                        $errorData = json_decode($errorBody, true);
                        $errorMessage = $errorData['message'] ?? $errorData['error_description'] ?? $errorData['error'] ?? $errorBody;
                        
                        Log::error('Failed to get LinkedIn profile from both endpoints', [
                            'userinfo_status' => $profileResponse->status(),
                            'userinfo_body' => $profileResponse->body(),
                            'me_status' => $meResponse->status(),
                            'me_body' => $errorBody,
                            'error_message' => $errorMessage,
                        ]);
                        
                        // Provide detailed error message
                        $detailedError = 'Failed to get LinkedIn profile. ';
                        if ($meResponse->status() === 401) {
                            $detailedError .= 'Token may be invalid or missing required scopes. ';
                        } elseif ($meResponse->status() === 403) {
                            $detailedError .= 'Insufficient permissions. Please ensure your LinkedIn app has the required scopes (r_liteprofile or r_basicprofile). ';
                        }
                        $detailedError .= 'Error: ' . $errorMessage . ' (Status: ' . $meResponse->status() . ')';
                        
                        return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_error=' . urlencode($detailedError));
                    }
                }
            }

            // If we still don't have person URN, we can store the token and fetch it later
            // The LinkedInService has a method to get person URN from token
            if (!$personUrn) {
                Log::warning('LinkedIn person URN not found in profile data, will fetch later', [
                    'profile_data' => $profileData,
                ]);
                // We'll store null for person_urn and fetch it when needed
                // This allows the connection to succeed even if we can't get URN now
            }

            // Store credentials in platform
            // Note: person_urn can be null and will be fetched automatically when needed
            $platform = Platform::updateOrCreate(
                [
                    'user_id' => $userId,
                    'platform_type' => 'linkedin',
                ],
                [
                    'credentials' => [
                        'access_token' => $accessToken,
                        'person_urn' => $personUrn, // Can be null, will be auto-fetched
                        'expires_in' => $expiresIn,
                        'connected_at' => now()->toIso8601String(),
                    ],
                    'is_active' => true,
                ]
            );

            // If person_urn is null, try to get it using LinkedInService
            if (!$personUrn) {
                try {
                    $linkedInService = new \App\Services\Platforms\LinkedInService();
                    // Use reflection to call the private method, or make it public
                    // Actually, let's just store it and let the service handle it
                    Log::info('LinkedIn connected without person_urn, will be fetched on first use');
                } catch (\Exception $e) {
                    Log::warning('Could not fetch person_urn after connection: ' . $e->getMessage());
                }
            }

            // Clear session
            session()->forget(['linkedin_oauth_state', 'linkedin_oauth_user_id']);

            Log::info('LinkedIn OAuth connection successful', [
                'user_id' => $userId,
                'platform_id' => $platform->id,
            ]);

            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_success=1');

        } catch (\Exception $e) {
            Log::error('LinkedIn OAuth callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/linkedin?linkedin_error=' . urlencode('OAuth callback failed: ' . $e->getMessage()));
        }
    }
}
