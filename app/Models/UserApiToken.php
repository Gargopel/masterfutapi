<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UserApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'app_device_id',
        'name',
        'token_hash',
        'token_prefix',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function issueFor(User $user, string $name, ?AppDevice $device = null): array
    {
        $plainTextToken = 'mf_live_'.Str::random(48);

        $token = static::create([
            'user_id' => $user->id,
            'app_device_id' => $device?->id,
            'name' => $name,
            'token_hash' => static::hashToken($plainTextToken),
            'token_prefix' => substr($plainTextToken, 0, 16),
        ]);

        return [$token, $plainTextToken];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appDevice(): BelongsTo
    {
        return $this->belongsTo(AppDevice::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(UserApiRequestLog::class);
    }
}
