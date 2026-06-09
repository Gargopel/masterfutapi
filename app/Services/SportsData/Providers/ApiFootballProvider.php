<?php

namespace App\Services\SportsData\Providers;

use App\Models\League;
use App\Models\MatchStatistic;
use App\Models\Season;
use App\Models\Standing;
use App\Models\SyncJob;
use App\Models\Team;
use App\Services\SportsData\SportsDataNormalizer;
use App\Support\SportsData\ProviderTestResult;
use App\Support\SportsData\SyncResult;

class ApiFootballProvider extends AbstractSportsProvider
{
    public function testConnection(): ProviderTestResult
    {
        try {
            $data = $this->request('GET', '/status', [], $this->authHeaders());
            return new ProviderTestResult(true, 'API-Football connection ok.', ['response' => data_get($data, 'response')]);
        } catch (\Throwable $e) {
            return new ProviderTestResult(false, $this->friendlyConnectionMessage($e));
        }
    }

    public function syncLeagues(SyncJob $job): SyncResult
    {
        return $this->withJobFailure($job, function () use ($job) {
            $this->begin($job);
            $normalizer = app(SportsDataNormalizer::class);
            $items = $this->pagedResponses($job, '/leagues', []);
            $this->updateTotal($job, count($items));

            foreach ($items as $item) {
                $country = $normalizer->country(data_get($item, 'country.name'), data_get($item, 'country.code'), data_get($item, 'country.flag'));
                $league = $normalizer->league($this->provider, [
                    'external_id' => (string) data_get($item, 'league.id'),
                    'name' => data_get($item, 'league.name'),
                    'country' => $country,
                    'type' => data_get($item, 'league.type'),
                    'logo_url' => data_get($item, 'league.logo'),
                    'raw_payload' => $item,
                ]);

                foreach (data_get($item, 'seasons', []) as $season) {
                    $normalizer->season($this->provider, $league, (int) data_get($season, 'year'), [
                        'name' => (string) data_get($season, 'year'),
                        'starts_at' => data_get($season, 'start'),
                        'ends_at' => data_get($season, 'end'),
                        'is_current' => (bool) data_get($season, 'current', false),
                        'raw_payload' => $season,
                    ]);
                }

                $this->incrementProgress($job, $this->actionFor($league), 'league', (string) data_get($item, 'league.id'), $league->id, $item);
            }

            return $this->complete($job->fresh(), ['imported' => count($items)]);
        });
    }

    public function syncTeams(SyncJob $job): SyncResult
    {
        return $this->withJobFailure($job, function () use ($job) {
            $params = $this->leagueSeasonParams($job);
            if (is_string($params)) {
                return $this->fail($job, $params);
            }

            $this->begin($job);
            $normalizer = app(SportsDataNormalizer::class);
            $items = $this->pagedResponses($job, '/teams', $params);
            $this->updateTotal($job, count($items));

            foreach ($items as $item) {
                $country = $normalizer->country(data_get($item, 'team.country'));
                $team = $normalizer->team($this->provider, [
                    'external_id' => (string) data_get($item, 'team.id'),
                    'name' => data_get($item, 'team.name'),
                    'country' => $country,
                    'logo_url' => data_get($item, 'team.logo'),
                    'founded' => data_get($item, 'team.founded'),
                    'venue_name' => data_get($item, 'venue.name'),
                    'raw_payload' => $item,
                ]);

                $this->incrementProgress($job, $this->actionFor($team), 'team', (string) data_get($item, 'team.id'), $team->id, $item);
            }

            return $this->complete($job->fresh(), ['imported' => count($items)] + $params);
        });
    }

