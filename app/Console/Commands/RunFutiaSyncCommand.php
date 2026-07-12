<?php

namespace App\Console\Commands;

use App\Models\SyncJob;
use App\Jobs\RunSportsDataSyncJob;
use App\Services\SportsData\SportsDataSyncService;
use App\Services\SportsData\SyncScheduleService;
use Illuminate\Console\Command;

class RunFutiaSyncCommand extends Command
{
    protected $signature = 'futia:sync:run {--limit=5} {--sync}';
    protected $description = 'Create due scheduled jobs and dispatch pending FutIA sports data sync jobs.';

    public function handle(SportsDataSyncService $service, SyncScheduleService $schedules): int
    {
        $created = $schedules->createDueJobs();
        if ($created > 0) {
            $this->info("Created {$created} scheduled sync job(s).");
        }

        SyncJob::where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->oldest()
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (SyncJob $job) use ($service) {
                try {
                    if ($this->option('sync')) {
                        $service->run($job);
                        $this->info("Sync job {$job->id} completed.");
                    } else {
                        RunSportsDataSyncJob::dispatch($job->id);
                        $this->info("Sync job {$job->id} dispatched.");
                    }
                } catch (\Throwable $e) {
                    $this->error("Sync job {$job->id} failed: {$e->getMessage()}");
                }
            });

        return self::SUCCESS;
    }
}
