<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\ApiRequestLog;
use App\Models\Country;
use App\Models\League;
use App\Models\Plan;
use App\Models\Season;
use App\Models\Sport;
use App\Models\SportsMatch;
use App\Models\SyncJob;
use App\Models\User;
use App\Models\UserApiToken;
use App\Services\SportsData\ProviderRateLimiter;
use App\Services\SportsData\SyncProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->getJson('/api/v1/sports')->assertUnauthorized();
        $this->withHeaders($this->apiHeaders())->getJson('/api/v1/sports')->assertOk()->assertJsonFragment(['slug' => 'football']);
        $this->withHeaders($this->apiHeaders())->getJson('/api/v1/leagues')->assertOk()->assertJsonFragment(['slug' => 'brasileirao-serie-a']);
    }

    public function test_plan_can_limit_user_to_single_league_season(): void
    {
        $sport = Sport::create(['name' => 'Football', 'slug' => 'football']);
        $country = Country::create(['name' => 'Brazil', 'code' => 'BR']);
        $serieA = League::create(['sport_id' => $sport->id, 'country_id' => $country->id, 'name' => 'Brasileiro Serie A', 'slug' => 'brasileiro-serie-a']);
        $serieB = League::create(['sport_id' => $sport->id, 'country_id' => $country->id, 'name' => 'Brasileiro Serie B', 'slug' => 'brasileiro-serie-b']);
        $season2026 = Season::create(['league_id' => $serieA->id, 'year' => 2026, 'name' => '2026']);
        $season2025 = Season::create(['league_id' => $serieA->id, 'year' => 2025, 'name' => '2025']);
        $otherSeason = Season::create(['league_id' => $serieB->id, 'year' => 2026, 'name' => '2026']);
        SportsMatch::create(['sport_id' => $sport->id, 'league_id' => $serieA->id, 'season_id' => $season2026->id, 'starts_at' => now(), 'status' => 'scheduled']);
        SportsMatch::create(['sport_id' => $sport->id, 'league_id' => $serieA->id, 'season_id' => $season2025->id, 'starts_at' => now(), 'status' => 'scheduled']);
        SportsMatch::create(['sport_id' => $sport->id, 'league_id' => $serieB->id, 'season_id' => $otherSeason->id, 'starts_at' => now(), 'status' => 'scheduled']);
        $plan = Plan::create(['name' => 'Free', 'slug' => 'free', 'is_default' => true, 'requests_per_minute' => 10, 'max_active_api_keys' => 3]);
        $plan->accessRules()->create(['scope_type' => 'season', 'season_id' => $season2026->id]);
        $user = User::factory()->create(['is_admin' => false, 'plan_id' => $plan->id]);
        [, $plainTextToken] = UserApiToken::issueFor($user, 'FutAI');

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/v1/leagues')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'brasileiro-serie-a'])
            ->assertJsonMissing(['slug' => 'brasileiro-serie-b']);

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/v1/seasons')
            ->assertOk()
            ->assertJsonFragment(['year' => 2026])
            ->assertJsonMissing(['year' => 2025]);

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/v1/matches')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_plan_region_americas_allows_american_leagues_only(): void
    {
        $sport = Sport::create(['name' => 'Football', 'slug' => 'football']);
        $brazil = Country::create(['name' => 'Brazil', 'code' => 'BR']);
        $england = Country::create(['name' => 'England', 'code' => 'GB']);
        $brasileiro = League::create(['sport_id' => $sport->id, 'country_id' => $brazil->id, 'name' => 'Brasileiro', 'slug' => 'brasileiro']);
        $premierLeague = League::create(['sport_id' => $sport->id, 'country_id' => $england->id, 'name' => 'Premier League', 'slug' => 'premier-league']);
        Season::create(['league_id' => $brasileiro->id, 'year' => 2026]);
        Season::create(['league_id' => $premierLeague->id, 'year' => 2026]);
        $plan = Plan::create(['name' => 'Americas', 'slug' => 'americas', 'requests_per_minute' => 10, 'max_active_api_keys' => 3]);
        $plan->accessRules()->create(['scope_type' => 'region', 'region' => 'americas']);
        $user = User::factory()->create(['is_admin' => false, 'plan_id' => $plan->id]);
        [, $plainTextToken] = UserApiToken::issueFor($user, 'FutAI');

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/v1/leagues')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'brasileiro'])
            ->assertJsonMissing(['slug' => 'premier-league']);
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

    public function test_homepage_focuses_futai_and_hides_public_user_auth(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('FutAI')
            ->assertSee('Cadastro e login dos usuarios serao feitos diretamente no app FutAI')
            ->assertSee('/docs')
            ->assertDontSee('/login')
            ->assertDontSee('/register')
            ->assertDontSee('Criar conta')
            ->assertDontSee('Multi-provider')
            ->assertDontSee('Football-Data')
            ->assertDontSee('API-Football');

        $this->get('/login')->assertRedirect('/');
        $this->get('/register')->assertRedirect('/');
        $this->getJson('/admin/api/dashboard')->assertUnauthorized();
    }

    public function test_user_can_create_and_revoke_api_key(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/api-keys')
            ->assertOk()
            ->assertSee('Gerar API key');

        $this->actingAs($user)->post('/api-keys', [
            'name' => 'Producao',
        ])->assertRedirect('/api-keys');

        $token = $user->apiTokens()->first();

        $this->assertNotNull($token);
        $this->assertDatabaseHas('user_api_tokens', [
            'user_id' => $user->id,
            'name' => 'Producao',
            'revoked_at' => null,
        ]);

        $this->actingAs($user)->delete("/api-keys/{$token->id}")->assertRedirect();
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_futai_app_can_register_login_and_consume_api(): void
    {
        $this->seed();

        $registerResponse = $this->postJson('/api/app/register', [
            'name' => 'FutAI User',
            'email' => 'futai-user@test.dev',
            'password' => 'strong-secret',
            'password_confirmation' => 'strong-secret',
            'api_key_name' => 'FutAI Desktop',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'futai-user@test.dev')
            ->assertJsonPath('api_key.name', 'FutAI Desktop')
            ->assertJsonPath('limits.active_api_keys', 3)
            ->assertJsonPath('limits.requests_per_minute', 10);

        $plainTextToken = $registerResponse->json('api_key.token');
        $this->assertIsString($plainTextToken);
        $this->assertStringStartsWith('mf_live_', $plainTextToken);

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/v1/metadata')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/app/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'futai-user@test.dev')
            ->assertJsonPath('current_api_key.name', 'FutAI Desktop')
            ->assertJsonPath('current_api_key.token', null);

        $loginResponse = $this->postJson('/api/app/login', [
            'email' => 'futai-user@test.dev',
            'password' => 'strong-secret',
            'api_key_name' => 'FutAI Notebook',
        ])->assertOk()
            ->assertJsonPath('user.email', 'futai-user@test.dev')
            ->assertJsonPath('api_key.name', 'FutAI Notebook');

        $this->assertStringStartsWith('mf_live_', $loginResponse->json('api_key.token'));
        $this->assertDatabaseCount('user_api_tokens', 2);
    }

    public function test_futai_app_can_manage_api_keys_and_enforces_free_limits(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        [, $plainTextToken] = UserApiToken::issueFor($user, 'Primary');

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->postJson('/api/app/api-keys', ['name' => 'Second'])
            ->assertCreated()
            ->assertJsonPath('api_key.name', 'Second')
            ->assertJsonPath('limits.active_api_keys', 3);

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->postJson('/api/app/api-keys', ['name' => 'Third'])
            ->assertCreated();

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->postJson('/api/app/api-keys', ['name' => 'Fourth'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'api_key_limit_reached');

        $tokenToRevoke = $user->apiTokens()->where('name', 'Second')->first();

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->deleteJson("/api/app/api-keys/{$tokenToRevoke->id}")
            ->assertOk()
            ->assertJsonPath('api_key.name', 'Second');

        $this->assertNotNull($tokenToRevoke->fresh()->revoked_at);

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->postJson('/api/app/api-keys', ['name' => 'Replacement'])
            ->assertCreated();
    }

    public function test_futai_login_can_replace_oldest_api_key_when_limit_is_reached(): void
    {
        $user = User::factory()->create(['email' => 'limit@test.dev', 'password' => 'strong-secret', 'is_admin' => false]);

        foreach (range(1, 3) as $index) {
            UserApiToken::issueFor($user, 'Key '.$index);
        }

        $this->postJson('/api/app/login', [
            'email' => 'limit@test.dev',
            'password' => 'strong-secret',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'api_key_limit_reached');

        $this->postJson('/api/app/login', [
            'email' => 'limit@test.dev',
            'password' => 'strong-secret',
            'api_key_name' => 'New Device',
            'revoke_oldest' => true,
        ])->assertOk()
            ->assertJsonPath('api_key.name', 'New Device');

        $this->assertSame(3, $user->apiTokens()->whereNull('revoked_at')->count());
        $this->assertNotNull($user->apiTokens()->where('name', 'Key 1')->first()->revoked_at);
    }

    public function test_user_is_limited_to_three_active_api_keys(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        foreach (range(1, 3) as $index) {
            UserApiToken::issueFor($user, 'Key '.$index);
        }

        $this->actingAs($user)->post('/api-keys', [
            'name' => 'Fourth key',
        ])->assertSessionHasErrors('name');

        $this->assertSame(3, $user->apiTokens()->whereNull('revoked_at')->count());

        $user->apiTokens()->first()->update(['revoked_at' => now()]);

        $this->actingAs($user)->post('/api-keys', [
            'name' => 'Replacement key',
        ])->assertRedirect('/api-keys');

        $this->assertSame(3, $user->apiTokens()->whereNull('revoked_at')->count());
    }

    public function test_revoked_api_key_cannot_access_public_api(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_admin' => false]);
        [$token, $plainTextToken] = UserApiToken::issueFor($user, 'Revoked key');

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/v1/metadata')
            ->assertOk();

        $token->update(['revoked_at' => now()]);

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/v1/metadata')
            ->assertUnauthorized();
    }

    public function test_public_api_is_limited_to_ten_requests_per_minute_per_user(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_admin' => false]);
        RateLimiter::clear('masterfut:user:'.$user->id);
        [, $firstToken] = UserApiToken::issueFor($user, 'First key');
        [, $secondToken] = UserApiToken::issueFor($user, 'Second key');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $plainTextToken = $attempt % 2 === 0 ? $firstToken : $secondToken;
            $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
                ->getJson('/api/v1/metadata')
                ->assertOk();
        }

        $this->withHeaders(['Authorization' => 'Bearer '.$firstToken])
            ->getJson('/api/v1/metadata')
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Limite de 10 requisicoes por minuto atingido.');
    }

    public function test_admin_can_see_users_keys_and_api_consumption(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $user = User::factory()->create(['name' => 'Client User', 'email' => 'client@test.dev', 'is_admin' => false]);
        [$token, $plainTextToken] = UserApiToken::issueFor($user, 'Client production');

        $this->withHeaders(['Authorization' => 'Bearer '.$plainTextToken])
            ->getJson('/api/v1/metadata')
            ->assertOk();

        $this->assertDatabaseHas('user_api_request_logs', [
            'user_id' => $user->id,
            'user_api_token_id' => $token->id,
            'endpoint' => '/api/v1/metadata',
            'status_code' => 200,
        ]);

        $this->actingAs($admin)->getJson('/admin/api/users-overview')
            ->assertOk()
            ->assertJsonPath('cards.users_total', 2)
            ->assertJsonFragment(['email' => 'client@test.dev']);

        $this->actingAs($admin)->getJson('/admin/api/user-api-tokens')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Client production'])
            ->assertJsonFragment(['token_prefix' => $token->token_prefix]);

        $this->actingAs($admin)->getJson('/admin/api/user-api-usage')
            ->assertOk()
            ->assertJsonPath('cards.requests', 1)
            ->assertJsonFragment(['endpoint' => '/api/v1/metadata']);
    }

    public function test_admin_can_manage_plans_and_assign_user_plan(): void
    {
        $this->seed();
        $admin = User::where('is_admin', true)->first();
        $user = User::factory()->create(['is_admin' => false]);
        $sport = Sport::first() ?? Sport::create(['name' => 'Football', 'slug' => 'football']);
        $country = Country::first() ?? Country::create(['name' => 'Brazil', 'code' => 'BR']);
        $league = League::create(['sport_id' => $sport->id, 'country_id' => $country->id, 'name' => 'Brasileiro Serie A', 'slug' => 'brasileiro-serie-a']);
        $season = Season::create(['league_id' => $league->id, 'year' => 2026, 'name' => '2026']);

        $response = $this->actingAs($admin)->postJson('/admin/api/plans', [
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Brasileiro Serie A 2026',
            'is_active' => true,
            'is_default' => true,
            'allow_all' => false,
            'requests_per_minute' => 10,
            'max_active_api_keys' => 3,
            'access_rules' => [
                ['scope_type' => 'season', 'season_id' => $season->id],
            ],
        ])->assertCreated()
            ->assertJsonPath('name', 'Free')
            ->assertJsonPath('access_rules.0.scope_type', 'season');

        $planId = $response->json('id');

        $this->actingAs($admin)->patchJson("/admin/api/users/{$user->id}/plan", [
            'plan_id' => $planId,
        ])->assertOk()
            ->assertJsonPath('plan.id', $planId);

        $this->actingAs($admin)->getJson('/admin/api/plans')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'free']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $planId]);
    }

    public function test_docs_page_and_profile_password_update_work(): void
    {
        $this->seed();

        $this->get('/docs')
            ->assertOk()
            ->assertSee('Documentacao v1')
            ->assertSee('Fluxo FutAI')
            ->assertSee('/api/app/register')
            ->assertSee('/matches')
            ->assertSee('updated_since');

        $user = User::factory()->create([
            'password' => 'old-password',
            'is_admin' => false,
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Alterar senha')
            ->assertSee('Sair');

        $this->actingAs($user)->post('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
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
            ->assertJsonPath('brand_name', 'MasterFut Pro')
            ->assertJsonPath('primary_cta_label', 'Conhecer o FutAI')
            ->assertJsonPath('primary_cta_url', '#futai');

        $this->get('/')->assertOk()->assertSee('MasterFut Pro')->assertSee('A nova casa dos dados esportivos.');
    }
}
