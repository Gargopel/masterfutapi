<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderMapping extends Model
{
    use HasFactory;
    protected $fillable = ['provider_id', 'entity_type', 'entity_id', 'external_id', 'external_name', 'raw_payload'];
    protected $casts = ['raw_payload' => 'array'];
}
