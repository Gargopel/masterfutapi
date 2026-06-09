<?php

namespace App\Services\SportsData\Contracts;

use App\Models\SyncJob;
use App\Support\SportsData\ProviderTestResult;
use App\Support\SportsData\SyncResult;

interface SportsDataProviderInterface
{
    public function testConnection(): ProviderTestResult;
    public function syncLeagues(SyncJob $job): SyncResult;
    public function syncTeams(SyncJob $job): SyncResult;
    public function syncMatches(SyncJob $job): SyncResult;
    public function syncStandings(SyncJob $job): SyncResult;
    public function syncMatchStatistics(SyncJob $job): SyncResult;
}
