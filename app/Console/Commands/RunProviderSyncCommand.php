<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\SyncJob;
use App\Jobs\RunSportsDataSyncJob;
use App\Services\SportsData\SportsDataSyncService;
use Illuminate\Console\Command;

class RunProviderSyncCommand extends Command
{
    protected $signature = 'futia:sync:provider {provider} {--type=sync_leagues} {--sync}';
    protected $description = 'Create and dispatch a sync job for one provider slug.';

    public function handle(SportsDataSyncService $service): int
    {
        $provider = ApiProvider::where('slug', $this->argument('provider'))->firstOrFail();
        $job = SyncJob::create(['api_provider_id' => $provider->id, 'type' => $this->option('type'), 'status' => 'pending', 'source' => 'manual']);
        if ($this->option('sync')) {
            $service->run($job);
            $this->info("Sync job {$job->id} finished with status {$job->fresh()->status}.");
        } else {
            RunSportsDataSyncJob::dispatch($job->id);
            $this->info("Sync job {$job->id} dispatched.");
        }
        return self::SUCCESS;
    }
}
