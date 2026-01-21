<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use App\Services\PlatformServiceFactory;

class PlatformController extends Controller
{
    public function index(Request $request)
    {
        $platforms = Platform::where('user_id', $request->user()->id)
            ->get()
            ->map(function ($platform) {
                return [
                    'id' => $platform->id,
                    'platform_type' => $platform->platform_type,
                    'is_active' => $platform->is_active,
                    'created_at' => $platform->created_at,
                ];
            });

        return response()->json($platforms);
    }

    public function connect(Request $request, $platformType)
    {
        $request->validate([
            'credentials' => 'required|array',
        ]);

        $allowedPlatforms = ['facebook', 'linkedin', 'youtube', 'tiktok', 'kaggle'];
        
        if (!in_array($platformType, $allowedPlatforms)) {
            return response()->json(['error' => 'Invalid platform type'], 400);
        }

        // Optionally test connection before saving (if test parameter is provided)
        if ($request->has('test_first') && $request->test_first) {
            try {
                $service = PlatformServiceFactory::create($platformType);
                if (method_exists($service, 'testConnection')) {
                    $testResult = $service->testConnection($request->credentials);
                    if (!$testResult['success']) {
                        return response()->json([
                            'error' => 'Connection test failed',
                            'message' => $testResult['message'],
                        ], 400);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Connection test failed',
                    'message' => $e->getMessage(),
                ], 400);
            }
        }

        $platform = Platform::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'platform_type' => $platformType,
            ],
            [
                'credentials' => $request->credentials,
                'is_active' => true,
            ]
        );

        return response()->json([
            'id' => $platform->id,
            'platform_type' => $platform->platform_type,
            'is_active' => $platform->is_active,
        ], 201);
    }

    public function disconnect(Request $request, $platformType)
    {
        $platform = Platform::where('user_id', $request->user()->id)
            ->where('platform_type', $platformType)
            ->firstOrFail();

        $platform->delete();

        return response()->json(['message' => 'Platform disconnected successfully']);
    }

    public function test(Request $request, $platformType)
    {
        $request->validate([
            'credentials' => 'required|array',
        ]);

        $allowedPlatforms = ['facebook', 'linkedin', 'youtube', 'tiktok', 'kaggle'];
        
        if (!in_array($platformType, $allowedPlatforms)) {
            return response()->json(['error' => 'Invalid platform type'], 400);
        }

        try {
            $service = PlatformServiceFactory::create($platformType);
            $isValid = $service->validateCredentials($request->credentials);
            
            if (!$isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credentials are incomplete or invalid',
                ], 400);
            }

            // Test the connection by calling a test method if available
            if (method_exists($service, 'testConnection')) {
                $testResult = $service->testConnection($request->credentials);
                return response()->json($testResult);
            }

            // Fallback: just validate credentials
            return response()->json([
                'success' => true,
                'message' => 'Credentials are valid',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 400);
        }
    }
}

