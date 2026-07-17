<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunSportsDataSyncJob;
use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\ApiRequestLog;
use App\Models\Country;
use App\Models\League;
use App\Models\MatchStatistic;
use App\Models\Plan;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\Sport;
use App\Models\SportsMatch;
use App\Models\SyncSchedule;
use App\Models\SystemAlert;
use App\Models\SyncJob;
use App\Models\SyncJobItem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserApiRequestLog;
use App\Models\UserApiToken;
use App\Services\SportsData\ProviderRegistry;
use App\Services\SportsData\FullProviderSyncService;
use App\Services\SportsData\SportsDataSyncService;
use App\Services\SportsData\SyncLockService;
use App\Services\SportsData\SyncScheduleService;
use App\Services\SportsData\SyncProgressService;
use App\Services\SportsData\SystemAlertService;
use App\Services\Support\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminApiController extends Controller
{
    public function dashboard(SyncProgressService $progress, SystemAlertService $alerts)
    {
        $alerts->providerHealthAlerts();
        $today = now()->startOfDay();
        $providers = ApiProvider::with(['syncJobs' => fn ($query) => $query->latest()->limit(20), 'requestLogs' => fn ($query) => $query->latest('requested_at')->limit(5)])
            ->orderBy('priority')
            ->get();

        return [
            'cards' => [
                'active_providers' => ApiProvider::where('is_active', true)->count(),
                'active_api_keys' => ApiProviderKey::where('is_active', true)->count(),
                'collected_leagues' => League::count(),
                'collected_teams' => Team::count(),
                'collected_matches' => SportsMatch::count(),
                'finished_matches' => SportsMatch::whereIn('status', ['FT', 'finished', 'completed'])->count(),
                'future_matches' => SportsMatch::where('starts_at', '>', now())->count(),
                'collected_statistics' => MatchStatistic::count(),
                'requests_today' => ApiRequestLog::where('requested_at', '>=', $today)->count(),
                'errors_today' => ApiRequestLog::where('requested_at', '>=', $today)->where('success', false)->count(),
                'pending_sync_jobs' => SyncJob::where('status', 'pending')->count(),
                'ready_sync_jobs' => SyncJob::where('status', 'pending')->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))->count(),
                'scheduled_sync_jobs' => SyncJob::where('status', 'pending')->where('available_at', '>', now())->count(),
                'running_sync_jobs' => SyncJob::where('status', 'running')->count(),
                'failed_sync_jobs' => SyncJob::where('status', 'failed')->count(),
                'open_alerts' => SystemAlert::whereNull('resolved_at')->count(),
                'status_429_today' => ApiRequestLog::where('requested_at', '>=', $today)->where('status_code', 429)->count(),
                'cancelled_jobs' => SyncJob::where('status', 'cancelled')->count(),
                'active_schedules' => SyncSchedule::where('is_active', true)->count(),
            ],
            'overall_progress' => $progress->overall(),
            'recent_sync_jobs' => SyncJob::with('provider:id,name,slug')->latest()->limit(8)->get(),
            'providers_with_error' => ApiProvider::whereNotNull('last_error')->latest()->limit(8)->get(),
            'request_usage' => ApiRequestLog::selectRaw('api_provider_id, COUNT(*) as requests')->with('provider:id,name')->groupBy('api_provider_id')->get(),
            'top_leagues' => League::withCount('matches')->orderByDesc('matches_count')->limit(5)->get(['id', 'name', 'slug']),
            'last_sync' => SyncJob::latest('finished_at')->first(),
            'provider_progress' => $providers->map(fn (ApiProvider $provider) => [
                'id' => $provider->id,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'total_jobs' => $provider->syncJobs->count(),
                'completed' => $provider->syncJobs->where('status', 'completed')->count(),
                'failed' => $provider->syncJobs->where('status', 'failed')->count(),
                'running' => $provider->syncJobs->where('status', 'running')->count(),
                'average_progress' => round((float) $provider->syncJobs->avg('progress_percent'), 2),
                'latest_requests' => $provider->requestLogs,
                'last_error' => $provider->last_error,
            ]),
            'coverage' => [
                'sports' => Sport::whereHas('leagues')->count(),
                'countries' => Country::whereHas('leagues')->count(),
                'leagues_by_country' => Country::withCount('leagues')->orderByDesc('leagues_count')->limit(10)->get(['id', 'name', 'code'])->where('leagues_count', '>', 0)->values(),
                'seasons_by_league' => League::withCount('seasons')->orderByDesc('seasons_count')->limit(10)->get(['id', 'name', 'slug'])->where('seasons_count', '>', 0)->values(),
                'years' => Season::distinct()->orderByDesc('year')->pluck('year'),
            ],
            'alerts' => SystemAlert::whereNull('resolved_at')->latest()->limit(8)->get(),
            'next_schedule' => SyncSchedule::where('is_active', true)->whereNotNull('next_run_at')->orderBy('next_run_at')->first(),
            'queue_health' => $this->queueHealth($alerts),
        ];
    }

    public function index()
    {
        return ApiProvider::withCount(['keys', 'requestLogs', 'syncJobs'])->orderBy('priority')->get();
    }

    public function store(Request $request)
    {
        $data = $this->providerRules($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        return ApiProvider::create($data);
    }

    public function update(Request $request, ApiProvider $provider)
    {
        $data = $this->providerRules($request, $provider);
        $provider->update($data);
        return $provider->fresh();
    }

    public function testProvider(ApiProvider $provider, ProviderRegistry $registry)
    {
        $result = $registry->make($provider)->testConnection();
        $provider->update(['last_checked_at' => now(), 'last_error' => $result->success ? null : $result->message]);
        return ['success' => $result->success, 'message' => $result->message, 'meta' => $result->meta];
    }

    public function fullSyncProvider(Request $request, ApiProvider $provider, FullProviderSyncService $fullSync)
    {
        $data = $request->validate([
            'request_interval_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'seasons' => ['nullable', 'array'],
            'seasons.*' => ['integer', 'min:1900', 'max:2100'],
            'max_children' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        return response()->json($fullSync->create($provider, $data), 201);
    }

    public function providerKeys()
    {
        return ApiProviderKey::with('provider:id,name,slug')->latest()->get();
    }

    public function usersOverview(Request $request)
    {
        $today = now()->startOfDay();
        $users = User::query()
            ->with('plan:id,name,slug')
            ->withCount([
                'apiTokens',
                'apiTokens as active_api_tokens_count' => fn ($query) => $query->whereNull('revoked_at'),
                'apiRequestLogs as requests_today_count' => fn ($query) => $query->where('requested_at', '>=', $today),
                'apiRequestLogs as requests_total_count',
            ])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', '%'.$request->query('search').'%')
                ->orWhere('email', 'like', '%'.$request->query('search').'%')))
            ->latest()
            ->paginate(25);

        return [
            'cards' => [
                'users_total' => User::count(),
                'users_with_active_keys' => User::whereHas('apiTokens', fn ($query) => $query->whereNull('revoked_at'))->count(),
                'active_user_api_keys' => UserApiToken::whereNull('revoked_at')->count(),
                'revoked_user_api_keys' => UserApiToken::whereNotNull('revoked_at')->count(),
                'requests_today' => UserApiRequestLog::where('requested_at', '>=', $today)->count(),
                'rate_limited_today' => UserApiRequestLog::where('requested_at', '>=', $today)->where('status_code', 429)->count(),
            ],
            'data' => $users,
        ];
    }

    public function userApiTokens(Request $request)
    {
        return UserApiToken::query()
            ->with('user:id,name,email')
            ->withCount('requestLogs')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status'), fn ($query) => $request->query('status') === 'active' ? $query->whereNull('revoked_at') : $query->whereNotNull('revoked_at'))
            ->latest()
            ->paginate(25);
    }

    public function userApiUsage(Request $request)
    {
        $logs = UserApiRequestLog::query()
            ->with(['user:id,name,email', 'token:id,name,token_prefix'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status_code'), fn ($query) => $query->where('status_code', $request->integer('status_code')))
            ->when($request->filled('endpoint'), fn ($query) => $query->where('endpoint', 'like', '%'.$request->query('endpoint').'%'))
            ->latest('requested_at');

        return [
            'cards' => [
                'requests' => (clone $logs)->count(),
                'unique_users' => (clone $logs)->distinct('user_id')->count('user_id'),
                'rate_limited' => (clone $logs)->where('status_code', 429)->count(),
                'average_duration_ms' => round((float) (clone $logs)->avg('duration_ms'), 2),
            ],
            'data' => $logs->paginate(50),
        ];
    }

    public function plans()
    {
        return Plan::with([
            'accessRules.country:id,name,code',
            'accessRules.league:id,name,slug',
            'accessRules.season:id,league_id,year,name',
        ])
            ->withCount('users')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function storePlan(Request $request)
    {
        $data = $this->planRules($request);

        return DB::transaction(function () use ($data) {
            if ($data['is_default'] ?? false) {
                Plan::query()->update(['is_default' => false]);
            }

            $plan = Plan::create(collect($data)->except('access_rules')->all());
            $this->syncPlanAccessRules($plan, $data['access_rules'] ?? []);

            return response()->json($plan->load(['accessRules.country:id,name,code', 'accessRules.league:id,name,slug', 'accessRules.season:id,league_id,year,name']), 201);
        });
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $data = $this->planRules($request, $plan);

        return DB::transaction(function () use ($plan, $data) {
            if ($data['is_default'] ?? false) {
                Plan::query()->whereKeyNot($plan->id)->update(['is_default' => false]);
            }

            $plan->update(collect($data)->except('access_rules')->all());
            $this->syncPlanAccessRules($plan, $data['access_rules'] ?? []);

            return $plan->fresh()->load(['accessRules.country:id,name,code', 'accessRules.league:id,name,slug', 'accessRules.season:id,league_id,year,name']);
        });
    }

    public function planOptions()
    {
        return [
            'regions' => [
                ['value' => 'americas', 'label' => 'Americas'],
            ],
            'countries' => Country::orderBy('name')->get(['id', 'name', 'code']),
            'leagues' => League::with('country:id,name,code')->orderBy('name')->get(['id', 'country_id', 'name', 'slug']),
            'seasons' => Season::with('league:id,name,slug')->latest('year')->get(['id', 'league_id', 'year', 'name']),
        ];
    }

    public function updateUserPlan(Request $request, User $user)
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'exists:plans,id'],
        ]);

        $user->update(['plan_id' => $data['plan_id'] ?? null]);

        return $user->fresh()->load('plan:id,name,slug');
    }

    public function storeProviderKey(Request $request)
    {
        $data = $request->validate([
            'api_provider_id' => ['required', 'exists:api_providers,id'],
            'name' => ['required', 'string', 'max:120'],
            'api_key' => ['required', 'string', 'min:4'],
            'is_active' => ['boolean'],
            'requests_per_minute' => ['nullable', 'integer', 'min:1'],
            'requests_per_day' => ['nullable', 'integer', 'min:1'],
        ]);

        return ApiProviderKey::create([
            'api_provider_id' => $data['api_provider_id'],
            'name' => $data['name'],
            'encrypted_key' => $data['api_key'],
            'key_hint' => '****'.substr($data['api_key'], -4),
            'is_active' => $data['is_active'] ?? true,
            'requests_per_minute' => $data['requests_per_minute'] ?? null,
            'requests_per_day' => $data['requests_per_day'] ?? null,
        ])->load('provider:id,name,slug');
    }

    public function updateProviderKey(Request $request, ApiProviderKey $key)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'requests_per_minute' => ['nullable', 'integer', 'min:1'],
            'requests_per_day' => ['nullable', 'integer', 'min:1'],
        ]);
        $key->update($data);
        return $key->fresh()->load('provider:id,name,slug');
    }

    public function syncJobs()
    {
        return SyncJob::with('provider:id,name,slug')->latest()->paginate(25);
    }

    public function showSyncJob(Request $request, SyncJob $job)
    {
        $itemQuery = $job->items()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->query('entity_type')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->query('action')))
            ->when($request->boolean('errors_only'), fn ($query) => $query->whereNotNull('error_message'))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('external_id', 'like', '%'.$request->query('search').'%')
                ->orWhere('error_message', 'like', '%'.$request->query('search').'%')))
            ->latest();

        $logQuery = $job->requestLogs()
            ->with('provider:id,name,slug')
            ->when($request->filled('success'), fn ($query) => $query->where('success', $request->boolean('success')))
            ->when($request->filled('status_code'), fn ($query) => $query->where('status_code', $request->integer('status_code')))
            ->when($request->filled('endpoint'), fn ($query) => $query->where('endpoint', 'like', '%'.$request->query('endpoint').'%'))
            ->latest('requested_at');

        $durationSeconds = $job->started_at ? (int) $job->started_at->diffInSeconds($job->finished_at ?? now()) : null;

        return [
            'job' => $job->load(['provider:id,name,slug', 'sport:id,name,slug', 'league:id,name,slug', 'season:id,year,name', 'parent:id,status,type']),
            'summary' => [
                'requests_count' => $job->requestLogs()->count(),
                'request_errors_count' => $job->requestLogs()->where('success', false)->count(),
                'duration_seconds' => $durationSeconds,
                'average_duration_per_item_seconds' => $durationSeconds && $job->processed_items ? round($durationSeconds / max(1, $job->processed_items), 2) : null,
            ],
            'items' => $itemQuery->paginate(25, ['*'], 'items_page'),
            'request_logs' => $logQuery->paginate(25, ['*'], 'logs_page'),
        ];
    }

    public function storeSyncJob(Request $request, SyncLockService $locks)
    {
        $data = $request->validate([
            'api_provider_id' => ['required', 'exists:api_providers,id'],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'league_id' => ['nullable', 'exists:leagues,id'],
            'season_id' => ['nullable', 'exists:seasons,id'],
            'type' => ['required', Rule::in(['sync_leagues', 'sync_teams', 'sync_matches', 'sync_standings', 'sync_match_statistics'])],
            'config' => ['nullable', 'array'],
            'is_incremental' => ['sometimes', 'boolean'],
        ]);

        $data['is_incremental'] = $data['is_incremental'] ?? filled(data_get($data, 'config.updated_since'));
        if ($locks->hasDuplicate($data)) {
            return response()->json(['message' => 'A pending or running sync job with the same scope already exists.'], 409);
        }

        $job = SyncJob::create($data + ['status' => 'pending', 'source' => 'manual', 'progress_percent' => 0]);
        RunSportsDataSyncJob::dispatch($job->id);

        return $job;
    }

    public function runSyncJob(SyncJob $job)
    {
        if ($job->status !== 'pending') {
            $job->update(['status' => 'pending', 'finished_at' => null, 'last_error' => null]);
        }
        RunSportsDataSyncJob::dispatch($job->id);
        return $job->fresh();
    }

    public function rerunSyncJob(SyncJob $job)
    {
        $newJob = SyncJob::create([
            'parent_sync_job_id' => $job->id,
            'api_provider_id' => $job->api_provider_id,
            'sport_id' => $job->sport_id,
            'league_id' => $job->league_id,
            'season_id' => $job->season_id,
            'type' => $job->type,
            'source' => 'manual',
            'is_incremental' => $job->is_incremental,
            'status' => 'pending',
            'progress_percent' => 0,
            'config' => $job->config,
        ]);
        RunSportsDataSyncJob::dispatch($newJob->id);

        return $newJob->load('provider:id,name,slug');
    }

    public function cancelSyncJob(SyncJob $job, SyncProgressService $progress)
    {
        return $progress->cancel($job);
    }

    public function requestLogs(Request $request)
    {
        $today = now()->startOfDay();
        $base = ApiRequestLog::query();
        $filtered = ApiRequestLog::with(['provider:id,name,slug', 'syncJob:id,status,type'])
            ->when($request->filled('provider'), fn ($query) => $query->where('api_provider_id', $request->integer('provider')))
            ->when($request->filled('success'), fn ($query) => $query->where('success', $request->boolean('success')))
            ->when($request->filled('status_code'), fn ($query) => $query->where('status_code', $request->integer('status_code')))
            ->when($request->filled('date_from'), fn ($query) => $query->where('requested_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->where('requested_at', '<=', $request->date('date_to')))
            ->when($request->filled('sync_job_id'), fn ($query) => $query->where('sync_job_id', $request->integer('sync_job_id')))
            ->when($request->filled('endpoint'), fn ($query) => $query->where('endpoint', 'like', '%'.$request->query('endpoint').'%'))
            ->when($request->boolean('ssl_errors'), fn ($query) => $query->where('error_message', 'like', '%SSL certificate%'))
            ->when($request->boolean('rate_limit'), fn ($query) => $query->where('status_code', 429))
            ->when($request->filled('min_duration_ms'), fn ($query) => $query->where('duration_ms', '>=', $request->integer('min_duration_ms')))
            ->latest('requested_at');

        $providerWithMostErrors = ApiProvider::query()
            ->select('api_providers.id', 'api_providers.name', DB::raw('COUNT(api_request_logs.id) as errors_count'))
            ->join('api_request_logs', 'api_request_logs.api_provider_id', '=', 'api_providers.id')
            ->where('api_request_logs.requested_at', '>=', $today)
            ->where('api_request_logs.success', false)
            ->groupBy('api_providers.id', 'api_providers.name')
            ->orderByDesc('errors_count')
            ->first();

        return [
            'cards' => [
                'requests_today' => (clone $base)->where('requested_at', '>=', $today)->count(),
                'errors_today' => (clone $base)->where('requested_at', '>=', $today)->where('success', false)->count(),
                'average_response_time_ms' => round((float) (clone $base)->where('requested_at', '>=', $today)->avg('duration_ms'), 2),
                'status_429_today' => (clone $base)->where('requested_at', '>=', $today)->where('status_code', 429)->count(),
                'ssl_curl_errors_today' => (clone $base)->where('requested_at', '>=', $today)->where('error_message', 'like', '%SSL certificate%')->count(),
                'provider_with_most_errors' => $providerWithMostErrors?->name,
            ],
            'data' => $filtered->paginate(50),
        ];
    }

    public function exportRequestLogs(Request $request, CsvExporter $csv)
    {
        $rows = ApiRequestLog::with('provider:id,name')->latest('requested_at')->get()->map(fn (ApiRequestLog $log) => [
            $log->id, $log->provider?->name, $log->sync_job_id, $log->method, $log->endpoint, $log->status_code,
            $log->success ? 'true' : 'false', $log->duration_ms, $log->requested_at?->toDateTimeString(),
            $log->error_message, str($log->response_excerpt)->limit(300)->toString(),
        ]);

        return $csv->download('request-logs.csv', ['id', 'provider', 'sync_job_id', 'method', 'endpoint', 'status_code', 'success', 'duration_ms', 'requested_at', 'error_message', 'response_excerpt'], $rows);
    }

    public function exportSyncJobs(CsvExporter $csv)
    {
        $rows = SyncJob::with('provider:id,name')->latest()->get()->map(fn (SyncJob $job) => [
            $job->id, $job->provider?->name, $job->type, $job->source, $job->is_incremental ? 'true' : 'false',
            $job->status, $job->progress_percent, $job->total_items, $job->processed_items,
            $job->created_items, $job->updated_items, $job->failed_items, $job->started_at?->toDateTimeString(),
            $job->finished_at?->toDateTimeString(), $job->last_error,
        ]);

        return $csv->download('sync-jobs.csv', ['id', 'provider', 'type', 'source', 'incremental', 'status', 'progress', 'total', 'processed', 'created', 'updated', 'failed', 'started_at', 'finished_at', 'last_error'], $rows);
    }

    public function exportSyncJobItems(SyncJob $job, CsvExporter $csv)
    {
        $rows = $job->items()->latest()->get()->map(fn (SyncJobItem $item) => [
            $item->id, $item->status, $item->entity_type, $item->entity_id, $item->external_id,
            $item->action, $item->error_message, $item->created_at?->toDateTimeString(),
        ]);

        return $csv->download("sync-job-{$job->id}-items.csv", ['id', 'status', 'entity_type', 'entity_id', 'external_id', 'action', 'error_message', 'created_at'], $rows);
    }

    public function syncSchedules()
    {
        return SyncSchedule::with(['provider:id,name,slug', 'lastSyncJob:id,status,type'])->latest()->paginate(25);
    }

    public function storeSyncSchedule(Request $request, SyncScheduleService $service)
    {
        $data = $request->validate([
            'api_provider_id' => ['required', 'exists:api_providers,id'],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'league_id' => ['nullable', 'exists:leagues,id'],
            'season_id' => ['nullable', 'exists:seasons,id'],
            'type' => ['required', Rule::in(['sync_leagues', 'sync_teams', 'sync_matches', 'sync_standings', 'sync_match_statistics'])],
            'name' => ['required', 'string', 'max:160'],
            'frequency' => ['nullable', Rule::in(['hourly', 'every_6_hours', 'every_12_hours', 'daily', 'weekly', 'custom', 'custom_cron'])],
            'cron_expression' => ['nullable', 'string', 'max:120'],
            'config' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $schedule = SyncSchedule::create($data + ['frequency' => 'daily']);
        $schedule->update(['next_run_at' => $service->nextRunAt($schedule)]);

        return response()->json($schedule->fresh()->load('provider:id,name,slug'), 201);
    }

    public function updateSyncSchedule(Request $request, SyncSchedule $schedule, SyncScheduleService $service)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'frequency' => ['sometimes', Rule::in(['hourly', 'every_6_hours', 'every_12_hours', 'daily', 'weekly', 'custom', 'custom_cron'])],
            'cron_expression' => ['nullable', 'string', 'max:120'],
            'config' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $schedule->update($data);
        $schedule->update(['next_run_at' => $service->nextRunAt($schedule)]);

        return $schedule->fresh();
    }

    public function runSyncSchedule(SyncSchedule $schedule, SyncLockService $locks)
    {
        $scope = [
            'api_provider_id' => $schedule->api_provider_id,
            'sport_id' => $schedule->sport_id,
            'league_id' => $schedule->league_id,
            'season_id' => $schedule->season_id,
            'type' => $schedule->type,
            'config' => $schedule->config,
        ];

        if ($locks->hasDuplicate($scope)) {
            return response()->json(['message' => 'A pending or running sync job with the same scope already exists.'], 409);
        }

        $job = SyncJob::create($scope + [
            'sync_schedule_id' => $schedule->id,
            'source' => 'scheduled',
            'is_incremental' => filled(data_get($schedule->config, 'updated_since')),
            'status' => 'pending',
            'progress_percent' => 0,
        ]);
        RunSportsDataSyncJob::dispatch($job->id);

        return $job;
    }

    public function alerts()
    {
        return SystemAlert::latest()->paginate(25);
    }

    public function readAlert(SystemAlert $alert)
    {
        $alert->update(['is_read' => true]);
        return $alert->fresh();
    }

    public function resolveAlert(SystemAlert $alert)
    {
        $alert->update(['is_read' => true, 'resolved_at' => now()]);
        return $alert->fresh();
    }

    public function queueHealth(SystemAlertService $alerts)
    {
        $failedJobsCount = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
        $pendingJobsCount = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $staleRunning = SyncJob::where('status', 'running')->where('started_at', '<', now()->subMinutes(60))->count();

        if (($pendingJobsCount ?? 0) > 10 || $staleRunning > 0 || ($failedJobsCount ?? 0) > 0) {
            $alerts->createOnce('queue_attention', 'warning', 'Queue needs attention', 'There are pending, failed, or stale queue jobs.');
        }

        return [
            'queue_connection' => config('queue.default'),
            'pending_jobs_count' => $pendingJobsCount,
            'failed_jobs_count' => $failedJobsCount,
            'last_processed_sync_job' => SyncJob::whereIn('status', ['completed', 'failed', 'cancelled'])->latest('finished_at')->first(),
            'running_sync_jobs_count' => SyncJob::where('status', 'running')->count(),
            'stale_running_jobs_count' => $staleRunning,
            'recommendation' => $staleRunning > 0 || ($failedJobsCount ?? 0) > 0 ? 'Check Supervisor queue worker and run futia:sync:recover-stale if needed.' : 'Queue looks healthy.',
        ];
    }

    public function dataCoverage(Request $request)
    {
        $rows = Season::query()
            ->with(['league.country:id,name,code', 'league.sport:id,name,slug'])
            ->when($request->filled('season'), fn ($query) => $query->where('year', $request->integer('season')))
            ->when($request->filled('league_id'), fn ($query) => $query->where('league_id', $request->integer('league_id')))
            ->when($request->filled('sport'), fn ($query) => $query->whereHas('league.sport', fn ($sport) => $sport->where('slug', $request->query('sport'))))
            ->when($request->filled('country'), fn ($query) => $query->whereHas('league.country', fn ($country) => $country->where('code', $request->query('country'))))
            ->latest('year')
            ->get()
            ->map(function (Season $season) use ($request) {
                $matches = SportsMatch::where('season_id', $season->id)
                    ->when($request->filled('provider'), fn ($query) => $query->whereHas('provider', fn ($provider) => $provider->where('slug', $request->query('provider'))));
                $matchIds = (clone $matches)->pluck('id');

                return [
                    'league' => $season->league?->only(['id', 'name', 'slug']),
                    'country' => $season->league?->country?->only(['id', 'name', 'code']),
                    'sport' => $season->league?->sport?->only(['id', 'name', 'slug']),
                    'season' => $season->only(['id', 'year', 'name']),
                    'matches_total' => (clone $matches)->count(),
                    'matches_finished' => (clone $matches)->where('status', 'finished')->count(),
                    'matches_scheduled' => (clone $matches)->where('status', 'scheduled')->count(),
                    'teams_count' => DB::table('matches')->where('season_id', $season->id)->select('home_team_id as team_id')->union(DB::table('matches')->where('season_id', $season->id)->select('away_team_id as team_id'))->distinct()->count(),
                    'standings_count' => \App\Models\Standing::where('season_id', $season->id)->count(),
                    'statistics_count' => MatchStatistic::whereIn('match_id', $matchIds)->count(),
                    'last_synced_at' => (clone $matches)->max('last_synced_at'),
                    'providers' => ApiProvider::whereIn('id', (clone $matches)->whereNotNull('external_provider_id')->distinct()->pluck('external_provider_id'))->get(['id', 'name', 'slug']),
                ];
            });

        return [
            'summary' => [
                'sports' => Sport::count(),
                'countries' => Country::count(),
                'leagues' => League::count(),
                'seasons' => Season::count(),
                'teams' => Team::count(),
                'matches' => SportsMatch::count(),
                'finished_matches' => SportsMatch::where('status', 'finished')->count(),
                'scheduled_matches' => SportsMatch::where('status', 'scheduled')->count(),
                'statistics' => MatchStatistic::count(),
                'standings' => \App\Models\Standing::count(),
            ],
            'rows' => $rows->values(),
        ];
    }

    public function providerHealth()
    {
        $today = now()->startOfDay();

        return ApiProvider::withCount([
            'keys as active_keys_count' => fn ($query) => $query->where('is_active', true),
            'keys as cooldown_keys_count' => fn ($query) => $query->where('cooldown_until', '>', now()),
        ])->orderBy('priority')->get()->map(function (ApiProvider $provider) use ($today) {
            $logs = ApiRequestLog::where('api_provider_id', $provider->id)->where('requested_at', '>=', $today);
            $lastSuccess = ApiRequestLog::where('api_provider_id', $provider->id)->where('success', true)->latest('requested_at')->first();
            $lastError = ApiRequestLog::where('api_provider_id', $provider->id)->where('success', false)->latest('requested_at')->first();

            return [
                'id' => $provider->id,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'status' => $provider->status,
                'is_active' => $provider->is_active,
                'active_keys_count' => $provider->active_keys_count,
                'requests_today' => (clone $logs)->count(),
                'errors_today' => (clone $logs)->where('success', false)->count(),
                'last_success_at' => $lastSuccess?->requested_at,
                'last_error_at' => $lastError?->requested_at,
                'last_error' => $lastError?->error_message ?? $provider->last_error,
                'cooldown_keys_count' => $provider->cooldown_keys_count,
                'rate_limit_hits_today' => (clone $logs)->where('status_code', 429)->count(),
                'average_response_time_ms' => round((float) (clone $logs)->avg('duration_ms'), 2),
                'last_sync_job' => SyncJob::where('api_provider_id', $provider->id)->latest()->first(),
            ];
        });
    }

    public function homepageSettings()
    {
        return SiteSetting::homepage();
    }

    public function updateHomepageSettings(Request $request)
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:120'],
            'nav_badge' => ['nullable', 'string', 'max:160'],
            'hero_title' => ['required', 'string', 'max:180'],
            'hero_subtitle' => ['required', 'string', 'max:500'],
            'hero_image_url' => ['nullable', 'url', 'max:500'],
            'primary_cta_label' => ['required', 'string', 'max:80'],
            'primary_cta_url' => ['required', 'string', 'max:180'],
            'secondary_cta_label' => ['required', 'string', 'max:80'],
            'secondary_cta_url' => ['required', 'string', 'max:180'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'features' => ['required', 'array', 'size:3'],
            'features.*.title' => ['required', 'string', 'max:80'],
            'features.*.description' => ['required', 'string', 'max:220'],
        ]);

        return SiteSetting::updateHomepage($data);
    }

    public function entityIndex(string $entity)
    {
        $map = [
            'sports' => Sport::class,
            'countries' => Country::class,
            'leagues' => League::class,
            'seasons' => Season::class,
            'teams' => Team::class,
            'matches' => SportsMatch::class,
        ];

        abort_unless(isset($map[$entity]), 404);
        return $map[$entity]::query()->latest()->paginate(25);
    }

    private function providerRules(Request $request, ?ApiProvider $provider = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:120', Rule::unique('api_providers', 'slug')->ignore($provider)],
            'type' => ['sometimes', 'string', 'max:60'],
            'base_url' => ['nullable', 'url'],
            'website_url' => ['nullable', 'url'],
            'docs_url' => ['nullable', 'url'],
            'developer_url' => ['nullable', 'url'],
            'is_active' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive', 'planned', 'error'])],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1'],
            'rate_limit_per_day' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'config' => ['nullable', 'array'],
        ]);
    }

    private function planRules(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('plans', 'slug')->ignore($plan)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'allow_all' => ['boolean'],
            'requests_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_active_api_keys' => ['required', 'integer', 'min:1', 'max:100'],
            'access_rules' => ['array'],
            'access_rules.*.scope_type' => ['required', Rule::in(['region', 'country', 'league', 'season'])],
            'access_rules.*.region' => ['nullable', 'required_if:access_rules.*.scope_type,region', Rule::in(['americas'])],
            'access_rules.*.country_id' => ['nullable', 'required_if:access_rules.*.scope_type,country', 'exists:countries,id'],
            'access_rules.*.league_id' => ['nullable', 'required_if:access_rules.*.scope_type,league', 'exists:leagues,id'],
            'access_rules.*.season_id' => ['nullable', 'required_if:access_rules.*.scope_type,season', 'exists:seasons,id'],
        ]);
    }

    private function syncPlanAccessRules(Plan $plan, array $rules): void
    {
        $plan->accessRules()->delete();

        foreach ($rules as $rule) {
            $plan->accessRules()->create([
                'scope_type' => $rule['scope_type'],
                'region' => $rule['scope_type'] === 'region' ? ($rule['region'] ?? null) : null,
                'country_id' => $rule['scope_type'] === 'country' ? ($rule['country_id'] ?? null) : null,
                'league_id' => $rule['scope_type'] === 'league' ? ($rule['league_id'] ?? null) : null,
                'season_id' => $rule['scope_type'] === 'season' ? ($rule['season_id'] ?? null) : null,
            ]);
        }
    }
}
