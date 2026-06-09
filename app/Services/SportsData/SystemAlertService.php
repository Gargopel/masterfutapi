<?php

namespace App\Services\SportsData;

use App\Models\ApiProvider;
use App\Models\SystemAlert;
use App\Models\SyncJob;

class SystemAlertService
{
    public function syncFailed(SyncJob $job): SystemAlert
    {
        return $this->createOnce('sync_job_failed', 'error', "Sync job #{$job->id} failed", $job->last_error ?: 'Sync job failed.', SyncJob::class, $job->id);
    }

    public function providerHealthAlerts(): void
    {
        if (ApiProvider::where('is_active', true)->doesntExist()) {
            $this->createOnce('no_active_provider', 'warning', 'No active provider', 'There are no active providers configured.');
        }

        ApiProvider::where('is_active', true)->withCount(['keys as active_keys_count' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->each(function (ApiProvider $provider) {
                if ($provider->active_keys_count < 1) {
                    $this->createOnce('provider_without_key', 'warning', "{$provider->name} has no active API key", 'Active providers need at least one active key.', ApiProvider::class, $provider->id);
                }
            });
    }

    public function createOnce(string $type, string $severity, string $title, string $message, ?string $sourceType = null, ?int $sourceId = null, array $metadata = []): SystemAlert
    {
        return SystemAlert::firstOrCreate([
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'resolved_at' => null,
        ], [
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }
}
