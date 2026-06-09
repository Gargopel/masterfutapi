<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Standing extends Model
{
    use HasFactory;
    protected $fillable = ['league_id', 'season_id', 'team_id', 'position', 'points', 'played', 'won', 'draw', 'lost', 'goals_for', 'goals_against', 'goal_difference', 'raw_payload'];
    protected $casts = ['raw_payload' => 'array'];
    public function league() { return $this->belongsTo(League::class); }
    public function season() { return $this->belongsTo(Season::class); }
    public function team() { return $this->belongsTo(Team::class); }
}
