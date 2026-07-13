<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\ApiRequestLog;
use App\Models\League;
use App\Models\ProviderMapping;
use App\Models\Season;
use App\Models\Sport;
use App\Models\SportsMatch;
use App\Models\Standing;
use App\Models\SyncJob;
use App\Models\Team;
use App\Services\SportsData\ProviderRegistry;
use App\Services\SportsData\SportsDataSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_football_data_connection_and_match_sync_create_data_without_duplicates(): void
    {
        $this->seed();
        $provider = $this->activate('football-data');
        Http::fake([
            'api.football-data.org/v4/competitions' => Http::response(['competitions' => [['id' => 2013, 'code' => 'BSA', 'name' => 'Campeonato Brasileiro Serie A', 'type' => 'LEAGUE', 'area' => ['name' => 'Brazil', 'code' => 'BRA']]]]),
            'api.football-data.org/v4/competitions/BSA/matches*' => Http::response(['competition' => ['id' => 2013, 'code' => 'BSA', 'name' => 'Campeonato Brasileiro Serie A'], 'matches' => [[
                'id' => 9001,
                'utcDate' => '2026-05-01T19:00:00Z',
                'status' => 'FINISHED',
                'season' => ['startDate' => '2026-01-01', 'endDate' => '2026-12-31'],
                'homeTeam' => ['id' => 1, 'name' => 'Flamengo'],
                'awayTeam' => ['id' => 2, 'name' => 'Palmeiras'],
                'score' => ['fullTime' => ['home' => 2, 'away' => 1]],
            ]]]),
        ]);

        $result = app(ProviderRegistry::class)->make($provider)->testConnection();
        $this->assertTrue($result->success);

        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_matches', 'status' => 'pending', 'config' => ['competition_code' => 'BSA', 'season' => 2026]]);
        app(SportsDataSyncService::class)->run($job);
        app(SportsDataSyncService::class)->run($job->fresh());

        $this->assertSame(1, SportsMatch::where('external_provider_id', $provider->id)->where('external_id', '9001')->count());
        $this->assertDatabaseHas('teams', ['name' => 'Flamengo']);
        $this->assertDatabaseHas('provider_mappings', ['provider_id' => $provider->id, 'entity_type' => 'match', 'external_id' => '9001']);
        $this->assertSame('completed', $job->fresh()->status);
        $this->assertGreaterThanOrEqual(2, ApiRequestLog::where('api_provider_id', $provider->id)->count());
    }

    public function test_football_data_match_sync_skips_missing_teams_and_404_competitions(): void
    {
        $this->seed();
        $provider = $this->activate('football-data');
        Http::fake([
            'api.football-data.org/v4/competitions/BAD/matches*' => Http::response(['message' => 'The resource you are looking for does not exist.', 'error' => 404], 404),
            'api.football-data.org/v4/competitions/BSA/matches*' => Http::response(['competition' => ['id' => 2013, 'code' => 'BSA', 'name' => 'Campeonato Brasileiro Serie A'], 'matches' => [[
                'id' => 9002,
                'utcDate' => '2026-05-02T19:00:00Z',
                'status' => 'FINISHED',
                'season' => ['startDate' => '2026-01-01', 'endDate' => '2026-12-31'],
                'homeTeam' => ['id' => null, 'name' => null],
                'awayTeam' => ['id' => 2, 'name' => 'Palmeiras'],
                'score' => ['fullTime' => ['home' => 0, 'away' => 1]],
            ]]]),
        ]);

        $missing = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_matches', 'status' => 'pending', 'config' => ['competition_code' => 'BAD', 'season' => 2026]]);
        app(SportsDataSyncService::class)->run($missing);
        $this->assertSame('completed', $missing->fresh()->status);
        $this->assertSame('Competition or season is not available on Football-Data.org.', $missing->fresh()->result['skipped_reason']);

        $partial = SyncJob::create(['api_provider_id' => $provider->id, 'type' => 'sync_matches', 'status' => 'pending', 'config' => ['competition_code' => 'BSA', 'season' => 2026]]);
        app(SportsDataSyncService::class)->run($partial);
        $this->assertSame('completed', $partial->fresh()->status);
        $this->assertSame(1, $partial->fresh()->failed_items);
        $this->assertDatabaseMissing('matches', ['external_provider_id' => $provider->id, 'external_id' => '9002']);
    }

    public function test_api_football_syncs_leagues_teams_matches_standings_and_statistics(): void
    {
        $this->seed();
        $provider = $this->activate('api-football');
        Http::fake([
            'v3.football.api-sports.io/status' => Http::response(['response' => ['requests' => ['current' => 1]]]),
            'v3.football.api-sports.io/leagues*' => Http::response(['response' => [[
                'league' => ['id' => 71, 'name' => 'Serie A', 'type' => 'League', 'logo' => 'logo.png'],
                'country' => ['name' => 'Brazil', 'code' => 'BR', 'flag' => 'flag.png'],
                'seasons' => [['year' => 2024, 'start' => '2024-01-01', 'end' => '2024-12-31', 'current' => true]],
            ]]]),
            'v3.football.api-sports.io/teams*' => Http::response(['response' => [[
                'team' => ['id' => 100, 'name' => 'Botafogo', 'country' => 'Brazil', 'founded' => 1904, 'logo' => 'team.png'],
                'venue' => ['name' => 'Nilton Santos'],
            ], [
                'team' => ['id' => 101, 'name' => 'Santos', 'country' => 'Brazil', 'founded' => 1912, 'logo' => 'santos.png'],
                'venue' => ['name' => 'Vila Belmiro'],
            ]]]),
            'v3.football.api-sports.io/standings*' => Http::response(['response' => [[
                'league' => ['id' => 71, 'season' => 2024, 'standings' => [[[
                    'rank' => 1,
                    'team' => ['id' => 100, 'name' => 'Botafogo', 'logo' => 'team.png'],
                    'points' => 70,
                    'goalsDiff' => 30,
                    'all' => ['played' => 38, 'win' => 22, 'draw' => 4, 'lose' => 12, 'goals' => ['for' => 60, 'against' => 30]],
                ]]]],
            ]]]),
            'v3.football.api-sports.io/fixtures/statistics*' => Http::response(['response' => [[
                'team' => ['id' => 100, 'name' => 'Botafogo'],
                'statistics' => [['type' => 'Total Shots', 'value' => 12], ['type' => 'Shots on Goal', 'value' => 6], ['type' => 'Ball Possession', 'value' => '55%']],
            ]]]),
            'v3.football.api-sports.io/fixtures*' => Http::response(['response' => [[
                'fixture' => ['id' => 7001, 'date' => '2024-06-01T16:00:00-03:00', 'timezone' => 'America/Sao_Paulo', 'venue' => ['name' => 'Nilton Santos', 'city' => 'Rio'], 'status' => ['short' => 'FT', 'elapsed' => 90]],
                'league' => ['id' => 71, 'name' => 'Serie A', 'country' => 'Brazil', 'season' => 2024, 'logo' => 'logo.png'],
                'teams' => ['home' => ['id' => 100, 'name' => 'Botafogo', 'logo' => 'team.png'], 'away' => ['id' => 101, 'name' => 'Santos', 'logo' => 'santos.png']],
                'goals' => ['home' => 3, 'away' => 0],
            ]]]),
        ]);

        $this->assertTrue(app(ProviderRegistry::class)->make($provider)->testConnection()->success);
        $this->runJob($provider, 'sync_leagues');
        $this->runJob($provider, 'sync_teams', ['league_id' => 71, 'season' => 2024]);
        $this->runJob($provider, 'sync_matches', ['league_id' => 71, 'season' => 2024, 'timezone' => 'America/Sao_Paulo']);
        $this->runJob($provider, 'sync_standings', ['league_id' => 71, 'season' => 2024]);
        $this->runJob($provider, 'sync_match_statistics', ['fixture_id' => 7001]);

        $this->assertDatabaseHas('leagues', ['external_provider_id' => $provider->id, 'external_id' => '71']);
        $this->assertDatabaseHas('teams', ['external_provider_id' => $provider->id, 'external_id' => '100']);
        $this->assertDatabaseHas('matches', ['external_provider_id' => $provider->id, 'external_id' => '7001', 'status' => 'finished']);
        $this->assertSame(1, Standing::count());
        $this->assertDatabaseHas('match_statistics', ['shots' => 12, 'shots_on_target' => 6]);
        $this->assertGreaterThanOrEqual(5, ApiRequestLog::where('api_provider_id', $provider->id)->count());
    }

    public function test_rate_limit_missing_config_and_public_filters(): void
    {
        $this->seed();
        $provider = $this->activate('api-football', requestsPerMinute: 1);
        ApiRequestLog::create(['api_provider_id' => $provider->id, 'api_provider_key_id' => $provider->keys()->first()->id, 'method' => 'GET', 'endpoint' => '/status', 'success' => true, 'requested_at' => now()]);

        $job = $this->runJob($provider, 'sync_matches', ['league_id' => 71, 'season' => 2024]);
        $this->assertSame('failed', $job->status);
        $this->assertSame('Provider rate limit exceeded.', $job->last_error);

        $provider->keys()->first()->update(['requests_per_minute' => 100]);
        $missing = $this->runJob($provider, 'sync_matches', []);
        $this->assertSame('Missing required config: league_id.', $missing->last_error);

        $league = League::first();
        $sport = Sport::where('slug', 'football')->first();
        $season = Season::create(['league_id' => $league->id, 'year' => 2024, 'name' => '2024']);
        $home = Team::create(['sport_id' => $sport->id, 'name' => 'Home', 'slug' => 'home']);
        $away = Team::create(['sport_id' => $sport->id, 'name' => 'Away', 'slug' => 'away']);
        $match = SportsMatch::create([
            'sport_id' => $sport->id,
            'league_id' => $league->id,
            'season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'external_provider_id' => $provider->id,
            'external_id' => 'filter-1',
            'starts_at' => '2024-06-01 12:00:00',
            'status' => 'finished',
        ]);
        $this->withHeaders($this->apiHeaders())->getJson('/api/v1/matches?league_id='.$league->id.'&status=finished&date_from=2024-01-01&date_to=2024-12-31')
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id])
            ->assertJsonMissingPath('data.0.provider')
            ->assertJsonMissingPath('data.0.external_provider_id')
            ->assertJsonMissingPath('data.0.external_id');
    }

    public function test_dashboard_reflects_collected_data(): void
    {
        $this->seed();
        $admin = \App\Models\User::where('is_admin', true)->first();

        $this->actingAs($admin)->getJson('/admin/api/dashboard')
            ->assertOk()
            ->assertJsonStructure(['cards' => ['active_providers', 'requests_today', 'pending_sync_jobs'], 'provider_progress', 'coverage']);
    }

    private function activate(string $slug, int $requestsPerMinute = 100): ApiProvider
    {
        $provider = ApiProvider::where('slug', $slug)->firstOrFail();
        $provider->update(['is_active' => true, 'status' => 'active', 'rate_limit_per_minute' => $requestsPerMinute, 'base_url' => $provider->base_url]);
        ApiProviderKey::create(['api_provider_id' => $provider->id, 'name' => 'Test', 'encrypted_key' => 'test-key', 'key_hint' => '****-key', 'is_active' => true, 'requests_per_minute' => $requestsPerMinute, 'requests_per_day' => 1000]);

        return $provider->fresh();
    }

    private function runJob(ApiProvider $provider, string $type, array $config = []): SyncJob
    {
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => $type, 'status' => 'pending', 'config' => $config]);
        return app(SportsDataSyncService::class)->run($job);
    }
}
