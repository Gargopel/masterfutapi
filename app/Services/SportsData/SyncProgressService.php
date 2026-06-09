<?php

namespace App\Services\SportsData;

use App\Models\SyncJob;
use Throwable;

class SyncProgressService
{
    public function calculate(?int $processed, ?int $total): float
    {
        if (! $total || $total < 1) {
            return $processed ? 100.0 : 0.0;
        }

        return round(min(100, max(0, ($processed ?? 0) / $total * 100)), 2);
    }

    public function update(SyncJob $job): void
    {
        $job->update(['progress_percent' => $this->calculate($job->processed_items, $job->total_items)]);
    }

    public function start(SyncJob $job, ?int $totalItems = null): SyncJob
    {
        $job->update([
            'status' => 'running',
            'started_at' => $job->started_at ?? now(),
            'finished_at' => null,
            'last_error' => null,
            'total_items' => $totalItems,
            'processed_items' => 0,
            'created_items' => 0,
            'updated_items' => 0,
            'failed_items' => 0,
            'progress_percent' => 0,
        ]);

        return $job->fresh();
    }

    public function advance(SyncJob $job, ?string $action = null): SyncJob
    {
        $job->refresh();
        $job->forceFill([
            'processed_items' => ($job->processed_items ?? 0) + 1,
            'created_items' => ($job->created_items ?? 0) + ($action === 'created' ? 1 : 0),
            'updated_items' => ($job->updated_items ?? 0) + ($action === 'updated' ? 1 : 0),
        ])->save();
        $this->update($job->fresh());

        return $job->fresh();
    }

    public function markItemCreated(SyncJob $job): SyncJob
    {
        return $this->advance($job, 'created');
    }

    public function markItemUpdated(SyncJob $job): SyncJob
    {
        return $this->advance($job, 'updated');
    }

    public function markItemFailed(SyncJob $job): SyncJob
    {
        $job->refresh();
        $job->forceFill(['failed_items' => ($job->failed_items ?? 0) + 1])->save();

        return $job->fresh();
    }

    public function complete(SyncJob $job, array $result = []): SyncJob
    {
        $job->refresh();
        $job->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'finished_at' => now(),
            'result' => $result,
        ]);

        return $job->fresh();
    }

    public function fail(SyncJob $job, Throwable|string $error): SyncJob
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $job->update(['status' => 'failed', 'last_error' => $message, 'finished_at' => now()]);

        return $job->fresh();
    }

    public function cancel(SyncJob $job, array $result = []): SyncJob
    {
        $result += ['cancelled_by_request' => true, 'processed_until_cancel' => $job->processed_items ?? 0];
        if ($job->status === 'running') {
            $job->update(['status' => 'cancelled', 'cancel_requested_at' => now(), 'finished_at' => now(), 'result' => $result]);
        } else {
            $job->update(['status' => 'cancelled', 'finished_at' => now(), 'result' => $result]);
        }

        return $job->fresh();
    }

    public function shouldCancel(SyncJob $job): bool
    {
        $job->refresh();
        return $job->status === 'cancelled' || $job->cancel_requested_at !== null;
    }

    public function overall(): float
    {
        $totals = SyncJob::query()
            ->whereIn('status', ['running', 'completed'])
            ->selectRaw('SUM(processed_items) as processed, SUM(total_items) as total')
            ->first();

        return $this->calculate((int) $totals?->processed, (int) $totals?->total);
    }
}
