<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// Simple test route to verify Laravel is working
Route::get('/test-laravel', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Laravel is working correctly!',
        'laravel_version' => app()->version(),
        'php_version' => PHP_VERSION,
        'server_time' => now()->toDateTimeString(),
        'timezone' => config('app.timezone'),
        'environment' => config('app.env'),
        'app_name' => config('app.name'),
        'app_url' => config('app.url'),
        'database_connected' => (function() {
            try {
                DB::connection()->getPdo();
                return true;
            } catch (\Exception $e) {
                return false;
            }
        })(),
    ]);
});

// Diagnostic route to check build files
Route::get('/test-build-files', function () {
    $buildPath = public_path('build');
    $manifestPath = public_path('build/manifest.json');
    
    return response()->json([
        'build_directory_exists' => is_dir($buildPath),
        'build_directory_path' => $buildPath,
        'manifest_exists' => file_exists($manifestPath),
        'manifest_path' => $manifestPath,
        'build_directory_contents' => is_dir($buildPath) ? array_slice(scandir($buildPath), 2) : [],
        'manifest_content' => file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null,
        'public_path' => public_path(),
        'base_path' => base_path(),
    ]);
});

// OAuth callbacks (needs session middleware)
Route::get('/api/platforms/linkedin/oauth/callback', [\App\Http\Controllers\LinkedInOAuthController::class, 'callback']);
Route::get('/api/platforms/tiktok/oauth/callback', [\App\Http\Controllers\TikTokOAuthController::class, 'callback']);
Route::get('/api/platforms/facebook/oauth/callback', [\App\Http\Controllers\FacebookOAuthController::class, 'callback']);

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

