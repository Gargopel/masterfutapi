<?php

namespace App\Console\Commands;

use App\Models\SyncJob;
use App\Services\SportsData\SystemAlertService;
use Illuminate\Console\Command;

class RecoverStaleSyncJobsCommand extends Command
{
    protected $signature = 'futia:sync:recover-stale {--minutes=60} {--requeue}';
    protected $description = 'Recover sync jobs stuck in running state.';

    public function handle(SystemAlertService $alerts): int
    {
        $minutes = (int) $this->option('minutes');
        $status = $this->option('requeue') ? 'pending' : 'failed';
        $count = 0;

        SyncJob::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes($minutes))
            ->get()
            ->each(function (SyncJob $job) use ($alerts, $status, &$count) {
                $job->update([
                    'status' => $status,
                    'finished_at' => $status === 'failed' ? now() : null,
                    'last_error' => $status === 'failed' ? 'Recovered stale running job.' : null,
                    'result' => array_merge($job->result ?? [], ['recovered_stale' => true]),
                ]);
                $alerts->createOnce('stale_sync_job', 'warning', "Stale sync job #{$job->id}", 'A running sync job was recovered.', SyncJob::class, $job->id);
                $count++;
            });

        $this->info("Recovered {$count} stale sync job(s).");
        return self::SUCCESS;
    }
}
