<?php

namespace App\Services\Platforms;

use App\Services\PlatformServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInService implements PlatformServiceInterface
{
    public function publish(string $content, array $credentials, array $media = []): array
    {
        // Get credentials from array or environment variables
        // Check all possible keys for access token
        $accessToken = $credentials['access_token'] 
            ?? $credentials['LINKEDIN_ACCESS_TOKEN'] 
            ?? $credentials['linkedin_access_token']
            ?? env('LINKEDIN_ACCESS_TOKEN')
            ?? null;
        
        $personUrn = $credentials['person_urn'] 
            ?? $credentials['LINKEDIN_PERSON_URN'] 
            ?? $credentials['linkedin_person_urn']
            ?? env('LINKEDIN_PERSON_URN')
            ?? null;

        // Log credentials for debugging (without exposing the token)
        Log::info('LinkedIn publish attempt', [
            'has_access_token' => !empty($accessToken),
            'token_length' => $accessToken ? strlen($accessToken) : 0,
            'has_person_urn' => !empty($personUrn),
            'credentials_keys' => array_keys($credentials),
        ]);

        if (!$accessToken || empty(trim($accessToken))) {
            throw new \Exception('LinkedIn access token is required. Please check your credentials in Settings or set LINKEDIN_ACCESS_TOKEN in .env. The token may be expired or invalid.');
        }

        // Validate token format (LinkedIn tokens are typically long strings)
        if (strlen(trim($accessToken)) < 50) {
            throw new \Exception('LinkedIn access token appears to be invalid (too short). Please verify your token in Settings.');
        }

        // If person_urn is not provided, try to get it from the API
        if (!$personUrn) {
            $personUrn = $this->getPersonUrn($accessToken);
        }

        if (!$personUrn) {
            throw new \Exception('LinkedIn person URN is required. Unable to fetch from API. Please provide person_urn in Settings or set LINKEDIN_PERSON_URN in .env. This may indicate your access token is invalid or expired.');
        }

        // Ensure person_urn is in correct format (urn:li:person:xxx or urn:li:organization:xxx)
        if (!str_starts_with($personUrn, 'urn:li:')) {
            // If it's just an ID, try to determine if it's person or organization
            // For now, assume it's a person URN
            $personUrn = 'urn:li:person:' . $personUrn;
        }

        $url = 'https://api.linkedin.com/v2/ugcPosts';

        $postData = [
            'author' => $personUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $content,
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        // Handle media if provided
        // Note: LinkedIn requires uploading images first via their Upload API
        // For now, we'll just include the media URL in the text if it's a link
        if (!empty($media) && isset($media[0])) {
            $mediaUrl = $media[0]['url'] ?? null;
            if ($mediaUrl) {
                // For LinkedIn, we can mention the link in the content
                // Full media upload would require using LinkedIn's Upload API first
                // For now, append the URL to the content if it's not already there
                if (strpos($content, $mediaUrl) === false) {
                    $content .= "\n\n" . $mediaUrl;
                }
                $postData['specificContent']['com.linkedin.ugc.ShareContent']['shareCommentary']['text'] = $content;
            }
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->timeout(30)
        ->post($url, $postData);

        if ($response->successful()) {
            // LinkedIn UGC Posts API returns the post URN in the 'id' field
            // Format: urn:li:ugcPost:1234567890 or urn:li:share:1234567890
            $responseData = $response->json();
            $postUrn = $response->header('x-linkedin-id') ?? $responseData['id'] ?? null;
            
            Log::info('LinkedIn post created', [
                'post_urn' => $postUrn,
                'response_status' => $response->status(),
                'response_body' => $responseData,
            ]);
            
            $postUrl = null;
            
            // Construct LinkedIn post URL directly from URN
            // LinkedIn supports URLs in format: https://www.linkedin.com/feed/update/{URN}
            if ($postUrn) {
                // URL-encode the URN to handle any special characters
                $encodedUrn = urlencode($postUrn);
                $postUrl = "https://www.linkedin.com/feed/update/{$postUrn}";
                
                Log::info('LinkedIn post URL constructed', [
                    'post_urn' => $postUrn,
                    'post_url' => $postUrl,
                ]);
            } else {
                Log::warning('LinkedIn post URN not found in response', [
                    'response_body' => $responseData,
                    'response_headers' => $response->headers(),
                ]);
                // Fallback to feed URL if URN not available
                $postUrl = "https://www.linkedin.com/feed/";
            }

            return [
                'success' => true,
                'post_id' => $postUrn,
                'post_urn' => $postUrn,
                'post_url' => $postUrl,
                'url' => $postUrl,
                'platform' => 'linkedin',
                'message' => $postUrl && $postUrl !== 'https://www.linkedin.com/feed/' 
                    ? 'Post published successfully on LinkedIn. Click the link to view it.' 
                    : 'Post published successfully on LinkedIn. Please check your LinkedIn feed to view it.',
            ];
        }

        $errorBody = $response->body();
        $status = $response->status();
        
        // Parse error response for better error messages
        $errorData = json_decode($errorBody, true);
        $errorMessage = $errorData['message'] ?? $errorBody;
        $errorCode = $errorData['code'] ?? $errorData['serviceErrorCode'] ?? null;
        
        Log::error('LinkedIn API error', [
            'status' => $status,
            'code' => $errorCode,
            'message' => $errorMessage,
            'body' => $errorBody,
        ]);

        // Provide user-friendly error messages
        if ($status === 401) {
            if ($errorCode === 65600 || str_contains($errorMessage, 'INVALID_ACCESS_TOKEN')) {
                throw new \Exception('LinkedIn access token is invalid or expired. Please update your access token in Settings. LinkedIn tokens typically expire after 60 days. You may need to regenerate your token from the LinkedIn Developer Portal.');
            }
            throw new \Exception('LinkedIn authentication failed. Please check your access token in Settings. Error: ' . $errorMessage);
        }

        if ($status === 403) {
            throw new \Exception('LinkedIn API access forbidden. Your token may not have the required permissions (w_member_social, w_organization_social). Please check your LinkedIn app permissions.');
        }

        throw new \Exception('LinkedIn API error: ' . $errorMessage . ' (Status: ' . $status . ($errorCode ? ', Code: ' . $errorCode : '') . ')');
    }

    /**
     * Get person URN from LinkedIn API using access token
     */
    private function getPersonUrn(string $accessToken): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->timeout(10)
            ->get('https://api.linkedin.com/v2/me');

            if ($response->successful()) {
                $data = $response->json();
                // LinkedIn API v2 returns id in format "urn:li:person:xxx"
                return $data['id'] ?? null;
            } else {
                // Log the error for debugging
                $errorBody = $response->body();
                $errorData = json_decode($errorBody, true);
                Log::warning('Failed to get person URN from LinkedIn API', [
                    'status' => $response->status(),
                    'error' => $errorData['message'] ?? $errorBody,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get person URN from LinkedIn API: ' . $e->getMessage());
        }

        return null;
    }

    public function validateCredentials(array $credentials): bool
    {
        $accessToken = $credentials['access_token'] 
            ?? $credentials['LINKEDIN_ACCESS_TOKEN'] 
            ?? env('LINKEDIN_ACCESS_TOKEN')
            ?? null;
        
        // person_urn is optional if we can get it from API
        return !empty($accessToken);
    }

    public function testConnection(array $credentials): array
    {
        // Get credentials from array or environment variables
        $accessToken = $credentials['access_token'] 
            ?? $credentials['LINKEDIN_ACCESS_TOKEN'] 
            ?? env('LINKEDIN_ACCESS_TOKEN')
            ?? null;

        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'LinkedIn access token is required. Please provide access_token or set LINKEDIN_ACCESS_TOKEN in .env',
            ];
        }

        try {
            // Test by getting user profile
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->timeout(10)
            ->get('https://api.linkedin.com/v2/me');

            if ($response->successful()) {
                $userData = $response->json();
                $personUrn = $userData['id'] ?? null;
                $firstName = $userData['firstName']['localized']['en_US'] ?? $userData['firstName'] ?? 'Unknown';
                $lastName = $userData['lastName']['localized']['en_US'] ?? $userData['lastName'] ?? '';

                return [
                    'success' => true,
                    'message' => 'Connection successful! Authenticated as: ' . $firstName . ' ' . $lastName . ($personUrn ? ' (URN: ' . $personUrn . ')' : ''),
                    'user' => $userData,
                    'person_urn' => $personUrn,
                ];
            }

            $errorBody = $response->body();
            $status = $response->status();

            return [
                'success' => false,
                'message' => 'Connection failed with status ' . $status . ': ' . $errorBody,
                'status' => $status,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }
}

