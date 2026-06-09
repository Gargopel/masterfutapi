<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasFactory;
    protected $fillable = ['team_id', 'country_id', 'name', 'external_id', 'birth_date', 'position', 'photo_url', 'raw_payload'];
    protected $casts = ['birth_date' => 'date', 'raw_payload' => 'array'];
}
