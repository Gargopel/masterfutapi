<?php

namespace App\Services\SportsData;

use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\ApiRequestLog;

class ProviderRateLimiter
{
    public function canRequest(ApiProvider $provider, ?ApiProviderKey $key = null): bool
    {
        $minuteLimit = $key?->requests_per_minute ?? $provider->rate_limit_per_minute;
        $dayLimit = $key?->requests_per_day ?? $provider->rate_limit_per_day;

        if ($key && $key->cooldown_until && $key->cooldown_until->isFuture()) {
            return false;
        }

        if ($minuteLimit !== null) {
            $query = ApiRequestLog::where('api_provider_id', $provider->id)->where('requested_at', '>=', now()->subMinute());
            if ($key) {
                $query->where('api_provider_key_id', $key->id);
            }
            if ($query->count() >= $minuteLimit) {
                return false;
            }
        }

        if ($dayLimit !== null && $key && $key->requests_used_today >= $dayLimit) {
            return false;
        }

        return true;
    }

    public function markUsed(ApiProviderKey $key): void
    {
        $key->forceFill([
            'requests_used_today' => $key->requests_used_today + 1,
            'last_used_at' => now(),
        ])->save();
    }

    public function cooldown(ApiProviderKey $key, ?string $error = null): void
    {
        $key->forceFill(['cooldown_until' => now()->addMinutes(15), 'last_error' => $error])->save();
    }
}
