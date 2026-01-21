<?php

use Illuminate\Support\Facades\Route;

// OAuth callbacks (needs session middleware)
Route::get('/api/platforms/linkedin/oauth/callback', [\App\Http\Controllers\LinkedInOAuthController::class, 'callback']);
Route::get('/api/platforms/tiktok/oauth/callback', [\App\Http\Controllers\TikTokOAuthController::class, 'callback']);
Route::get('/api/platforms/facebook/oauth/callback', [\App\Http\Controllers\FacebookOAuthController::class, 'callback']);

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

