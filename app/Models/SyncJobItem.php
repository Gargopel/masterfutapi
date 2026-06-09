<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncJobItem extends Model
{
    use HasFactory;
    protected $fillable = ['sync_job_id', 'entity_type', 'entity_id', 'external_id', 'status', 'action', 'error_message', 'raw_payload'];
    protected $casts = ['raw_payload' => 'array'];
}
