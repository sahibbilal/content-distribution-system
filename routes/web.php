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

// Diagnostic route to see what HTML is generated
Route::get('/test-vite-output', function () {
    try {
        ob_start();
        $viteOutput = '';
        // Capture what @vite() would output
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (isset($manifest['resources/js/app.js'])) {
                $jsFile = $manifest['resources/js/app.js']['file'];
                $cssFiles = $manifest['resources/js/app.js']['css'] ?? [];
                
                $viteOutput .= '<!-- Vite Assets -->' . "\n";
                foreach ($cssFiles as $css) {
                    $viteOutput .= '<link rel="stylesheet" href="' . asset('build/' . $css) . '">' . "\n";
                }
                $viteOutput .= '<script type="module" src="' . asset('build/' . $jsFile) . '"></script>' . "\n";
            }
        }
        ob_end_clean();
        
        return response()->json([
            'vite_helper_output' => $viteOutput,
            'asset_urls' => [
                'js' => asset('build/assets/app-Dopveo1y.js'),
                'css' => asset('build/assets/app-CgSQIh2N.css'),
                'manifest' => asset('build/manifest.json'),
            ],
            'app_url' => config('app.url'),
            'asset_function_test' => asset('build/test'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

// OAuth callbacks (needs session middleware)
Route::get('/api/platforms/linkedin/oauth/callback', [\App\Http\Controllers\LinkedInOAuthController::class, 'callback']);
Route::get('/api/platforms/tiktok/oauth/callback', [\App\Http\Controllers\TikTokOAuthController::class, 'callback']);
Route::get('/api/platforms/facebook/oauth/callback', [\App\Http\Controllers\FacebookOAuthController::class, 'callback']);

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

