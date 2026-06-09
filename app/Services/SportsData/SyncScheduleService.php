<?php

namespace App\Services\SportsData;

use App\Models\SyncJob;
use App\Models\SyncSchedule;
use Carbon\Carbon;

class SyncScheduleService
{
    public function __construct(private SyncLockService $locks, private SystemAlertService $alerts) {}

    public function createDueJobs(): int
    {
        $created = 0;

        SyncSchedule::where('is_active', true)
            ->where(fn ($query) => $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
            ->get()
            ->each(function (SyncSchedule $schedule) use (&$created) {
                $scope = [
                    'api_provider_id' => $schedule->api_provider_id,
                    'sport_id' => $schedule->sport_id,
                    'league_id' => $schedule->league_id,
                    'season_id' => $schedule->season_id,
                    'type' => $schedule->type,
                    'config' => $schedule->config,
                ];

                if ($this->locks->hasDuplicate($scope)) {
                    $schedule->update(['last_error' => 'Pending or running duplicate sync job already exists.', 'next_run_at' => $this->nextRunAt($schedule)]);
                    return;
                }

                try {
                    $job = SyncJob::create($scope + [
                        'sync_schedule_id' => $schedule->id,
                        'source' => 'scheduled',
                        'status' => 'pending',
                        'progress_percent' => 0,
                        'is_incremental' => filled(data_get($schedule->config, 'updated_since')),
                    ]);

                    $schedule->update([
                        'last_run_at' => now(),
                        'next_run_at' => $this->nextRunAt($schedule),
                        'last_sync_job_id' => $job->id,
                        'last_error' => null,
                    ]);
                    $created++;
                } catch (\Throwable $e) {
                    $schedule->update(['last_error' => $e->getMessage()]);
                    $this->alerts->createOnce('schedule_failed', 'error', "Schedule {$schedule->name} failed", $e->getMessage(), SyncSchedule::class, $schedule->id);
                }
            });

        return $created;
    }

    public function nextRunAt(SyncSchedule $schedule): Carbon
    {
        return match ($schedule->frequency) {
            'hourly' => now()->addHour(),
            'every_6_hours' => now()->addHours(6),
            'every_12_hours' => now()->addHours(12),
            'weekly' => now()->addWeek(),
            default => now()->addDay(),
        };
    }
}
