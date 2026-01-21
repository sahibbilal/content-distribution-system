<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookOAuthController extends Controller
{
    /**
     * Initiate Facebook OAuth flow
     */
    public function initiate(Request $request)
    {
        $appId = trim(env('FACEBOOK_APP_ID', ''));
        $appSecret = trim(env('FACEBOOK_APP_SECRET', ''));
        $redirectUri = trim(env('FACEBOOK_REDIRECT_URI', url('/api/platforms/facebook/oauth/callback')));

        if (empty($appId) || empty($appSecret)) {
            return response()->json([
                'error' => 'Facebook OAuth not configured',
                'message' => 'Please set FACEBOOK_APP_ID and FACEBOOK_APP_SECRET in .env file',
            ], 400);
        }

        // Generate state token for CSRF protection
        $state = bin2hex(random_bytes(16));
        session(['facebook_oauth_state' => $state]);
        session(['facebook_oauth_user_id' => $request->user()->id]);

        // Required permissions for Facebook Pages and Instagram
        $scopes = [
            'pages_manage_posts',      // Post to Facebook Pages
            'pages_show_list',         // List user's pages
            'pages_read_engagement',   // Read page engagement
            'instagram_basic',         // Access Instagram Basic Display API
            'instagram_content_publish', // Publish to Instagram
            'business_management',    // Manage business assets
        ];

        $authUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
        ]);

        return response()->json([
            'auth_url' => $authUrl,
        ]);
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $errorReason = $request->query('error_reason');
        $errorDescription = $request->query('error_description');

        // Check for errors
        if ($error) {
            $errorMsg = $errorDescription ?: $errorReason ?: 'OAuth authorization failed';
            Log::error('Facebook OAuth error', [
                'error' => $error,
                'reason' => $errorReason,
                'description' => $errorDescription,
            ]);
            
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/facebook?facebook_error=' . urlencode($errorMsg));
        }

        // Verify state token
        $storedState = session('facebook_oauth_state');
        $userId = session('facebook_oauth_user_id');

        if (!$state || $state !== $storedState) {
            Log::error('Facebook OAuth state mismatch', [
                'received' => $state,
                'stored' => $storedState,
            ]);
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/facebook?facebook_error=' . urlencode('Invalid state token. Please try again.'));
        }

        if (!$code) {
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/facebook?facebook_error=' . urlencode('Authorization code not received'));
        }

        // Exchange code for access token
        try {
            $appId = trim(env('FACEBOOK_APP_ID', ''));
            $appSecret = trim(env('FACEBOOK_APP_SECRET', ''));
            $redirectUri = trim(env('FACEBOOK_REDIRECT_URI', url('/api/platforms/facebook/oauth/callback')));

            // Validate credentials are present
            if (empty($appId) || empty($appSecret)) {
                Log::error('Facebook OAuth credentials missing');
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/facebook?facebook_error=' . urlencode('Facebook OAuth credentials not configured. Please check your .env file.'));
            }

            // Step 1: Exchange authorization code for short-lived access token
            $tokenResponse = Http::asForm()
                ->post('https://graph.facebook.com/v18.0/oauth/access_token', [
                    'client_id' => $appId,
                    'client_secret' => $appSecret,
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ]);

            if (!$tokenResponse->successful()) {
                $errorBody = $tokenResponse->body();
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error']['message'] ?? $errorData['error_description'] ?? $errorBody;
                
                Log::error('Facebook token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'error' => $errorData['error'] ?? 'unknown',
                    'error_message' => $errorMessage,
                ]);

                $userFriendlyError = 'Failed to exchange authorization code for token. ';
                if (isset($errorData['error']['type']) && $errorData['error']['type'] === 'OAuthException') {
                    $userFriendlyError .= 'This usually means: 1) App ID or Secret is incorrect in .env, 2) Redirect URI doesn\'t match Facebook app settings (current: ' . $redirectUri . '), or 3) The authorization code has expired.';
                } else {
                    $userFriendlyError .= $errorMessage;
                }
                
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/facebook?facebook_error=' . urlencode($userFriendlyError));
            }

            $tokenData = $tokenResponse->json();
            $shortLivedToken = $tokenData['access_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? null;

            if (!$shortLivedToken) {
                return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/facebook?facebook_error=' . urlencode('Access token not received from Facebook'));
            }

            // Step 2: Exchange short-lived token for long-lived token (60 days)
            $longLivedResponse = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $shortLivedToken,
            ]);

            $longLivedToken = $shortLivedToken; // Fallback to short-lived if exchange fails
            $longLivedExpiresIn = $expiresIn;

            if ($longLivedResponse->successful()) {
                $longLivedData = $longLivedResponse->json();
                $longLivedToken = $longLivedData['access_token'] ?? $shortLivedToken;
                $longLivedExpiresIn = $longLivedData['expires_in'] ?? $expiresIn;
            }

            // Step 3: Get user's pages
            $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                'access_token' => $longLivedToken,
                'fields' => 'id,name,access_token,instagram_business_account',
            ]);

            $pages = [];
            $pageAccessTokens = [];
            $instagramAccounts = [];

            if ($pagesResponse->successful()) {
                $pagesData = $pagesResponse->json();
                $pages = $pagesData['data'] ?? [];

                foreach ($pages as $page) {
                    $pageId = $page['id'] ?? null;
                    $pageName = $page['name'] ?? 'Unknown Page';
                    $pageAccessToken = $page['access_token'] ?? null;
                    $instagramAccountId = $page['instagram_business_account']['id'] ?? null;

                    if ($pageId && $pageAccessToken) {
                        $pageAccessTokens[$pageId] = [
                            'page_id' => $pageId,
                            'page_name' => $pageName,
                            'access_token' => $pageAccessToken,
                        ];

                        // If this page has an Instagram Business Account
                        if ($instagramAccountId) {
                            $instagramAccounts[$pageId] = [
                                'instagram_account_id' => $instagramAccountId,
                                'page_id' => $pageId,
                                'page_name' => $pageName,
                            ];
                        }
                    }
                }
            }

            // Step 4: Get user info
            $userInfoResponse = Http::get('https://graph.facebook.com/v18.0/me', [
                'access_token' => $longLivedToken,
                'fields' => 'id,name,email',
            ]);

            $userInfo = null;
            if ($userInfoResponse->successful()) {
                $userInfo = $userInfoResponse->json();
            }

            // Store credentials - we'll store the first page by default, or let user select
            $primaryPageId = null;
            $primaryPageToken = null;
            $primaryInstagramId = null;

            if (!empty($pageAccessTokens)) {
                // Use the first page as primary
                $firstPage = reset($pageAccessTokens);
                $primaryPageId = $firstPage['page_id'];
                $primaryPageToken = $firstPage['access_token'];

                // Check if this page has Instagram
                if (isset($instagramAccounts[$primaryPageId])) {
                    $primaryInstagramId = $instagramAccounts[$primaryPageId]['instagram_account_id'];
                }
            }

            // Store credentials in platform
            $platform = Platform::updateOrCreate(
                [
                    'user_id' => $userId,
                    'platform_type' => 'facebook',
                ],
                [
                    'credentials' => [
                        'user_access_token' => $longLivedToken,
                        'user_id' => $userInfo['id'] ?? null,
                        'user_name' => $userInfo['name'] ?? null,
                        'page_id' => $primaryPageId,
                        'access_token' => $primaryPageToken, // Page access token
                        'instagram_account_id' => $primaryInstagramId,
                        'pages' => $pages, // Store all pages for future selection
                        'page_access_tokens' => $pageAccessTokens,
                        'instagram_accounts' => $instagramAccounts,
                        'expires_in' => $longLivedExpiresIn,
                        'connected_at' => now()->toIso8601String(),
                    ],
                    'is_active' => true,
                ]
            );

            // Clear session
            session()->forget(['facebook_oauth_state', 'facebook_oauth_user_id']);

            Log::info('Facebook OAuth connection successful', [
                'user_id' => $userId,
                'platform_id' => $platform->id,
                'pages_count' => count($pages),
                'instagram_accounts_count' => count($instagramAccounts),
                'primary_page_id' => $primaryPageId,
                'has_instagram' => !empty($primaryInstagramId),
            ]);

            $successMessage = 'Facebook connected successfully!';
            if (!empty($pages)) {
                $successMessage .= ' Connected to ' . count($pages) . ' page(s)';
            }
            if (!empty($instagramAccounts)) {
                $successMessage .= ' and ' . count($instagramAccounts) . ' Instagram account(s)';
            }

            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/facebook?facebook_success=1&message=' . urlencode($successMessage));

        } catch (\Exception $e) {
            Log::error('Facebook OAuth callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect(env('FRONTEND_URL', config('app.url')) . '/platforms/facebook?facebook_error=' . urlencode('OAuth callback failed: ' . $e->getMessage()));
        }
    }

    /**
     * Get user's pages (for page selection)
     */
    public function getPages(Request $request)
    {
        $user = $request->user();
        $platform = Platform::where('user_id', $user->id)
            ->where('platform_type', 'facebook')
            ->where('is_active', true)
            ->first();

        if (!$platform) {
            return response()->json([
                'success' => false,
                'message' => 'Facebook not connected',
            ], 404);
        }

        $credentials = $platform->credentials ?? [];
        $pages = $credentials['pages'] ?? [];
        $pageAccessTokens = $credentials['page_access_tokens'] ?? [];
        $instagramAccounts = $credentials['instagram_accounts'] ?? [];

        return response()->json([
            'success' => true,
            'pages' => $pages,
            'page_access_tokens' => $pageAccessTokens,
            'instagram_accounts' => $instagramAccounts,
        ]);
    }

    /**
     * Switch active page
     */
    public function switchPage(Request $request)
    {
        $user = $request->user();
        $pageId = $request->input('page_id');

        $platform = Platform::where('user_id', $user->id)
            ->where('platform_type', 'facebook')
            ->where('is_active', true)
            ->first();

        if (!$platform) {
            return response()->json([
                'success' => false,
                'message' => 'Facebook not connected',
            ], 404);
        }

        $credentials = $platform->credentials ?? [];
        $pageAccessTokens = $credentials['page_access_tokens'] ?? [];
        $instagramAccounts = $credentials['instagram_accounts'] ?? [];

        if (!isset($pageAccessTokens[$pageId])) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        $pageData = $pageAccessTokens[$pageId];
        $instagramAccountId = $instagramAccounts[$pageId]['instagram_account_id'] ?? null;

        $credentials['page_id'] = $pageId;
        $credentials['access_token'] = $pageData['access_token'];
        $credentials['instagram_account_id'] = $instagramAccountId;

        $platform->credentials = $credentials;
        $platform->save();

        return response()->json([
            'success' => true,
            'message' => 'Page switched successfully',
            'page' => $pageData,
            'instagram_account_id' => $instagramAccountId,
        ]);
    }
}
