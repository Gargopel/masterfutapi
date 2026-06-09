<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\SiteSetting;
use App\Models\SportsMatch;
use App\Models\Sport;
use App\Models\Standing;
use App\Models\Team;
use App\Models\Country;
use App\Models\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PublicPageController extends Controller
{
    public function home()
    {
        return view('home', [
            'settings' => SiteSetting::homepage(),
            'stats' => [
                'leagues' => $this->countTable('leagues', League::class),
                'teams' => $this->countTable('teams', Team::class),
                'matches' => $this->countTable('sports_matches', SportsMatch::class),
            ],
        ]);
    }

    public function dashboard()
    {
        return view('user-dashboard', [
            'user' => auth()->user(),
            'metadataUrl' => url('/api/v1/metadata'),
            'stats' => $this->apiStats(),
        ]);
    }

    public function profile()
    {
        return view('user-profile', [
            'user' => auth()->user(),
        ]);
    }

    public function docs()
    {
        return view('docs', [
            'baseUrl' => url('/api/v1'),
            'settings' => SiteSetting::homepage(),
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function countTable(string $table, string $model): int
    {
        return Schema::hasTable($table) ? $model::count() : 0;
    }

    private function apiStats(): array
    {
        return [
            'sports' => $this->countTable('sports', Sport::class),
            'countries' => $this->countTable('countries', Country::class),
            'leagues' => $this->countTable('leagues', League::class),
            'seasons' => $this->countTable('seasons', Season::class),
            'teams' => $this->countTable('teams', Team::class),
            'matches' => $this->countTable('sports_matches', SportsMatch::class),
            'standings' => $this->countTable('standings', Standing::class),
        ];
    }
}
