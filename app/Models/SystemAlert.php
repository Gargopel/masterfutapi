<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'severity', 'title', 'message', 'source_type', 'source_id', 'is_read', 'resolved_at', 'metadata'];
    protected $casts = ['is_read' => 'boolean', 'resolved_at' => 'datetime', 'metadata' => 'array'];
}