    public function syncMatches(SyncJob $job): SyncResult
    {
        return $this->withJobFailure($job, function () use ($job) {
            $params = $this->leagueSeasonParams($job);
            if (is_string($params)) {
                return $this->fail($job, $params);
            }
            $params['timezone'] = $this->configValue($job, 'timezone') ?: 'America/Sao_Paulo';

            $this->begin($job);
            $normalizer = app(SportsDataNormalizer::class);
            $items = $this->pagedResponses($job, '/fixtures', $params);
            $this->updateTotal($job, count($items));

            foreach ($items as $item) {
                $league = $this->leagueFromFixture($normalizer, $item);
                $season = $normalizer->season($this->provider, $league, (int) data_get($item, 'league.season'), ['raw_payload' => data_get($item, 'league', [])]);
                $home = $this->teamFromFixture($normalizer, $item, 'home');
                $away = $this->teamFromFixture($normalizer, $item, 'away');

                $match = $normalizer->match($this->provider, [
                    'sport' => $normalizer->football(),
                    'league' => $league,
                    'season' => $season,
                    'home_team' => $home,
                    'away_team' => $away,
                    'external_id' => (string) data_get($item, 'fixture.id'),
                    'starts_at' => data_get($item, 'fixture.date'),
                    'status' => $normalizer->mapStatus(data_get($item, 'fixture.status.short')),
                    'minute' => data_get($item, 'fixture.status.elapsed'),
                    'home_score' => data_get($item, 'goals.home'),
                    'away_score' => data_get($item, 'goals.away'),
                    'venue' => trim((string) data_get($item, 'fixture.venue.name').' '.(string) data_get($item, 'fixture.venue.city')),
                    'timezone' => data_get($item, 'fixture.timezone'),
                    'raw_payload' => $item,
                ]);

                $this->incrementProgress($job, $this->actionFor($match), 'match', (string) data_get($item, 'fixture.id'), $match->id, $item);
            }

            return $this->complete($job->fresh(), ['imported' => count($items)] + $params);
        });
    }

    public function syncStandings(SyncJob $job): SyncResult
    {
        return $this->withJobFailure($job, function () use ($job) {
            $params = $this->leagueSeasonParams($job);
            if (is_string($params)) {
                return $this->fail($job, $params);
            }

            $this->begin($job);
            $normalizer = app(SportsDataNormalizer::class);
            $payload = $this->request('GET', '/standings', $params, $this->authHeaders());
            $groups = data_get($payload, 'response.0.league.standings', []);
            $rows = collect($groups)->flatten(1)->values();
            $this->updateTotal($job, $rows->count());
            $league = $this->leagueByExternal((string) $params['league']);
            $season = $league ? $normalizer->season($this->provider, $league, (int) $params['season']) : null;

            foreach ($rows as $row) {
                if (! $league || ! $season) {
                    $this->recordItemFailure($job, 'League not found for standings.', 'standing', (string) data_get($row, 'team.id'), $row);
                    continue;
                }

                $team = $this->teamByExternal((string) data_get($row, 'team.id')) ?: $normalizer->team($this->provider, [
                    'external_id' => (string) data_get($row, 'team.id'),
                    'name' => data_get($row, 'team.name'),
                    'logo_url' => data_get($row, 'team.logo'),
                    'raw_payload' => data_get($row, 'team', []),
                ]);

                $standing = Standing::updateOrCreate([
                    'league_id' => $league->id,
                    'season_id' => $season->id,
                    'team_id' => $team->id,
                ], [
                    'position' => data_get($row, 'rank'),
                    'points' => data_get($row, 'points'),
                    'played' => data_get($row, 'all.played'),
                    'won' => data_get($row, 'all.win'),
                    'draw' => data_get($row, 'all.draw'),
                    'lost' => data_get($row, 'all.lose'),
                    'goals_for' => data_get($row, 'all.goals.for'),
                    'goals_against' => data_get($row, 'all.goals.against'),
                    'goal_difference' => data_get($row, 'goalsDiff'),
                    'raw_payload' => $row,
                ]);

                $normalizer->mapping($this->provider, 'standing', $standing->id, $league->id.':'.$season->id.':'.$team->id, $team->name, $row);
                $this->incrementProgress($job, $this->actionFor($standing), 'standing', (string) data_get($row, 'team.id'), $standing->id, $row);
            }

            return $this->complete($job->fresh(), ['imported' => $rows->count()] + $params);
        });
    }

