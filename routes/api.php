<?php

use App\Http\Controllers\AdminAiController;
use App\Http\Controllers\AdminMailController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SiteContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/facebook', [AuthController::class, 'facebookLogin']);
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Public site content (home page, legal pages)
Route::get('/site-content', [SiteContentController::class, 'show']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::put('/admin/site-content', [SiteContentController::class, 'update']);

    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::post('/admin/users/{user}/verify', [AdminUserController::class, 'verify']);
    Route::post('/admin/users/{user}/resend-verification', [AdminUserController::class, 'resendVerification']);

    Route::get('/admin/ai', [AdminAiController::class, 'index']);
    Route::put('/admin/ai/{provider}', [AdminAiController::class, 'update']);
    Route::post('/admin/ai/{provider}/test', [AdminAiController::class, 'test']);

    Route::get('/admin/mail', [AdminMailController::class, 'show']);
    Route::post('/admin/mail/test', [AdminMailController::class, 'test']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('posts', PostController::class);
    Route::apiResource('schedules', ScheduleController::class)->only(['index', 'destroy']);
    
    Route::prefix('platforms')->group(function () {
        Route::get('/', [PlatformController::class, 'index']);
        Route::post('/{platform}/connect', [PlatformController::class, 'connect']);
        Route::post('/{platform}/test', [PlatformController::class, 'test']);
        Route::delete('/{platform}/disconnect', [PlatformController::class, 'disconnect']);
        
        // LinkedIn OAuth routes
        Route::prefix('linkedin/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\LinkedInOAuthController::class, 'initiate']);
        });
        
        // TikTok OAuth routes
        Route::prefix('tiktok/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\TikTokOAuthController::class, 'initiate']);
        });
        
        // Facebook OAuth routes
        Route::prefix('facebook/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\FacebookOAuthController::class, 'initiate']);
            Route::get('/pages', [\App\Http\Controllers\FacebookOAuthController::class, 'getPages']);
            Route::post('/switch-page', [\App\Http\Controllers\FacebookOAuthController::class, 'switchPage']);
        });
    });
    
    Route::post('/media/upload', [MediaController::class, 'upload']);
});
