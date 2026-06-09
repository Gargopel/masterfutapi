<?php

namespace App\Services\SportsData;

use App\Models\ApiProvider;
use App\Models\ApiProviderKey;

class ProviderKeyResolver
{
    public function resolve(ApiProvider $provider): ?ApiProviderKey
    {
        $keys = $provider->keys()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', now()))
            ->orderBy('requests_used_today')
            ->get();

        return $keys->first(fn (ApiProviderKey $key) => app(ProviderRateLimiter::class)->canRequest($provider, $key)) ?? $keys->first();
    }
}
