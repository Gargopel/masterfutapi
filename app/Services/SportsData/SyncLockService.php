<?php

namespace App\Services\SportsData;

use App\Models\SyncJob;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class SyncLockService
{
    public function lockKeyFor(SyncJob|array $scope): string
    {
        $data = $scope instanceof SyncJob ? $scope->toArray() : $scope;
        $config = data_get($data, 'config') ?? [];
        ksort($config);

        return sprintf(
            'sync:provider:%s:type:%s:sport:%s:league:%s:season:%s:hash:%s',
            data_get($data, 'api_provider_id', 'none'),
            data_get($data, 'type', 'none'),
            data_get($data, 'sport_id', 'none') ?: 'none',
            data_get($data, 'league_id', 'none') ?: 'none',
            data_get($data, 'season_id', 'none') ?: 'none',
            substr(sha1(json_encode($config)), 0, 12),
        );
    }

    public function acquire(SyncJob $job, int $seconds = 1800): ?Lock
    {
        $lock = Cache::lock($this->lockKeyFor($job), $seconds);

        return $lock->get() ? $lock : null;
    }

    public function hasDuplicate(array $scope, ?int $ignoreJobId = null): bool
    {
        return SyncJob::query()
            ->whereIn('status', ['pending', 'running'])
            ->when($ignoreJobId, fn ($query) => $query->where('id', '!=', $ignoreJobId))
            ->where('api_provider_id', data_get($scope, 'api_provider_id'))
            ->where('type', data_get($scope, 'type'))
            ->where('sport_id', data_get($scope, 'sport_id'))
            ->where('league_id', data_get($scope, 'league_id'))
            ->where('season_id', data_get($scope, 'season_id'))
            ->get()
            ->contains(fn (SyncJob $job) => $this->lockKeyFor($job) === $this->lockKeyFor($scope));
    }
}