    public function syncMatchStatistics(SyncJob $job): SyncResult
    {
        return $this->withJobFailure($job, function () use ($job) {
            $fixtureId = $this->configValue($job, 'fixture_id');
            if (! $fixtureId) {
                return $this->fail($job, 'Missing required config: fixture_id.');
            }

            $this->begin($job);
            $normalizer = app(SportsDataNormalizer::class);
            $payload = $this->request('GET', '/fixtures/statistics', ['fixture' => $fixtureId], $this->authHeaders());
            $items = $payload['response'] ?? [];
            $this->updateTotal($job, count($items));
            $match = \App\Models\SportsMatch::where('external_provider_id', $this->provider->id)->where('external_id', (string) $fixtureId)->first();

            foreach ($items as $item) {
                if (! $match) {
                    $this->recordItemFailure($job, 'Match not found for fixture statistics.', 'match_statistic', (string) $fixtureId, $item);
                    continue;
                }

                $team = $this->teamByExternal((string) data_get($item, 'team.id'));
                $stats = collect(data_get($item, 'statistics', []))->mapWithKeys(fn ($stat) => [data_get($stat, 'type') => data_get($stat, 'value')]);
                $model = MatchStatistic::updateOrCreate([
                    'match_id' => $match->id,
                    'team_id' => $team?->id,
                ], [
                    'shots' => $this->intStat($stats->get('Total Shots')),
                    'shots_on_target' => $this->intStat($stats->get('Shots on Goal')),
                    'corners' => $this->intStat($stats->get('Corner Kicks')),
                    'yellow_cards' => $this->intStat($stats->get('Yellow Cards')),
                    'red_cards' => $this->intStat($stats->get('Red Cards')),
                    'possession' => $this->percentStat($stats->get('Ball Possession')),
                    'expected_goals' => $this->floatStat($stats->get('expected_goals') ?? $stats->get('Expected Goals')),
                    'raw_payload' => $item,
                ]);

                $this->incrementProgress($job, $this->actionFor($model), 'match_statistic', (string) $fixtureId, $model->id, $item);
            }

            return $this->complete($job->fresh(), ['imported' => count($items), 'fixture_id' => $fixtureId]);
        });
    }

    private function leagueSeasonParams(SyncJob $job): array|string
    {
        $leagueId = $this->configValue($job, 'league_id');
        $season = $this->configValue($job, 'season');

        if (! $leagueId) {
            return 'Missing required config: league_id.';
        }

        if (! $season) {
            return 'Missing required config: season.';
        }

        return ['league' => $leagueId, 'season' => $season];
    }

    private function pagedResponses(SyncJob $job, string $endpoint, array $params): array
    {
        $page = (int) ($this->configValue($job, 'page') ?: 1);
        $maxPages = (int) ($this->configValue($job, 'max_pages') ?: 10);
        $items = [];

        do {
            if (app(\App\Services\SportsData\SyncProgressService::class)->shouldCancel($job)) {
                throw new \RuntimeException('Sync job cancellation requested.');
            }
            $payload = $this->request('GET', $endpoint, $params + ['page' => $page], $this->authHeaders());
            $items = array_merge($items, $payload['response'] ?? []);
            $current = (int) data_get($payload, 'paging.current', $page);
            $total = (int) data_get($payload, 'paging.total', $current);
            $page++;
        } while ($current < $total && ($page - 1) < $maxPages);

        $job->update(['result' => array_merge($job->result ?? [], ['last_page_collected' => $page - 1, 'max_pages' => $maxPages])]);

        return $items;
    }

    private function leagueFromFixture(SportsDataNormalizer $normalizer, array $item): League
    {
        $externalId = (string) data_get($item, 'league.id');
        return $this->leagueByExternal($externalId) ?: $normalizer->league($this->provider, [
            'external_id' => $externalId,
            'name' => data_get($item, 'league.name'),
            'country' => $normalizer->country(data_get($item, 'league.country')),
            'type' => null,
            'logo_url' => data_get($item, 'league.logo'),
            'raw_payload' => data_get($item, 'league', []),
        ]);
    }

    private function teamFromFixture(SportsDataNormalizer $normalizer, array $item, string $side): Team
    {
        $team = data_get($item, "teams.{$side}", []);
        return $this->teamByExternal((string) data_get($team, 'id')) ?: $normalizer->team($this->provider, [
            'external_id' => (string) data_get($team, 'id'),
            'name' => data_get($team, 'name'),
            'logo_url' => data_get($team, 'logo'),
            'raw_payload' => $team,
        ]);
    }

    private function leagueByExternal(string $externalId): ?League
    {
        return League::where('external_provider_id', $this->provider->id)->where('external_id', $externalId)->first();
    }

    private function teamByExternal(string $externalId): ?Team
    {
        return Team::where('external_provider_id', $this->provider->id)->where('external_id', $externalId)->first();
    }

    private function intStat(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function floatStat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function percentStat(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace('%', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function authHeaders(): array
    {
        $key = app(\App\Services\SportsData\ProviderKeyResolver::class)->resolve($this->provider);
        return ['x-apisports-key' => $key?->encrypted_key ?? ''];
    }
}
