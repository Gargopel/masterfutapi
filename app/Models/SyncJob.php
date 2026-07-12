<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncJob extends Model
{
    use HasFactory;
    protected $fillable = ['parent_sync_job_id', 'sync_schedule_id', 'api_provider_id', 'sport_id', 'league_id', 'season_id', 'type', 'source', 'is_incremental', 'status', 'available_at', 'progress_percent', 'total_items', 'processed_items', 'created_items', 'updated_items', 'failed_items', 'started_at', 'finished_at', 'cancel_requested_at', 'last_error', 'config', 'result'];
    protected $casts = ['is_incremental' => 'boolean', 'progress_percent' => 'decimal:2', 'available_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'cancel_requested_at' => 'datetime', 'config' => 'array', 'result' => 'array'];
    public function provider() { return $this->belongsTo(ApiProvider::class, 'api_provider_id'); }
    public function parent() { return $this->belongsTo(SyncJob::class, 'parent_sync_job_id'); }
    public function reruns() { return $this->hasMany(SyncJob::class, 'parent_sync_job_id'); }
    public function sport() { return $this->belongsTo(Sport::class); }
    public function league() { return $this->belongsTo(League::class); }
    public function season() { return $this->belongsTo(Season::class); }
    public function items() { return $this->hasMany(SyncJobItem::class); }
    public function requestLogs() { return $this->hasMany(ApiRequestLog::class); }
    public function schedule() { return $this->belongsTo(SyncSchedule::class, 'sync_schedule_id'); }
}
