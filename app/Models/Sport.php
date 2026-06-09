<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sport extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'slug', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function leagues() { return $this->hasMany(League::class); }
}
