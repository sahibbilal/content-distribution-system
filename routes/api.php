<?php

use App\Http\Controllers\AdminAiController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AiDraftController;
use App\Http\Controllers\AdminMailController;
use App\Http\Controllers\AdminPipelineController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SiteContentController;
use App\Http\Controllers\WorkerWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/facebook', [AuthController::class, 'facebookLogin']);
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/avatar', [ProfileController::class, 'avatar'])->middleware('throttle:20,1');
        Route::delete('/avatar', [ProfileController::class, 'removeAvatar']);
        Route::put('/password', [ProfileController::class, 'password'])->middleware('throttle:6,1');
    });
});

// Cloud worker results (HMAC signed) and signed downloads of episode input files (voice, avatar, captions)
Route::post('/webhooks/worker', WorkerWebhookController::class)->name('webhooks.worker')->middleware('throttle:60,1');
Route::get('/episode-files/{episode}/{kind}', [EpisodeController::class, 'file'])->name('episodes.file')->middleware('signed');

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

    Route::get('/admin/pipeline', [AdminPipelineController::class, 'show']);
    Route::put('/admin/pipeline', [AdminPipelineController::class, 'update']);
    Route::post('/admin/pipeline/test/{service}', [AdminPipelineController::class, 'test']);

    Route::get('/admin/mail', [AdminMailController::class, 'show']);
    Route::post('/admin/mail/test', [AdminMailController::class, 'test']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/ai/assistant', [AiAssistantController::class, 'show']);
    Route::put('/ai/instructions/{section}', [AiAssistantController::class, 'updateSection']);
    Route::delete('/ai/instructions/{section}', [AiAssistantController::class, 'resetSection']);
    Route::post('/ai/projects', [AiAssistantController::class, 'storeProject']);
    Route::put('/ai/projects/{id}', [AiAssistantController::class, 'updateProject']);
    Route::delete('/ai/projects/{id}', [AiAssistantController::class, 'destroyProject']);
    Route::get('/ai/projects/{id}/memory', [AiAssistantController::class, 'memory']);
    Route::delete('/ai/projects/{id}/memory', [AiAssistantController::class, 'clearMemory']);

    Route::post('/ai/draft', [AiDraftController::class, 'draft'])->middleware('throttle:20,1');
    Route::get('/ai/draft/{id}', [AiDraftController::class, 'show']);

    Route::get('/episodes', [EpisodeController::class, 'index']);
    Route::post('/episodes', [EpisodeController::class, 'store']);
    Route::get('/episodes/{id}', [EpisodeController::class, 'show']);
    Route::patch('/episodes/{id}', [EpisodeController::class, 'update']);
    Route::post('/episodes/{id}/approve', [EpisodeController::class, 'approve']);
    Route::post('/episodes/{id}/reject', [EpisodeController::class, 'reject']);
    Route::post('/episodes/{id}/revise', [EpisodeController::class, 'revise']);
    Route::post('/episodes/{id}/avatar', [EpisodeController::class, 'avatar']);
    Route::post('/episodes/{id}/approve-outputs', [EpisodeController::class, 'approveOutputs']);
    Route::post('/episodes/{id}/cancel-posting', [EpisodeController::class, 'cancelPosting']);
    Route::post('/episodes/{id}/retry', [EpisodeController::class, 'retry']);
    Route::post('/episodes/{id}/cancel', [EpisodeController::class, 'cancel']);

    Route::apiResource('posts', PostController::class);
    Route::get('/schedules/calendar', [ScheduleController::class, 'calendar']);
    Route::apiResource('schedules', ScheduleController::class)->only(['index', 'destroy']);
    
    Route::prefix('platforms')->group(function () {
        Route::get('/', [PlatformController::class, 'index']);
        Route::post('/{platform}/connect', [PlatformController::class, 'connect']);
        Route::post('/{platform}/test', [PlatformController::class, 'test']);
        Route::delete('/{platform}/disconnect', [PlatformController::class, 'disconnect']);
        Route::patch('/{platform}/settings', [PlatformController::class, 'settings']);
        
        // LinkedIn OAuth routes
        Route::prefix('linkedin/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\LinkedInOAuthController::class, 'initiate']);
        });
        
        // TikTok OAuth routes
        Route::prefix('tiktok/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\TikTokOAuthController::class, 'initiate']);
        });
        
        // X (Twitter) OAuth routes
        Route::prefix('twitter/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\TwitterOAuthController::class, 'initiate']);
        });

        // YouTube OAuth routes
        Route::prefix('youtube/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\YouTubeOAuthController::class, 'initiate']);
        });

        // Instagram OAuth routes
        Route::prefix('instagram/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\InstagramOAuthController::class, 'initiate']);
        });

        // Facebook OAuth routes
        Route::prefix('facebook/oauth')->group(function () {
            Route::get('/initiate', [\App\Http\Controllers\FacebookOAuthController::class, 'initiate']);
            Route::get('/pages', [\App\Http\Controllers\FacebookOAuthController::class, 'getPages']);
            Route::post('/switch-page', [\App\Http\Controllers\FacebookOAuthController::class, 'switchPage']);
        });
    });
    
    Route::get('/media', [MediaController::class, 'index']);
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);
    Route::post('/media/upload', [MediaController::class, 'upload']);
    Route::post('/media/check', [MediaController::class, 'check']);
});
