<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/facebook', [AuthController::class, 'facebookLogin']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
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
