<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;
    protected $fillable = ['league_id', 'year', 'name', 'starts_at', 'ends_at', 'is_current', 'external_id'];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'is_current' => 'boolean'];
    public function league() { return $this->belongsTo(League::class); }
}
