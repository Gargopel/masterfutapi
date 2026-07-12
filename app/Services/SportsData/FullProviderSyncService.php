<?php

namespace App\Services\SportsData;

use App\Jobs\RunSportsDataSyncJob;
use App\Models\ApiProvider;
use App\Models\League;
use App\Models\ProviderMapping;
use App\Models\Season;
use App\Models\SyncJob;

class FullProviderSyncService
{
    public const TYPE = 'full_provider_sync';
    public const DEFAULT_INTERVAL_SECONDS = 60;

    public function create(ApiProvider $provider, array $config = []): SyncJob
    {
        $interval = (int) ($config['request_interval_seconds'] ?? self::DEFAULT_INTERVAL_SECONDS);
        $interval = max(1, $interval);

        $job = SyncJob::create([
            'api_provider_id' => $provider->id,
            'type' => self::TYPE,
            'source' => 'manual',
            'status' => 'pending',
            'progress_percent' => 0,
            'config' => [
                'request_interval_seconds' => $interval,
                'seasons' => $config['seasons'] ?? [now()->year],
                'max_children' => $config['max_children'] ?? null,
            ],
        ]);

        RunSportsDataSyncJob::dispatch($job->id);

        return $job;
    }

    public function plan(SyncJob $parent): SyncJob
    {
        $parent->load('provider');
        $interval = max(1, (int) data_get($parent->config, 'request_interval_seconds', self::DEFAULT_INTERVAL_SECONDS));
        $children = $this->childScopes($parent);
        $maxChildren = data_get($parent->config, 'max_children');
        if ($maxChildren) {
            $children = array_slice($children, 0, (int) $maxChildren);
        }

        $parent->update([
            'status' => 'running',
            'started_at' => now(),
            'total_items' => count($children),
            'processed_items' => 0,
            'progress_percent' => count($children) === 0 ? 100 : 0,
            'last_error' => null,
        ]);

        foreach ($children as $index => $scope) {
            $child = SyncJob::create($scope + [
                'parent_sync_job_id' => $parent->id,
                'api_provider_id' => $parent->api_provider_id,
                'source' => 'full_provider_sync',
                'status' => 'pending',
                'progress_percent' => 0,
            ]);

            RunSportsDataSyncJob::dispatch($child->id)->delay(now()->addSeconds($index * $interval));
        }

        $parent->update([
            'status' => 'completed',
            'processed_items' => count($children),
            'progress_percent' => 100,
            'finished_at' => now(),
            'result' => [
                'message' => 'Full provider sync jobs scheduled.',
                'children_scheduled' => count($children),
                'request_interval_seconds' => $interval,
            ],
        ]);

        return $parent->fresh();
    }

    private function childScopes(SyncJob $parent): array
    {
        $provider = $parent->provider;
        if (! $provider) {
            return [];
        }

        return match ($provider->slug) {
            'football-data' => $this->footballDataScopes($parent),
            'api-football' => $this->apiFootballScopes($parent),
            default => [
                ['type' => 'sync_leagues', 'config' => []],
            ],
        };
    }

    private function footballDataScopes(SyncJob $parent): array
    {
        if (data_get($parent->config, 'phase') !== 'expand_after_leagues') {
            return [
                ['type' => 'sync_leagues', 'config' => []],
                [
                    'type' => self::TYPE,
                    'config' => array_merge($parent->config ?? [], ['phase' => 'expand_after_leagues']),
                ],
            ];
        }

        $scopes = [];
        $seasons = collect(data_get($parent->config, 'seasons', [now()->year]))->filter()->values();

        League::query()
            ->where('external_provider_id', $parent->api_provider_id)
            ->orderBy('name')
            ->get()
            ->each(function (League $league) use (&$scopes, $seasons) {
                $mapping = ProviderMapping::where('provider_id', $league->external_provider_id)
                    ->where('entity_type', 'league')
                    ->where('entity_id', $league->id)
                    ->first();
                $competitionCode = data_get($mapping?->raw_payload ?? [], 'code') ?: $league->external_id;
                foreach ($seasons as $season) {
                    $scopes[] = [
                        'type' => 'sync_matches',
                        'league_id' => $league->id,
                        'config' => [
                            'competition_code' => (string) $competitionCode,
                            'season' => (int) $season,
                        ],
                    ];
                }
            });

        return $scopes;
    }

    private function apiFootballScopes(SyncJob $parent): array
    {
        $scopes = [['type' => 'sync_leagues', 'config' => []]];

        Season::query()
            ->whereHas('league', fn ($query) => $query->where('external_provider_id', $parent->api_provider_id))
            ->with('league:id,external_id')
            ->orderByDesc('year')
            ->get()
            ->each(function (Season $season) use (&$scopes) {
                if (! $season->league?->external_id) {
                    return;
                }

                $base = ['league_id' => (int) $season->league->external_id, 'season' => (int) $season->year];
                $scopes[] = ['type' => 'sync_teams', 'league_id' => $season->league_id, 'season_id' => $season->id, 'config' => $base];
                $scopes[] = ['type' => 'sync_matches', 'league_id' => $season->league_id, 'season_id' => $season->id, 'config' => $base + ['timezone' => 'America/Sao_Paulo']];
                $scopes[] = ['type' => 'sync_standings', 'league_id' => $season->league_id, 'season_id' => $season->id, 'config' => $base];
            });

        return $scopes;
    }
}
