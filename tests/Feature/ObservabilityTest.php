<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\ApiRequestLog;
use App\Models\League;
use App\Models\Season;
use App\Models\Sport;
use App\Models\SportsMatch;
use App\Models\SyncJob;
use App\Models\SyncJobItem;
use App\Models\SyncSchedule;
use App\Models\Team;
use App\Models\User;
use App\Services\SportsData\SportsDataSyncService;
use App\Services\SportsData\SyncProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_job_detail_returns_job_items_and_logs(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'completed']);
        SyncJobItem::create(['sync_job_id' => $job->id, 'entity_type' => 'league', 'external_id' => '71', 'status' => 'completed', 'action' => 'created']);
        ApiRequestLog::create(['api_provider_id' => $provider->id, 'sync_job_id' => $job->id, 'method' => 'GET', 'endpoint' => '/leagues', 'status_code' => 200, 'success' => true, 'requested_at' => now()]);

        $this->actingAs($admin)->getJson("/admin/api/sync-jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('job.id', $job->id)
            ->assertJsonPath('items.data.0.external_id', '71')
            ->assertJsonPath('request_logs.data.0.endpoint', '/leagues');
    }

    public function test_request_logs_filter_by_provider_status_and_sync_job(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'completed']);
        ApiRequestLog::create(['api_provider_id' => $provider->id, 'sync_job_id' => $job->id, 'method' => 'GET', 'endpoint' => '/status', 'status_code' => 429, 'success' => false, 'error_message' => 'Rate limit', 'requested_at' => now()]);

        $this->actingAs($admin)->getJson("/admin/api/request-logs?provider={$provider->id}&success=0&status_code=429&sync_job_id={$job->id}")
            ->assertOk()
            ->assertJsonPath('data.data.0.status_code', 429)
            ->assertJsonPath('cards.status_429_today', 1);
    }

    public function test_request_logs_are_linked_to_sync_job_during_sync(): void
    {
        $this->seed();
        $provider = $this->activate('api-football');
        Http::fake(['v3.football.api-sports.io/leagues*' => Http::response(['response' => []])]);

        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending']);
        app(SportsDataSyncService::class)->run($job);

        $this->assertDatabaseHas('api_request_logs', ['api_provider_id' => $provider->id, 'sync_job_id' => $job->id, 'endpoint' => '/leagues?page=1']);
        $this->assertSame('completed', $job->fresh()->status);
        $this->assertSame('100.00', (string) $job->fresh()->progress_percent);
    }

    public function test_curl_error_35_gets_friendly_ssl_handshake_message(): void
    {
        $this->seed();
        $provider = $this->activate('football-data');
        Http::fake([
            'api.football-data.org/v4/competitions' => Http::failedConnection('cURL error 35: OpenSSL SSL_connect: SSL_ERROR_SYSCALL'),
        ]);

        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending']);
        app(SportsDataSyncService::class)->run($job);

        $message = 'SSL/TLS handshake failed. Check server OpenSSL/cURL, CA certificates, outbound firewall, and IPv6 connectivity.';
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertSame($message, $job->fresh()->last_error);
        $this->assertDatabaseHas('api_request_logs', [
            'api_provider_id' => $provider->id,
            'success' => false,
            'error_message' => $message,
        ]);
    }

    public function test_rerun_and_cancel_behaviour(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_matches', 'status' => 'completed', 'config' => ['league_id' => 71]]);

        $this->actingAs($admin)->postJson("/admin/api/sync-jobs/{$job->id}/rerun")
            ->assertCreated()
            ->assertJsonPath('parent_sync_job_id', $job->id)
            ->assertJsonPath('status', 'pending');

        $pending = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending']);
        $this->actingAs($admin)->postJson("/admin/api/sync-jobs/{$pending->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $running = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'running']);
        $this->actingAs($admin)->postJson("/admin/api/sync-jobs/{$running->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('cancel_requested_at', fn ($value) => $value !== null);
    }

    public function test_progress_service_completion_and_failure(): void
    {
        $this->seed();
        $provider = ApiProvider::first();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending']);
        $service = app(SyncProgressService::class);

        $service->start($job, 4);
        $service->markItemCreated($job->fresh());
        $service->markItemUpdated($job->fresh());
        $this->assertSame(50.0, (float) $job->fresh()->progress_percent);

        $service->complete($job->fresh(), ['ok' => true]);
        $this->assertSame('100.00', (string) $job->fresh()->progress_percent);

        $failed = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'running']);
        $service->fail($failed, 'Boom');
        $this->assertSame('Boom', $failed->fresh()->last_error);
    }

    public function test_data_coverage_provider_health_and_metadata(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();
        $sport = Sport::where('slug', 'football')->first();
        $league = League::first();
        $season = Season::create(['league_id' => $league->id, 'year' => 2024, 'name' => '2024']);
        $home = Team::create(['sport_id' => $sport->id, 'name' => 'Home', 'slug' => 'home']);
        $away = Team::create(['sport_id' => $sport->id, 'name' => 'Away', 'slug' => 'away']);
        SportsMatch::create(['sport_id' => $sport->id, 'league_id' => $league->id, 'season_id' => $season->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id, 'external_provider_id' => $provider->id, 'external_id' => 'm1', 'starts_at' => now(), 'status' => 'scheduled', 'last_synced_at' => now()]);
        ApiRequestLog::create(['api_provider_id' => $provider->id, 'method' => 'GET', 'endpoint' => '/x', 'status_code' => 200, 'success' => true, 'duration_ms' => 42, 'requested_at' => now()]);

        $this->actingAs($admin)->getJson('/admin/api/data-coverage')->assertOk()->assertJsonPath('summary.matches', 1);
        $this->actingAs($admin)->getJson('/admin/api/providers/health')->assertOk()->assertJsonPath('0.requests_today', 1);
        $this->withHeaders($this->apiHeaders())->getJson('/api/v1/metadata')->assertOk()->assertJsonPath('api_version', 'v1')->assertJsonPath('supported_languages.0', 'pt-BR');
    }

    public function test_frontend_contains_new_translation_keys(): void
    {
        $source = file_get_contents(resource_path('js/app.tsx'));

        foreach (['jobDetail', 'requestLogs', 'providerHealth', 'emptyJobs'] as $key) {
            $this->assertStringContainsString($key, $source);
        }
    }

    public function test_retry_pagination_cooldown_schedules_csv_and_updated_since(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = $this->activate('api-football');

        Http::fake([
            'v3.football.api-sports.io/leagues*' => Http::sequence()
                ->push([], 500)
                ->push(['paging' => ['current' => 1, 'total' => 2], 'response' => [[
                    'league' => ['id' => 71, 'name' => 'Serie A', 'type' => 'League'],
                    'country' => ['name' => 'Brazil', 'code' => 'BR'],
                    'seasons' => [['year' => 2024]],
                ]]])
                ->push(['paging' => ['current' => 2, 'total' => 2], 'response' => [[
                    'league' => ['id' => 72, 'name' => 'Serie B', 'type' => 'League'],
                    'country' => ['name' => 'Brazil', 'code' => 'BR'],
                    'seasons' => [['year' => 2024]],
                ]]]),
        ]);

        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending', 'config' => ['max_pages' => 2, 'updated_since' => '2026-05-01T00:00:00'], 'is_incremental' => true]);
        app(SportsDataSyncService::class)->run($job);

        $this->assertSame('completed', $job->fresh()->status);
        $this->assertDatabaseHas('leagues', ['external_id' => '71']);
        $this->assertDatabaseHas('leagues', ['external_id' => '72']);
        $this->assertGreaterThanOrEqual(3, ApiRequestLog::where('sync_job_id', $job->id)->count());

        $footballData = $this->activate('football-data');
        Http::fake(['api.football-data.org/v4/competitions*' => Http::response([], 429)]);
        app(\App\Services\SportsData\ProviderRegistry::class)->make($footballData)->testConnection();
        $this->assertDatabaseHas('api_request_logs', ['api_provider_id' => $footballData->id, 'status_code' => 429]);
        $this->assertNotNull($footballData->keys()->first()->fresh()->cooldown_until);

        $schedule = SyncSchedule::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'name' => 'Hourly leagues', 'frequency' => 'hourly', 'is_active' => true, 'next_run_at' => now()->subMinute()]);
        $this->assertSame(1, app(\App\Services\SportsData\SyncScheduleService::class)->createDueJobs());
        $this->assertSame(0, app(\App\Services\SportsData\SyncScheduleService::class)->createDueJobs());
        $this->assertDatabaseHas('sync_jobs', ['sync_schedule_id' => $schedule->id, 'source' => 'scheduled']);

        $this->actingAs($admin)->get('/admin/api/request-logs/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($admin)->get('/admin/api/sync-jobs/export')->assertOk();
        $this->actingAs($admin)->get("/admin/api/sync-jobs/{$job->id}/items/export")->assertOk();

        $this->withHeaders($this->apiHeaders())->getJson('/api/v1/leagues?updated_since=2000-01-01T00:00:00')->assertOk()->assertJsonFragment(['current_page' => 1]);
    }

    public function test_cancel_preserves_processed_items_and_alerts_can_be_resolved(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'running', 'processed_items' => 2]);
        SyncJobItem::create(['sync_job_id' => $job->id, 'status' => 'completed', 'entity_type' => 'league', 'external_id' => '1']);

        app(SyncProgressService::class)->cancel($job);
        $this->assertSame(1, $job->items()->count());
        $this->assertSame(2, $job->fresh()->result['processed_until_cancel']);

        $alert = \App\Models\SystemAlert::create(['type' => 'sync_job_failed', 'severity' => 'error', 'title' => 'Failed', 'message' => 'Boom']);
        $this->actingAs($admin)->postJson("/admin/api/alerts/{$alert->id}/read")->assertOk()->assertJsonPath('is_read', true);
        $this->actingAs($admin)->postJson("/admin/api/alerts/{$alert->id}/resolve")->assertOk()->assertJsonPath('resolved_at', fn ($value) => $value !== null);
    }

    private function activate(string $slug): ApiProvider
    {
        $provider = ApiProvider::where('slug', $slug)->firstOrFail();
        $provider->update(['is_active' => true, 'status' => 'active']);
        ApiProviderKey::create(['api_provider_id' => $provider->id, 'name' => 'Test', 'encrypted_key' => 'test-key', 'key_hint' => '****-key', 'is_active' => true, 'requests_per_minute' => 100, 'requests_per_day' => 1000]);

        return $provider->fresh();
    }
}
