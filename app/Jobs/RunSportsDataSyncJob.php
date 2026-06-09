<?php

namespace App\Jobs;

use App\Models\SyncJob;
use App\Services\SportsData\SportsDataSyncService;
use App\Services\SportsData\SyncLockService;
use App\Services\SportsData\SystemAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunSportsDataSyncJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;
    public int $tries = 1;

    public function __construct(public int $syncJobId) {}

    public function handle(SportsDataSyncService $service, SyncLockService $locks, SystemAlertService $alerts): void
    {
        $job = SyncJob::findOrFail($this->syncJobId);
        if (! in_array($job->status, ['pending', 'running'], true)) {
            return;
        }

        $lock = $locks->acquire($job);
        if (! $lock) {
            $job->update([
                'status' => 'skipped',
                'finished_at' => now(),
                'result' => ['skipped_reason' => 'Duplicate sync lock is already active.'],
            ]);
            return;
        }

        try {
            $service->run($job);
        } catch (Throwable $e) {
            $job->update(['status' => 'failed', 'last_error' => $e->getMessage(), 'finished_at' => now()]);
            $alerts->syncFailed($job->fresh());
        } finally {
            optional($lock)->release();
        }
    }
}
