<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    use HasFactory;

    protected $fillable = ['api_provider_id', 'api_provider_key_id', 'sync_job_id', 'method', 'endpoint', 'status_code', 'success', 'duration_ms', 'error_message', 'requested_at', 'response_excerpt'];
    protected $casts = ['success' => 'boolean', 'requested_at' => 'datetime'];

    public function provider() { return $this->belongsTo(ApiProvider::class, 'api_provider_id'); }
    public function key() { return $this->belongsTo(ApiProviderKey::class, 'api_provider_key_id'); }
    public function syncJob() { return $this->belongsTo(SyncJob::class); }
}
