<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiProvider extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'type', 'base_url', 'website_url', 'docs_url', 'developer_url', 'is_active', 'status', 'rate_limit_per_minute', 'rate_limit_per_day', 'priority', 'config', 'last_checked_at', 'last_error'];
    protected $casts = ['is_active' => 'boolean', 'config' => 'array', 'last_checked_at' => 'datetime'];

    public function keys() { return $this->hasMany(ApiProviderKey::class); }
    public function requestLogs() { return $this->hasMany(ApiRequestLog::class); }
    public function syncJobs() { return $this->hasMany(SyncJob::class); }
}
