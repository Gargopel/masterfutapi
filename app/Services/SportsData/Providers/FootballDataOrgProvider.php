<?php

namespace App\Services\SportsData\Providers;

use App\Models\League;
use App\Models\SyncJob;
use App\Services\SportsData\SportsDataNormalizer;
use App\Support\SportsData\ProviderTestResult;
use App\Support\SportsData\SyncResult;

class FootballDataOrgProvider extends AbstractSportsProvider
{
    public function testConnection(): ProviderTestResult
    {
        try {
            $data = $this->request('GET', '/competitions', [], $this->authHeaders());
            return new ProviderTestResult(true, 'Football-Data.org connection ok.', ['count' => count($data['competitions'] ?? [])]);
        } catch (\Throwable $e) {
            return new ProviderTestResult(false, $this->friendlyConnectionMessage($e));
        }
    }

    public function syncLeagues(SyncJob $job): SyncResult
    {
        return $this->withJobFailure($job, function () use ($job) {
            $this->begin($job);
            $normalizer = app(SportsDataNormalizer::class);
            $payload = $this->request('GET', '/competitions', [], $this->authHeaders());
            $items = $payload['competitions'] ?? [];
            $this->updateTotal($job, count($items));

            foreach ($items as $item) {
                $country = $normalizer->country(data_get($item, 'area.name'), data_get($item, 'area.code'));
                $league = $normalizer->league($this->provider, [
                    'external_id' => (string) data_get($item, 'id', data_get($item, 'code')),
                    'name' => data_get($item, 'name'),
                    'country' => $country,
                    'type' => data_get($item, 'type'),
                    'logo_url' => data_get($item, 'emblem'),
                    'raw_payload' => $item,
                ]);

                if (data_get($item, 'currentSeason.startDate')) {
                    $year = (int) substr((string) data_get($item, 'currentSeason.startDate'), 0, 4);
                    $normalizer->season($this->provider, $league, $year, [
                        'name' => (string) $year,
                        'starts_at' => data_get($item, 'currentSeason.startDate'),
                        'ends_at' => data_get($item, 'currentSeason.endDate'),
                        'is_current' => true,
                        'external_id' => (string) data_get($item, 'currentSeason.id', $year),
                        'raw_payload' => data_get($item, 'currentSeason', []),
                    ]);
                }

                $this->incrementProgress($job, $this->actionFor($league), 'league', (string) data_get($item, 'id'), $league->id, $item);
            }

            return $this->complete($job->fresh(), ['imported' => count($items)]);
        });
    }

    public function syncMatches(SyncJob $job): SyncResult
    {
        return $this->withJobFailure($job, function () use ($job) {
            $competitionCode = $this->configValue($job, 'competition_code');
            $seasonYear = (int) $this->configValue($job, 'season');

            if (! $competitionCode) {
                return $this->fail($job, 'Missing required config: competition_code.');
            }

            $this->begin($job);
            $normalizer = app(SportsDataNormalizer::class);
            $sport = $normalizer->football();
            $payload = $this->request('GET', "/competitions/{$competitionCode}/matches", array_filter(['season' => $seasonYear ?: null]), $this->authHeaders());
            $items = $payload['matches'] ?? [];
            $this->updateTotal($job, count($items));

            $competition = data_get($payload, 'competition', []);
            $league = $this->resolveLeague($normalizer, $competition, (string) $competitionCode);

            foreach ($items as $item) {
                $season = $normalizer->season($this->provider, $league, (int) ($seasonYear ?: data_get($item, 'season.startDate', now()->year)), [
                    'name' => (string) ($seasonYear ?: data_get($item, 'season.startDate', now()->year)),
                    'starts_at' => data_get($item, 'season.startDate'),
                    'ends_at' => data_get($item, 'season.endDate'),
                    'is_current' => data_get($item, 'season.currentMatchday') !== null,
                    'raw_payload' => data_get($item, 'season', []),
                ]);

                $home = $this->teamFromMatch($normalizer, $item, 'homeTeam', $sport);
                $away = $this->teamFromMatch($normalizer, $item, 'awayTeam', $sport);

                $match = $normalizer->match($this->provider, [
                    'sport' => $sport,
                    'league' => $league,
                    'season' => $season,
                    'home_team' => $home,
                    'away_team' => $away,
                    'external_id' => (string) data_get($item, 'id'),
                    'starts_at' => data_get($item, 'utcDate'),
                    'status' => $normalizer->mapStatus(data_get($item, 'status')),
                    'home_score' => data_get($item, 'score.fullTime.home'),
                    'away_score' => data_get($item, 'score.fullTime.away'),
                    'timezone' => 'UTC',
                    'raw_payload' => $item,
                ]);

                $this->incrementProgress($job, $this->actionFor($match), 'match', (string) data_get($item, 'id'), $match->id, $item);
            }

            return $this->complete($job->fresh(), ['imported' => count($items), 'competition_code' => $competitionCode, 'season' => $seasonYear ?: null]);
        });
    }

    private function resolveLeague(SportsDataNormalizer $normalizer, array $competition, string $competitionCode): League
    {
        $externalId = (string) data_get($competition, 'id', $competitionCode);
        $league = League::where('external_provider_id', $this->provider->id)->where('external_id', $externalId)->first();

        return $league ?: $normalizer->league($this->provider, [
            'external_id' => $externalId,
            'name' => data_get($competition, 'name', $competitionCode),
            'country' => null,
            'type' => data_get($competition, 'type'),
            'raw_payload' => $competition,
        ]);
    }

    private function teamFromMatch(SportsDataNormalizer $normalizer, array $item, string $side, $sport)
    {
        return $normalizer->team($this->provider, [
            'sport' => $sport,
            'external_id' => (string) data_get($item, "{$side}.id", data_get($item, "{$side}.name")),
            'name' => data_get($item, "{$side}.name", 'Unknown team'),
            'short_name' => data_get($item, "{$side}.shortName"),
            'logo_url' => data_get($item, "{$side}.crest"),
            'raw_payload' => data_get($item, $side, []),
        ]);
    }

    private function authHeaders(): array
    {
        $key = app(\App\Services\SportsData\ProviderKeyResolver::class)->resolve($this->provider);
        return ['X-Auth-Token' => $key?->encrypted_key ?? ''];
    }
}
