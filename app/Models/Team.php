<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;
    protected $fillable = ['sport_id', 'country_id', 'external_provider_id', 'external_id', 'name', 'short_name', 'slug', 'logo_url', 'founded', 'venue_name', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function sport() { return $this->belongsTo(Sport::class); }
    public function country() { return $this->belongsTo(Country::class); }
    public function homeMatches() { return $this->hasMany(SportsMatch::class, 'home_team_id'); }
    public function awayMatches() { return $this->hasMany(SportsMatch::class, 'away_team_id'); }
}
