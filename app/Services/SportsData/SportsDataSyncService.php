<?php

namespace App\Services\SportsData;

use App\Models\SyncJob;
use App\Services\SportsData\SystemAlertService;

class SportsDataSyncService
{
    public function __construct(private ProviderRegistry $registry, private SystemAlertService $alerts, private FullProviderSyncService $fullSync) {}

    public function run(SyncJob $job): SyncJob
    {
        $job->load('provider');

        if (! $job->provider || ! $job->provider->is_active) {
            $job->update(['status' => 'failed', 'last_error' => 'Provider inactive or missing.', 'finished_at' => now()]);
            $this->alerts->syncFailed($job->fresh());
            return $job->fresh();
        }

        $job->update(['status' => 'running', 'started_at' => now(), 'last_error' => null]);
        $adapter = $this->registry->make($job->provider);

        $result = match ($job->type) {
            FullProviderSyncService::TYPE => new \App\Support\SportsData\SyncResult(true, 'Full sync planned.', $this->fullSync->plan($job)->result ?? []),
            'sync_leagues' => $adapter->syncLeagues($job),
            'sync_teams' => $adapter->syncTeams($job),
            'sync_matches' => $adapter->syncMatches($job),
            'sync_standings' => $adapter->syncStandings($job),
            'sync_match_statistics' => $adapter->syncMatchStatistics($job),
            default => new \App\Support\SportsData\SyncResult(false, 'Unsupported sync type.'),
        };

        if (! $result->success && $job->fresh()->status !== 'cancelled') {
            $job->update(['status' => 'failed', 'last_error' => $result->message, 'finished_at' => now()]);
            $this->alerts->syncFailed($job->fresh());
        }

        return $job->fresh();
    }
}
