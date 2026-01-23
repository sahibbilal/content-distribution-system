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

// OAuth callbacks (needs session middleware)
Route::get('/api/platforms/linkedin/oauth/callback', [\App\Http\Controllers\LinkedInOAuthController::class, 'callback']);
Route::get('/api/platforms/tiktok/oauth/callback', [\App\Http\Controllers\TikTokOAuthController::class, 'callback']);
Route::get('/api/platforms/facebook/oauth/callback', [\App\Http\Controllers\FacebookOAuthController::class, 'callback']);

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

