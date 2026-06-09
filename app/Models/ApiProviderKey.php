<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiProviderKey extends Model
{
    use HasFactory;

    protected $fillable = ['api_provider_id', 'name', 'encrypted_key', 'key_hint', 'is_active', 'requests_per_minute', 'requests_per_day', 'requests_used_today', 'last_used_at', 'cooldown_until', 'last_error'];
    protected $hidden = ['encrypted_key'];
    protected $casts = ['encrypted_key' => 'encrypted', 'is_active' => 'boolean', 'last_used_at' => 'datetime', 'cooldown_until' => 'datetime'];

    public function provider() { return $this->belongsTo(ApiProvider::class, 'api_provider_id'); }
}
