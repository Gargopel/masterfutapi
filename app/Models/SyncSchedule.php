<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncSchedule extends Model
{
    use HasFactory;

    protected $fillable = ['api_provider_id', 'sport_id', 'league_id', 'season_id', 'type', 'name', 'cron_expression', 'frequency', 'config', 'is_active', 'last_run_at', 'next_run_at', 'last_sync_job_id', 'last_error'];
    protected $casts = ['config' => 'array', 'is_active' => 'boolean', 'last_run_at' => 'datetime', 'next_run_at' => 'datetime'];

    public function provider() { return $this->belongsTo(ApiProvider::class, 'api_provider_id'); }
    public function lastSyncJob() { return $this->belongsTo(SyncJob::class, 'last_sync_job_id'); }
}
