<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApiRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_api_token_id',
        'method',
        'endpoint',
        'status_code',
        'duration_ms',
        'requested_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(UserApiToken::class, 'user_api_token_id');
    }
}
