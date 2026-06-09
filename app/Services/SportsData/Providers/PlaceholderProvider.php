<?php

namespace App\Services\SportsData\Providers;

use App\Support\SportsData\ProviderTestResult;

class PlaceholderProvider extends AbstractSportsProvider
{
    public function testConnection(): ProviderTestResult
    {
        return new ProviderTestResult(false, 'Provider planned; connector not implemented yet.');
    }
}
