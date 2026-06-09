<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchStatistic extends Model
{
    use HasFactory;
    protected $fillable = ['match_id', 'team_id', 'shots', 'shots_on_target', 'corners', 'yellow_cards', 'red_cards', 'possession', 'expected_goals', 'raw_payload'];
    protected $casts = ['raw_payload' => 'array'];
}
