<?php

use App\Http\Controllers\Admin\AdminApiController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\UserApiTokenController;
use App\Http\Controllers\UserAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPageController::class, 'home']);
Route::get('/docs', [PublicPageController::class, 'docs']);
Route::get('/login', [UserAuthController::class, 'showLogin'])->middleware('guest')->name('login');
Route::post('/login', [UserAuthController::class, 'login'])->middleware('guest');
Route::get('/register', [UserAuthController::class, 'showRegister'])->middleware('guest')->name('register');
Route::post('/register', [UserAuthController::class, 'register'])->middleware('guest');
Route::post('/logout', [UserAuthController::class, 'logout'])->middleware('auth');
Route::get('/dashboard', [PublicPageController::class, 'dashboard'])->middleware('auth');
Route::get('/profile', [PublicPageController::class, 'profile'])->middleware('auth');
Route::post('/profile/password', [UserAuthController::class, 'updatePassword'])->middleware('auth');
Route::get('/api-keys', [UserApiTokenController::class, 'index'])->middleware('auth');
Route::post('/api-keys', [UserApiTokenController::class, 'store'])->middleware('auth');
Route::delete('/api-keys/{token}', [UserApiTokenController::class, 'destroy'])->middleware('auth');

Route::post('/admin/api/login', [AuthController::class, 'login'])->middleware('guest');

Route::middleware(['auth', \App\Http\Middleware\EnsureAdmin::class])->group(function () {
    Route::post('/admin/api/logout', [AuthController::class, 'logout']);
    Route::get('/admin/api/me', [AuthController::class, 'me']);
    Route::get('/admin/api/dashboard', [AdminApiController::class, 'dashboard']);
    Route::get('/admin/api/data-coverage', [AdminApiController::class, 'dataCoverage']);
    Route::get('/admin/api/providers/health', [AdminApiController::class, 'providerHealth']);
    Route::get('/admin/api/system/queue-health', [AdminApiController::class, 'queueHealth']);
    Route::get('/admin/api/homepage-settings', [AdminApiController::class, 'homepageSettings']);
    Route::patch('/admin/api/homepage-settings', [AdminApiController::class, 'updateHomepageSettings']);
    Route::get('/admin/api/users-overview', [AdminApiController::class, 'usersOverview']);
    Route::get('/admin/api/user-api-tokens', [AdminApiController::class, 'userApiTokens']);
    Route::get('/admin/api/user-api-usage', [AdminApiController::class, 'userApiUsage']);
    Route::get('/admin/api/alerts', [AdminApiController::class, 'alerts']);
    Route::post('/admin/api/alerts/{alert}/read', [AdminApiController::class, 'readAlert']);
    Route::post('/admin/api/alerts/{alert}/resolve', [AdminApiController::class, 'resolveAlert']);
    Route::apiResource('/admin/api/providers', AdminApiController::class)->parameters(['providers' => 'provider'])->only(['index', 'store', 'update']);
    Route::post('/admin/api/providers/{provider}/test', [AdminApiController::class, 'testProvider']);
    Route::get('/admin/api/provider-keys', [AdminApiController::class, 'providerKeys']);
    Route::post('/admin/api/provider-keys', [AdminApiController::class, 'storeProviderKey']);
    Route::patch('/admin/api/provider-keys/{key}', [AdminApiController::class, 'updateProviderKey']);
    Route::get('/admin/api/sync-jobs', [AdminApiController::class, 'syncJobs']);
    Route::get('/admin/api/sync-jobs/export', [AdminApiController::class, 'exportSyncJobs']);
    Route::post('/admin/api/sync-jobs', [AdminApiController::class, 'storeSyncJob']);
    Route::get('/admin/api/sync-jobs/{job}', [AdminApiController::class, 'showSyncJob']);
    Route::get('/admin/api/sync-jobs/{job}/items/export', [AdminApiController::class, 'exportSyncJobItems']);
    Route::post('/admin/api/sync-jobs/{job}/run', [AdminApiController::class, 'runSyncJob']);
    Route::post('/admin/api/sync-jobs/{job}/rerun', [AdminApiController::class, 'rerunSyncJob']);
    Route::post('/admin/api/sync-jobs/{job}/cancel', [AdminApiController::class, 'cancelSyncJob']);
    Route::get('/admin/api/request-logs', [AdminApiController::class, 'requestLogs']);
    Route::get('/admin/api/request-logs/export', [AdminApiController::class, 'exportRequestLogs']);
    Route::get('/admin/api/schedules', [AdminApiController::class, 'syncSchedules']);
    Route::post('/admin/api/schedules', [AdminApiController::class, 'storeSyncSchedule']);
    Route::patch('/admin/api/schedules/{schedule}', [AdminApiController::class, 'updateSyncSchedule']);
    Route::post('/admin/api/schedules/{schedule}/run', [AdminApiController::class, 'runSyncSchedule']);
    Route::get('/admin/api/{entity}', [AdminApiController::class, 'entityIndex']);
});

Route::view('/admin/{any?}', 'app')->where('any', '.*');
