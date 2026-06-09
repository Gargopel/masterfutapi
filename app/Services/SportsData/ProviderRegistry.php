<?php

namespace App\Services\SportsData;

use App\Models\ApiProvider;
use App\Services\SportsData\Contracts\SportsDataProviderInterface;
use App\Services\SportsData\Providers\ApiFootballProvider;
use App\Services\SportsData\Providers\FootballDataOrgProvider;
use App\Services\SportsData\Providers\PlaceholderProvider;

class ProviderRegistry
{
    public function make(ApiProvider $provider): SportsDataProviderInterface
    {
        return match ($provider->slug) {
            'football-data' => new FootballDataOrgProvider($provider),
            'api-football' => new ApiFootballProvider($provider),
            default => new PlaceholderProvider($provider),
        };
    }
}
