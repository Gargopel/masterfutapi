<?php

namespace Tests\Feature;

use App\Jobs\RunSportsDataSyncJob;
use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\SyncJob;
use App\Models\SyncSchedule;
use App\Models\User;
use App\Services\SportsData\FullProviderSyncService;
use App\Services\SportsData\SportsDataNormalizer;
use App\Services\SportsData\SportsDataSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_job_executes_sync_job_and_releases_lock(): void
    {
        $this->seed();
        $provider = $this->activate('api-football');
        Http::fake(['v3.football.api-sports.io/leagues*' => Http::response(['response' => []])]);
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending']);

        app(RunSportsDataSyncJob::class, ['syncJobId' => $job->id])->handle(
            app(\App\Services\SportsData\SportsDataSyncService::class),
            app(\App\Services\SportsData\SyncLockService::class),
            app(\App\Services\SportsData\SystemAlertService::class),
        );

        $this->assertSame('completed', $job->fresh()->status);

        $next = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending']);
        $this->assertNotNull(app(\App\Services\SportsData\SyncLockService::class)->acquire($next));
    }

    public function test_admin_run_dispatches_job_to_queue(): void
    {
        $this->seed();
        Queue::fake();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending']);

        $this->actingAs($admin)->postJson("/admin/api/sync-jobs/{$job->id}/run")->assertOk();

        Queue::assertPushed(RunSportsDataSyncJob::class, fn ($queued) => $queued->syncJobId === $job->id);
    }

    public function test_sync_run_command_dispatches_by_default_and_sync_flag_runs_directly(): void
    {
        $this->seed();
        $provider = $this->activate('api-football');
        SyncSchedule::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'name' => 'Due', 'frequency' => 'hourly', 'is_active' => true, 'next_run_at' => now()->subMinute()]);

        Queue::fake();
        Artisan::call('futia:sync:run');
        Queue::assertPushed(RunSportsDataSyncJob::class);

        Http::fake(['v3.football.api-sports.io/leagues*' => Http::response(['response' => []])]);
        Artisan::call('futia:sync:run', ['--sync' => true]);
        $this->assertDatabaseHas('sync_jobs', ['status' => 'completed']);
    }

    public function test_duplicate_scope_is_rejected_and_schedule_next_runs_are_calculated(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();
        SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending', 'config' => ['a' => 1]]);

        $this->actingAs($admin)->postJson('/admin/api/sync-jobs', ['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'config' => ['a' => 1]])->assertStatus(409);

        foreach (['hourly', 'daily', 'weekly', 'every_12_hours'] as $frequency) {
            $schedule = SyncSchedule::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'name' => $frequency, 'frequency' => $frequency]);
            $this->assertTrue(app(\App\Services\SportsData\SyncScheduleService::class)->nextRunAt($schedule)->isFuture());
        }
    }

    public function test_queue_health_and_recover_stale(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'running', 'started_at' => now()->subHours(2)]);

        $this->actingAs($admin)->getJson('/admin/api/system/queue-health')->assertOk()->assertJsonPath('stale_running_jobs_count', 1);
        Artisan::call('futia:sync:recover-stale', ['--minutes' => 60]);

        $this->assertSame('failed', $job->fresh()->status);
        $this->assertDatabaseHas('system_alerts', ['type' => 'stale_sync_job']);
    }

    public function test_schedules_api_lists_creates_updates_and_runs_now(): void
    {
        $this->seed();
        Queue::fake();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();

        $response = $this->actingAs($admin)->postJson('/admin/api/schedules', [
            'api_provider_id' => $provider->id,
            'type' => 'sync_leagues',
            'name' => 'Daily leagues',
            'frequency' => 'daily',
            'config' => [],
            'is_active' => true,
        ])->assertCreated();

        $id = $response->json('id');
        $this->actingAs($admin)->getJson('/admin/api/schedules')->assertOk()->assertJsonFragment(['name' => 'Daily leagues']);
        $this->actingAs($admin)->patchJson("/admin/api/schedules/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);
        $this->actingAs($admin)->postJson("/admin/api/schedules/{$id}/run")->assertCreated();
        Queue::assertPushed(RunSportsDataSyncJob::class);
    }

    public function test_admin_can_schedule_full_provider_sync(): void
    {
        Queue::fake();
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = $this->activate('football-data');

        $this->actingAs($admin)->postJson("/admin/api/providers/{$provider->id}/full-sync", [
            'request_interval_seconds' => 60,
            'seasons' => [2026],
        ])->assertCreated()->assertJsonPath('type', FullProviderSyncService::TYPE);

        $job = SyncJob::where('type', FullProviderSyncService::TYPE)->first();
        $this->assertNotNull($job);
        $this->assertSame(60, $job->config['request_interval_seconds']);
        Queue::assertPushed(RunSportsDataSyncJob::class);
    }

    public function test_full_provider_sync_plans_children_with_interval(): void
    {
        Queue::fake();
        $this->seed();
        $provider = $this->activate('football-data');
        $normalizer = app(SportsDataNormalizer::class);
        $league = $normalizer->league($provider, [
            'external_id' => '2013',
            'name' => 'Campeonato Brasileiro Serie A',
            'country' => $normalizer->country('Brazil', 'BRA'),
            'type' => 'LEAGUE',
            'raw_payload' => ['id' => 2013, 'code' => 'BSA', 'name' => 'Campeonato Brasileiro Serie A'],
        ]);

        $parent = SyncJob::create([
            'api_provider_id' => $provider->id,
            'type' => FullProviderSyncService::TYPE,
            'status' => 'pending',
            'config' => ['request_interval_seconds' => 60, 'seasons' => [2026]],
        ]);

        app(SportsDataSyncService::class)->run($parent);

        $children = SyncJob::where('parent_sync_job_id', $parent->id)->orderBy('id')->get();
        $this->assertCount(2, $children);
        $this->assertSame('sync_leagues', $children[0]->type);
        $this->assertSame(FullProviderSyncService::TYPE, $children[1]->type);
        $this->assertSame('expand_after_leagues', $children[1]->config['phase']);
        $this->assertNotNull($children[0]->available_at);
        $this->assertNotNull($children[1]->available_at);
        $this->assertTrue($children[1]->available_at->greaterThan($children[0]->available_at));
        $this->assertSame('completed', $parent->fresh()->status);
        Queue::assertPushed(RunSportsDataSyncJob::class, 2);

        app(SportsDataSyncService::class)->run($children[1]);
        $matchChild = SyncJob::where('parent_sync_job_id', $children[1]->id)->where('type', 'sync_matches')->first();
        $this->assertNotNull($matchChild);
        $this->assertSame($league->id, $matchChild->league_id);
        $this->assertSame('BSA', $matchChild->config['competition_code']);
        $this->assertSame(2026, $matchChild->config['season']);
    }

    public function test_sync_run_command_ignores_jobs_that_are_not_available_yet(): void
    {
        $this->seed();
        $provider = $this->activate('api-football');
        SyncJob::create([
            'api_provider_id' => $provider->id,
            'type' => 'sync_leagues',
            'status' => 'pending',
            'available_at' => now()->addHour(),
        ]);

        $this->artisan('futia:sync:run --sync --limit=1')->assertSuccessful();

        $this->assertDatabaseHas('sync_jobs', [
            'api_provider_id' => $provider->id,
            'type' => 'sync_leagues',
            'status' => 'pending',
        ]);
    }

    public function test_metadata_includes_freshness(): void
    {
        $this->seed();
        $this->withHeaders($this->apiHeaders(path: '/api/v1/metadata'))->getJson('/api/v1/metadata')
            ->assertOk()
            ->assertJsonStructure(['freshness' => ['last_successful_sync_at', 'last_data_refresh_at', 'running_updates_count']])
            ->assertJsonMissingPath('freshness.active_providers_count')
            ->assertJsonMissingPath('providers_with_data');
    }

    private function activate(string $slug): ApiProvider
    {
        $provider = ApiProvider::where('slug', $slug)->firstOrFail();
        $provider->update(['is_active' => true, 'status' => 'active']);
        ApiProviderKey::create(['api_provider_id' => $provider->id, 'name' => 'Test', 'encrypted_key' => 'test-key', 'key_hint' => '****-key', 'is_active' => true, 'requests_per_minute' => 100, 'requests_per_day' => 1000]);

        return $provider->fresh();
    }
}
