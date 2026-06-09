<?php

namespace Tests\Feature;

use App\Jobs\RunSportsDataSyncJob;
use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\SyncJob;
use App\Models\SyncSchedule;
use App\Models\User;
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

    public function test_metadata_includes_freshness(): void
    {
        $this->seed();
        $this->getJson('/api/v1/metadata')
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
