<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportsMatch extends Model
{
    use HasFactory;
    protected $table = 'matches';
    protected $fillable = ['sport_id', 'league_id', 'season_id', 'home_team_id', 'away_team_id', 'external_provider_id', 'external_id', 'starts_at', 'status', 'minute', 'home_score', 'away_score', 'venue', 'timezone', 'raw_payload', 'last_synced_at'];
    protected $casts = ['starts_at' => 'datetime', 'raw_payload' => 'array', 'last_synced_at' => 'datetime'];
    public function league() { return $this->belongsTo(League::class); }
    public function season() { return $this->belongsTo(Season::class); }
    public function homeTeam() { return $this->belongsTo(Team::class, 'home_team_id'); }
    public function awayTeam() { return $this->belongsTo(Team::class, 'away_team_id'); }
    public function provider() { return $this->belongsTo(ApiProvider::class, 'external_provider_id'); }
}
