<?php

use App\Http\Controllers\Api\AppApiTokenController;
use App\Http\Controllers\Api\AppAuthController;
use App\Http\Controllers\Api\V1\PublicSportsApiController;
use App\Http\Middleware\EnsureUserApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->group(function () {
    Route::post('/register', [AppAuthController::class, 'register']);
    Route::post('/login', [AppAuthController::class, 'login']);

    Route::middleware(EnsureUserApiToken::class)->group(function () {
        Route::get('/me', [AppAuthController::class, 'me']);
        Route::get('/api-keys', [AppApiTokenController::class, 'index']);
        Route::post('/api-keys', [AppApiTokenController::class, 'store']);
        Route::delete('/api-keys/{token}', [AppApiTokenController::class, 'destroy']);
    });
});

Route::prefix('v1')->middleware(EnsureUserApiToken::class)->group(function () {
    Route::get('/metadata', [PublicSportsApiController::class, 'metadata']);
    Route::get('/sports', [PublicSportsApiController::class, 'sports']);
    Route::get('/countries', [PublicSportsApiController::class, 'countries']);
    Route::get('/leagues', [PublicSportsApiController::class, 'leagues']);
    Route::get('/seasons', [PublicSportsApiController::class, 'seasons']);
    Route::get('/teams', [PublicSportsApiController::class, 'teams']);
    Route::get('/matches', [PublicSportsApiController::class, 'matches']);
    Route::get('/matches/{match}', [PublicSportsApiController::class, 'match']);
    Route::get('/standings', [PublicSportsApiController::class, 'standings']);
    Route::get('/stats/summary', [PublicSportsApiController::class, 'summary']);
});
