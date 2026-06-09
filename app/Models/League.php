<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class League extends Model
{
    use HasFactory;
    protected $fillable = ['sport_id', 'country_id', 'external_provider_id', 'external_id', 'name', 'slug', 'type', 'logo_url', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function sport() { return $this->belongsTo(Sport::class); }
    public function country() { return $this->belongsTo(Country::class); }
    public function matches() { return $this->hasMany(SportsMatch::class); }
    public function seasons() { return $this->hasMany(Season::class); }
}
