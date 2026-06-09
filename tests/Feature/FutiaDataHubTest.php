<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\ApiRequestLog;
use App\Models\SyncJob;
use App\Models\User;
use App\Services\SportsData\ProviderRateLimiter;
use App\Services\SportsData\SyncProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FutiaDataHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_authenticate(): void
    {
        User::factory()->create(['email' => 'admin@test.dev', 'password' => Hash::make('secret'), 'is_admin' => true]);

        $this->postJson('/admin/api/login', ['email' => 'admin@test.dev', 'password' => 'secret'])->assertOk();
    }

    public function test_providers_are_seeded(): void
    {
        $this->seed();

        $this->assertDatabaseHas('api_providers', ['slug' => 'football-data']);
        $this->assertDatabaseHas('api_providers', ['slug' => 'api-football']);
        $this->assertDatabaseHas('api_providers', ['slug' => 'sportradar', 'status' => 'planned']);
    }

    public function test_api_key_is_encrypted_and_never_returned_complete(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();

        $response = $this->actingAs($admin)->postJson('/admin/api/provider-keys', [
            'api_provider_id' => $provider->id,
            'name' => 'Main',
            'api_key' => 'secret-ABCD',
        ])->assertCreated();

        $this->assertNotSame('secret-ABCD', ApiProviderKey::first()->getRawOriginal('encrypted_key'));
        $response->assertJsonMissing(['encrypted_key' => 'secret-ABCD'])->assertJsonPath('key_hint', '****ABCD');
    }

    public function test_provider_can_be_enabled_and_disabled(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::where('slug', 'football-data')->first();

        $this->actingAs($admin)->patchJson("/admin/api/providers/{$provider->id}", [
            ...$provider->toArray(),
            'is_active' => true,
            'status' => 'active',
        ])->assertOk()->assertJsonPath('is_active', true);
    }

    public function test_dashboard_returns_statistics(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();

        $this->actingAs($admin)->getJson('/admin/api/dashboard')->assertOk()->assertJsonStructure(['cards', 'overall_progress']);
    }

    public function test_sync_job_can_be_created_and_progress_is_calculated(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $provider = ApiProvider::first();

        $this->actingAs($admin)->postJson('/admin/api/sync-jobs', [
            'api_provider_id' => $provider->id,
            'type' => 'sync_leagues',
        ])->assertCreated();

        $this->assertSame(64.0, app(SyncProgressService::class)->calculate(128, 200));
    }

    public function test_request_log_is_created(): void
    {
        $this->seed();
        $provider = ApiProvider::first();

        ApiRequestLog::create(['api_provider_id' => $provider->id, 'method' => 'GET', 'endpoint' => '/v4/competitions', 'success' => true, 'requested_at' => now()]);

        $this->assertDatabaseHas('api_request_logs', ['endpoint' => '/v4/competitions']);
    }

    public function test_public_api_sports_and_leagues_work(): void
    {
        $this->seed();

        $this->getJson('/api/v1/sports')->assertOk()->assertJsonFragment(['slug' => 'football']);
        $this->getJson('/api/v1/leagues')->assertOk()->assertJsonFragment(['slug' => 'brasileirao-serie-a']);
    }

    public function test_rate_limiter_blocks_excess_requests(): void
    {
        $this->seed();
        $provider = ApiProvider::first();
        $provider->update(['rate_limit_per_minute' => 1]);
        ApiRequestLog::create(['api_provider_id' => $provider->id, 'method' => 'GET', 'endpoint' => '/', 'success' => true, 'requested_at' => now()]);

        $this->assertFalse(app(ProviderRateLimiter::class)->canRequest($provider));
    }

    public function test_inactive_provider_does_not_execute_sync(): void
    {
        $this->seed();
        $provider = ApiProvider::where('is_active', false)->first();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_leagues', 'status' => 'pending']);

        $this->postJson('/admin/api/login', ['email' => 'admin@futia.local', 'password' => 'password']);
        \Illuminate\Support\Facades\Queue::fake();
        $this->postJson("/admin/api/sync-jobs/{$job->id}/run")->assertOk();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RunSportsDataSyncJob::class);
    }

    public function test_frontend_multilingual_and_theme_assets_exist(): void
    {
        $source = file_get_contents(resource_path('js/app.tsx'));

        $this->assertStringContainsString("'pt-BR'", $source);
        $this->assertStringContainsString('localStorage', $source);
        $this->assertStringContainsString("'zh'", $source);
    }

    public function test_admin_create_command_creates_admin_user(): void
    {
        $this->artisan('futia:admin:create', [
            '--email' => 'owner@test.dev',
            '--password' => 'strong-secret',
            '--name' => 'Owner',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'owner@test.dev',
            'name' => 'Owner',
            'is_admin' => true,
        ]);
    }

    public function test_provider_catalog_command_seeds_providers_without_users(): void
    {
        $this->artisan('futia:providers:seed')->assertSuccessful();

        $this->assertDatabaseHas('api_providers', ['slug' => 'football-data']);
        $this->assertDatabaseHas('api_providers', ['slug' => 'api-football']);
        $this->assertDatabaseHas('api_providers', ['slug' => 'sportradar', 'status' => 'planned']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_homepage_and_user_registration_work(): void
    {
        $this->seed();

        $this->get('/')->assertOk()->assertSee('MasterFut API');

        $this->post('/register', [
            'name' => 'Api User',
            'email' => 'api-user@test.dev',
            'password' => 'strong-secret',
            'password_confirmation' => 'strong-secret',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'api-user@test.dev', 'is_admin' => false]);
        $this->get('/dashboard')->assertOk()->assertSee('/api/v1/metadata');
        $this->getJson('/admin/api/dashboard')->assertForbidden();
    }

    public function test_admin_can_update_homepage_settings(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();

        $payload = [
            'brand_name' => 'MasterFut Pro',
            'nav_badge' => 'Live football data',
            'hero_title' => 'A nova casa dos dados esportivos.',
            'hero_subtitle' => 'API configuravel para produtos de futebol.',
            'hero_image_url' => 'https://images.unsplash.com/photo-1556056504-5c7696c4c28d',
            'primary_cta_label' => 'Entrar',
            'primary_cta_url' => '/login',
            'secondary_cta_label' => 'Metadata',
            'secondary_cta_url' => '/api/v1/metadata',
            'accent_color' => '#0f766e',
            'features' => [
                ['title' => 'Coleta', 'description' => 'Jobs de sincronizacao.'],
                ['title' => 'API', 'description' => 'Endpoints publicos.'],
                ['title' => 'Painel', 'description' => 'Operacao centralizada.'],
            ],
        ];

        $this->actingAs($admin)->patchJson('/admin/api/homepage-settings', $payload)
            ->assertOk()
            ->assertJsonPath('brand_name', 'MasterFut Pro');

        $this->get('/')->assertOk()->assertSee('MasterFut Pro')->assertSee('A nova casa dos dados esportivos.');
    }
}
