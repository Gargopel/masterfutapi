<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
        'allow_all',
        'requests_per_minute',
        'max_active_api_keys',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'allow_all' => 'boolean',
    ];

    public function accessRules(): HasMany
    {
        return $this->hasMany(PlanAccessRule::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function default(): self
    {
        return static::query()->where('is_default', true)->where('is_active', true)->first()
            ?? static::query()->firstOrCreate(
                ['slug' => 'free'],
                [
                    'name' => 'Free',
                    'description' => 'Plano inicial.',
                    'is_active' => true,
                    'is_default' => true,
                    'allow_all' => false,
                    'requests_per_minute' => 10,
                    'max_active_api_keys' => 3,
                ],
            );
    }
}
