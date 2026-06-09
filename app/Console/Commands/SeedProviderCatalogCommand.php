<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\Country;
use App\Models\League;
use App\Models\Sport;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedProviderCatalogCommand extends Command
{
    protected $signature = 'futia:providers:seed';
    protected $description = 'Seed sports, countries, providers, and starter football catalog data without touching admin users.';

    public function handle(): int
    {
        foreach (['Football', 'Basketball', 'Tennis', 'Volleyball', 'Baseball', 'eSports'] as $sport) {
            Sport::updateOrCreate(['slug' => Str::slug($sport)], ['name' => $sport, 'is_active' => true]);
        }

        foreach ([['Brazil', 'BR'], ['United States', 'US'], ['England', 'GB-ENG'], ['Spain', 'ES'], ['Germany', 'DE'], ['Italy', 'IT'], ['France', 'FR']] as [$name, $code]) {
            Country::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        $providers = [
            ['Football-Data.org', 'football-data', 'https://api.football-data.org/v4', 'https://www.football-data.org', 'https://www.football-data.org/documentation/quickstart', 'inactive', 10, null, 10],
            ['API-Football', 'api-football', 'https://v3.football.api-sports.io', 'https://www.api-football.com', 'https://www.api-football.com/documentation-v3', 'inactive', null, null, 20],
            ['SportMonks', 'sportmonks', null, 'https://www.sportmonks.com/football-api/', null, 'planned', null, null, 30],
            ['TheSportsDB', 'thesportsdb', null, 'https://www.thesportsdb.com', null, 'planned', null, null, 40],
            ['OpenLigaDB', 'openligadb', null, 'https://www.openligadb.de', null, 'planned', null, null, 50],
            ['Sportradar', 'sportradar', null, 'https://sportradar.com', null, 'planned', null, null, 60],
            ['Stats Perform/Opta', 'stats-perform-opta', null, 'https://www.statsperform.com', null, 'planned', null, null, 70],
            ['Genius Sports', 'genius-sports', null, 'https://www.geniussports.com', null, 'planned', null, null, 80],
        ];

        foreach ($providers as [$name, $slug, $baseUrl, $websiteUrl, $docsUrl, $status, $rpm, $rpd, $priority]) {
            ApiProvider::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'type' => 'football',
                'base_url' => $baseUrl,
                'website_url' => $websiteUrl,
                'docs_url' => $docsUrl,
                'is_active' => false,
                'status' => $status,
                'rate_limit_per_minute' => $rpm,
                'rate_limit_per_day' => $rpd,
                'priority' => $priority,
            ]);
        }

        $football = Sport::where('slug', 'football')->first();
        $brazil = Country::where('code', 'BR')->first();

        if ($football && $brazil) {
            League::updateOrCreate(['slug' => 'brasileirao-serie-a'], [
                'sport_id' => $football->id,
                'country_id' => $brazil->id,
                'name' => 'Brasileirao Serie A',
                'type' => 'league',
                'is_active' => true,
            ]);
        }

        $this->info('Provider catalog seeded.');

        return self::SUCCESS;
    }
}
