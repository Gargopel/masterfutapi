<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchEvent extends Model
{
    use HasFactory;
    protected $fillable = ['match_id', 'team_id', 'player_name', 'event_type', 'minute', 'extra_minute', 'description', 'raw_payload'];
    protected $casts = ['raw_payload' => 'array'];
}
